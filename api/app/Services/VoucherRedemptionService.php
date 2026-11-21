<?php

namespace App\Services;

use App\Exceptions\VoucherException;
use App\Models\Merchant;
use App\Models\Redemption;
use App\Models\Voucher;
use Illuminate\Support\Facades\DB;

class VoucherRedemptionService
{
    /**
     * Simulasi validasi voucher tanpa mengubah kuota (BR-01 s.d. BR-06).
     */
    public function validateVoucher(
        Merchant $merchant,
        string $code,
        float $orderAmount,
        string $userIdentifier
    ): array {
        $normalizedCode = strtoupper(trim($code));

        $voucher = Voucher::where('code', $normalizedCode)
            ->whereHas('campaign', fn ($q) => $q->where('merchant_id', $merchant->id))
            ->with('campaign')
            ->first();

        $this->assertEligible($voucher, $orderAmount, $userIdentifier);

        [$discountAmount, $finalAmount] = $this->calculateDiscount($voucher, $orderAmount);

        return [
            'valid' => true,
            'voucher' => [
                'id' => $voucher->id,
                'code' => $voucher->code,
                'campaign_name' => $voucher->campaign->name,
                'discount_type' => $voucher->discount_type,
                'discount_value' => $voucher->discount_value,
                'max_discount' => $voucher->max_discount,
                'min_order_amount' => $voucher->min_order_amount,
                'quota_remaining' => $voucher->quota_total !== null
                    ? max(0, $voucher->quota_total - $voucher->quota_used)
                    : null,
            ],
            'calculation' => [
                'order_amount' => round($orderAmount, 2),
                'discount_amount' => $discountAmount,
                'final_amount' => $finalAmount,
            ],
        ];
    }

    /**
     * Penukaran voucher atomik dengan row-lock dan idempotency key (BR-01 s.d. BR-09).
     */
    public function redeemVoucher(
        Merchant $merchant,
        string $code,
        string $orderId,
        float $orderAmount,
        string $userIdentifier,
        ?string $idempotencyKey = null
    ): array {
        $normalizedCode = strtoupper(trim($code));
        $trimmedKey = $idempotencyKey ? trim($idempotencyKey) : null;

        return DB::transaction(function () use (
            $merchant,
            $normalizedCode,
            $orderId,
            $orderAmount,
            $userIdentifier,
            $trimmedKey
        ) {
            // BR-08: Cek idempotency key lebih dulu agar retry tidak memotong kuota dua kali
            if ($trimmedKey) {
                $existingByKey = Redemption::where('idempotency_key', $trimmedKey)
                    ->whereHas('voucher.campaign', fn ($q) => $q->where('merchant_id', $merchant->id))
                    ->with('voucher')
                    ->first();

                if ($existingByKey) {
                    return [
                        'replayed' => true,
                        'redemption' => $existingByKey,
                        'voucher' => $existingByKey->voucher,
                    ];
                }
            }

            // BR-07: Kunci baris voucher di database selama transaksi berjalan
            $voucher = Voucher::where('code', $normalizedCode)
                ->whereHas('campaign', fn ($q) => $q->where('merchant_id', $merchant->id))
                ->with('campaign')
                ->lockForUpdate()
                ->first();

            $this->assertEligible($voucher, $orderAmount, $userIdentifier);

            // BR-08: Tolak jika order_id yang sama sudah memakai voucher ini
            $alreadyRedeemedOrder = Redemption::where('voucher_id', $voucher->id)
                ->where('order_id', $orderId)
                ->exists();

            if ($alreadyRedeemedOrder) {
                throw new VoucherException(
                    'ORDER_ALREADY_REDEEMED',
                    "Order '{$orderId}' sudah pernah menukarkan voucher '{$voucher->code}'.",
                    409
                );
            }

            // BR-06: Hitung nominal potongan
            [$discountAmount, $finalAmount] = $this->calculateDiscount($voucher, $orderAmount);

            // BR-09: Catat transaksi redemption & naikkan quota_used
            $redemption = Redemption::create([
                'voucher_id' => $voucher->id,
                'order_id' => $orderId,
                'user_identifier' => $userIdentifier,
                'idempotency_key' => $trimmedKey,
                'order_amount' => round($orderAmount, 2),
                'discount_amount' => $discountAmount,
                'final_amount' => $finalAmount,
                'redeemed_at' => now(),
            ]);

            $voucher->increment('quota_used');
            $voucher->refresh();

            return [
                'replayed' => false,
                'redemption' => $redemption,
                'voucher' => $voucher,
            ];
        });
    }

