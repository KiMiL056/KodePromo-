<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\VoucherException;
use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Models\Redemption;
use App\Services\VoucherRedemptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VoucherRedemptionController extends Controller
{
    public function __construct(
        private readonly VoucherRedemptionService $service
    ) {}

    private function merchant(Request $request): Merchant
    {
        return $request->attributes->get('merchant');
    }

    public function index(Request $request): JsonResponse
    {
        $query = Redemption::whereHas('voucher.campaign', fn ($q) => $q->where('merchant_id', $this->merchant($request)->id))
            ->with('voucher.campaign')
            ->orderByDesc('id');

        if ($request->filled('user_identifier')) {
            $query->where('user_identifier', trim((string) $request->query('user_identifier')));
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->get(),
        ]);
    }

    public function validateCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'order_amount' => ['required', 'numeric', 'gt:0'],
            'user_identifier' => ['required', 'string', 'max:150'],
        ]);

        try {
            $result = $this->service->validateVoucher(
                $this->merchant($request),
                $validated['code'],
                (float) $validated['order_amount'],
                $validated['user_identifier']
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Voucher valid dan dapat digunakan.',
                'data' => $result,
            ], 200);
        } catch (VoucherException $e) {
            return response()->json([
                'status' => 'error',
                'code' => $e->errorCode,
                'message' => $e->getMessage(),
                'meta' => $e->meta,
            ], $e->statusCode);
        }
    }

    public function redeem(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'order_id' => ['required', 'string', 'max:100'],
            'order_amount' => ['required', 'numeric', 'gt:0'],
            'user_identifier' => ['required', 'string', 'max:150'],
        ]);

        $idempotencyKey = $request->header('Idempotency-Key');

        try {
            $result = $this->service->redeemVoucher(
                $this->merchant($request),
                $validated['code'],
                $validated['order_id'],
                (float) $validated['order_amount'],
                $validated['user_identifier'],
                $idempotencyKey
            );

            $redemption = $result['redemption'];
            $voucher = $result['voucher'];
            $isReplay = $result['replayed'];

            return response()->json([
                'status' => 'success',
                'message' => $isReplay
                    ? 'Transaksi idempotent dikembalikan tanpa pemotongan kuota ulang.'
                    : 'Voucher berhasil ditukarkan.',
                'idempotent_replay' => $isReplay,
                'data' => [
                    'redemption_id' => $redemption->id,
                    'voucher_code' => $voucher->code,
                    'order_id' => $redemption->order_id,
                    'user_identifier' => $redemption->user_identifier,
                    'order_amount' => $redemption->order_amount,
                    'discount_amount' => $redemption->discount_amount,
                    'final_amount' => $redemption->final_amount,
                    'quota_remaining' => $voucher->quota_total !== null
                        ? max(0, $voucher->quota_total - $voucher->quota_used)
                        : null,
                    'redeemed_at' => $redemption->redeemed_at->toIso8601String(),
                ],
            ], $isReplay ? 200 : 201);
        } catch (VoucherException $e) {
            return response()->json([
                'status' => 'error',
                'code' => $e->errorCode,
                'message' => $e->getMessage(),
                'meta' => $e->meta,
            ], $e->statusCode);
        }
    }
}