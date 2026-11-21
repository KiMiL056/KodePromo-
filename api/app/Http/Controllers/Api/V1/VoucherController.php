<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Merchant;
use App\Models\Voucher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VoucherController extends Controller
{
    private function merchant(Request $request): Merchant
    {
        return $request->attributes->get('merchant');
    }

    public function index(Request $request): JsonResponse
    {
        $vouchers = Voucher::whereHas('campaign', fn ($q) => $q->where('merchant_id', $this->merchant($request)->id))
            ->with('campaign')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $vouchers,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $merchantId = $this->merchant($request)->id;

        $validated = $request->validate([
            'campaign_id' => [
                'required',
                'integer',
                Rule::exists('campaigns', 'id')->where('merchant_id', $merchantId),
            ],
            'code' => ['required', 'string', 'max:50', 'unique:vouchers,code'],
            'discount_type' => ['required', Rule::in(['percentage', 'fixed'])],
            'discount_value' => ['required', 'numeric', 'gt:0'],
            'max_discount' => ['nullable', 'numeric', 'min:0'],
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'quota_total' => ['nullable', 'integer', 'min:1'],
            'max_uses_per_user' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $voucher = Voucher::create([
            'campaign_id' => $validated['campaign_id'],
            'code' => strtoupper(trim($validated['code'])),
            'discount_type' => $validated['discount_type'],
            'discount_value' => $validated['discount_value'],
            'max_discount' => $validated['discount_type'] === 'percentage' ? ($validated['max_discount'] ?? null) : null,
            'min_order_amount' => $validated['min_order_amount'] ?? 0,
            'quota_total' => $validated['quota_total'] ?? null,
            'quota_used' => 0,
            'max_uses_per_user' => $validated['max_uses_per_user'] ?? 1,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Voucher berhasil dibuat.',
            'data' => $voucher->load('campaign'),
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $voucher = Voucher::whereHas('campaign', fn ($q) => $q->where('merchant_id', $this->merchant($request)->id))
            ->with('campaign')
            ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $voucher,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $merchantId = $this->merchant($request)->id;
        $voucher = Voucher::whereHas('campaign', fn ($q) => $q->where('merchant_id', $merchantId))
            ->findOrFail($id);

        $validated = $request->validate([
            'campaign_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('campaigns', 'id')->where('merchant_id', $merchantId),
            ],
            'code' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('vouchers', 'code')->ignore($voucher->id)],
            'discount_type' => ['sometimes', 'required', Rule::in(['percentage', 'fixed'])],
            'discount_value' => ['sometimes', 'required', 'numeric', 'gt:0'],
            'max_discount' => ['nullable', 'numeric', 'min:0'],
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'quota_total' => ['nullable', 'integer', 'min:1'],
            'max_uses_per_user' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (isset($validated['code'])) {
            $validated['code'] = strtoupper(trim($validated['code']));
        }

        $voucher->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Voucher berhasil diperbarui.',
            'data' => $voucher->fresh()->load('campaign'),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $voucher = Voucher::whereHas('campaign', fn ($q) => $q->where('merchant_id', $this->merchant($request)->id))
            ->findOrFail($id);

        $voucher->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Voucher berhasil dihapus.',
        ]);
    }
}