    private function assertEligible(?Voucher $voucher, float $orderAmount, string $userIdentifier): void
    {
        // BR-01: Voucher ditemukan & aktif
        if (!$voucher) {
            throw new VoucherException(
                'VOUCHER_NOT_FOUND',
                'Kode voucher tidak ditemukan.',
                404
            );
        }

        if (!$voucher->is_active) {
            throw new VoucherException(
                'VOUCHER_INACTIVE',
                "Voucher '{$voucher->code}' sedang tidak aktif.",
                422
            );
        }

        // BR-02: Kampanye aktif & berada dalam rentang waktu berlaku
        $campaign = $voucher->campaign;
        if (!$campaign || !$campaign->is_active) {
            throw new VoucherException(
                'CAMPAIGN_INACTIVE',
                'Kampanye untuk voucher ini sedang tidak aktif.',
                422
            );
        }

        $now = now();
        if ($now->lt($campaign->starts_at)) {
            throw new VoucherException(
                'CAMPAIGN_NOT_STARTED',
                'Periode kampanye promo belum dimulai.',
                422,
                ['starts_at' => $campaign->starts_at->toIso8601String()]
            );
        }

        if ($now->gt($campaign->ends_at)) {
            throw new VoucherException(
                'CAMPAIGN_EXPIRED',
                'Periode kampanye promo sudah berakhir.',
                422,
                ['ends_at' => $campaign->ends_at->toIso8601String()]
            );
        }

        // BR-03: Minimum belanja terpenuhi
        if ($orderAmount < $voucher->min_order_amount) {
            throw new VoucherException(
                'MIN_ORDER_NOT_MET',
                'Total belanja belum memenuhi syarat minimum voucher.',
                422,
                [
                    'min_order_amount' => $voucher->min_order_amount,
                    'order_amount' => round($orderAmount, 2),
                ]
            );
        }

        // BR-04: Kuota total masih tersedia
        if ($voucher->quota_total !== null && $voucher->quota_used >= $voucher->quota_total) {
            throw new VoucherException(
                'QUOTA_EXCEEDED',
                "Kuota voucher '{$voucher->code}' sudah habis.",
                422
            );
        }

        // BR-05: Batas pemakaian per pengguna
        $userUses = Redemption::where('voucher_id', $voucher->id)
            ->where('user_identifier', $userIdentifier)
            ->count();

        if ($userUses >= $voucher->max_uses_per_user) {
            throw new VoucherException(
                'USER_LIMIT_EXCEEDED',
                'Batas penggunaan voucher untuk pengguna ini telah tercapai.',
                422,
                ['max_uses_per_user' => $voucher->max_uses_per_user]
            );
        }
    }

    /**
     * @return array{0: float, 1: float} [discountAmount, finalAmount]
     */
    private function calculateDiscount(Voucher $voucher, float $orderAmount): array
    {
        if ($voucher->discount_type === 'percentage') {
            $rawDiscount = $orderAmount * ($voucher->discount_value / 100);
            if ($voucher->max_discount !== null) {
                $rawDiscount = min($rawDiscount, $voucher->max_discount);
            }
        } else {
            $rawDiscount = $voucher->discount_value;
        }

        $discountAmount = round(min($rawDiscount, $orderAmount), 2);
        $finalAmount = round(max(0, $orderAmount - $discountAmount), 2);

        return [$discountAmount, $finalAmount];
    }
}