<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Merchant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CampaignController extends Controller
{
    private function merchant(Request $request): Merchant
    {
        return $request->attributes->get('merchant');
    }

    public function index(Request $request): JsonResponse
    {
        $campaigns = Campaign::where('merchant_id', $this->merchant($request)->id)
            ->withCount('vouchers')
            ->latest()
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $campaigns,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $campaign = Campaign::create([
            'merchant_id' => $this->merchant($request)->id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'starts_at' => $validated['starts_at'],
            'ends_at' => $validated['ends_at'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Kampanye berhasil dibuat.',
            'data' => $campaign,
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $campaign = Campaign::where('merchant_id', $this->merchant($request)->id)
            ->with('vouchers')
            ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $campaign,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $campaign = Campaign::where('merchant_id', $this->merchant($request)->id)
            ->findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'starts_at' => ['sometimes', 'required', 'date'],
            'ends_at' => ['sometimes', 'required', 'date'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $startsAt = $validated['starts_at'] ?? $campaign->starts_at;
        $endsAt = $validated['ends_at'] ?? $campaign->ends_at;

        if (strtotime((string) $endsAt) <= strtotime((string) $startsAt)) {
            return response()->json([
                'status' => 'error',
                'code' => 'VALIDATION_ERROR',
                'message' => 'Tanggal ends_at harus setelah starts_at.',
            ], 422);
        }

        $campaign->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Kampanye berhasil diperbarui.',
            'data' => $campaign->fresh(),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $campaign = Campaign::where('merchant_id', $this->merchant($request)->id)
            ->findOrFail($id);

        $campaign->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Kampanye berhasil dihapus.',
        ]);
    }

    public function report(Request $request, int $id): JsonResponse
    {
        $campaign = Campaign::where('merchant_id', $this->merchant($request)->id)
            ->with(['vouchers.redemptions'])
            ->findOrFail($id);

        $allRedemptions = $campaign->vouchers->flatMap->redemptions;

        $vouchersSummary = $campaign->vouchers->map(function ($voucher) {
            return [
                'id' => $voucher->id,
                'code' => $voucher->code,
                'discount_type' => $voucher->discount_type,
                'discount_value' => $voucher->discount_value,
                'quota_total' => $voucher->quota_total,
                'quota_used' => $voucher->quota_used,
                'quota_remaining' => $voucher->quota_total !== null
                    ? max(0, $voucher->quota_total - $voucher->quota_used)
                    : null,
                'redemptions_count' => $voucher->redemptions->count(),
                'total_discount_given' => round((float) $voucher->redemptions->sum('discount_amount'), 2),
                'is_active' => $voucher->is_active,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => [
                'campaign' => [
                    'id' => $campaign->id,
                    'name' => $campaign->name,
                    'starts_at' => $campaign->starts_at->toIso8601String(),
                    'ends_at' => $campaign->ends_at->toIso8601String(),
                    'is_active' => $campaign->is_active,
                ],
                'summary' => [
                    'total_vouchers' => $campaign->vouchers->count(),
                    'total_redemptions' => $allRedemptions->count(),
                    'total_order_amount' => round((float) $allRedemptions->sum('order_amount'), 2),
                    'total_discount_given' => round((float) $allRedemptions->sum('discount_amount'), 2),
                ],
                'vouchers' => $vouchersSummary,
            ],
        ]);
    }
}