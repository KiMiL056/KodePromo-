<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Merchant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

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
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $campaigns,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $payload = [
            'merchant_id' => $this->merchant($request)->id,
            'name' => $validated['name'],
            'starts_at' => $validated['starts_at'],
            'ends_at' => $validated['ends_at'],
            'is_active' => $validated['is_active'] ?? true,
        ];

        if (Schema::hasColumn('campaigns', 'description')) {
            $payload['description'] = $validated['description'] ?? null;
        }

        $campaign = Campaign::create($payload);

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
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            'starts_at' => ['sometimes', 'required', 'date'],
            'ends_at' => ['sometimes', 'required', 'date'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (!Schema::hasColumn('campaigns', 'description')) {
            unset($validated['description']);
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

        $totalRedemptions = 0;
        $totalOrderAmount = 0.0;
        $totalDiscountGiven = 0.0;

        $voucherBreakdown = $campaign->vouchers->map(function ($v) use (&$totalRedemptions, &$totalOrderAmount, &$totalDiscountGiven) {
            $count = $v->redemptions->count();
            $orders = (float) $v->redemptions->sum('order_amount');
            $discounts = (float) $v->redemptions->sum('discount_amount');

            $totalRedemptions += $count;
            $totalOrderAmount += $orders;
            $totalDiscountGiven += $discounts;

            return [
                'voucher_id' => $v->id,
                'code' => $v->code,
                'quota_total' => $v->quota_total,
                'quota_used' => $v->quota_used,
                'redemptions_count' => $count,
                'total_discount_given' => round($discounts, 2),
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => [
                'campaign' => [
                    'id' => $campaign->id,
                    'name' => $campaign->name,
                    'starts_at' => $campaign->starts_at?->toIso8601String(),
                    'ends_at' => $campaign->ends_at?->toIso8601String(),
                    'is_active' => $campaign->is_active,
                ],
                'summary' => [
                    'total_vouchers' => $campaign->vouchers->count(),
                    'total_redemptions' => $totalRedemptions,
                    'total_order_amount' => round($totalOrderAmount, 2),
                    'total_discount_given' => round($totalDiscountGiven, 2),
                ],
                'vouchers' => $voucherBreakdown,
            ],
        ]);
    }
}