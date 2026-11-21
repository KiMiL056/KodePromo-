<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
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
        $merchantId = $this->merchant($request)->id;

        $query = Voucher::whereHas('campaign', function ($q) use ($merchantId) {
            $q->where('merchant_id', $merchantId);
        })->with('campaign:id,name,starts_at,ends_at,is_active');

        if ($request->filled('campaign_id')) {
            $query->where('campaign_id', (int) $request->query('campaign_id'));
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->latest()->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $merchantId = $this->merchant($request)->id;

        if ($request->has('code')) {
            $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);
        }

        $validated = $request->validate([
            'campaign_id' => [
                'required',
                'integer',
                Rule::exists('campaigns', 'id')->where('merchant_id', $merchantId),
            ],
            'code' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:vouchers,code'],
            'discount_type' => ['required', Rule::in(['percentage', 'fixed'])],
            'discount_value' => [
                'required',
                'numeric',
                'gt:0',
                Rule::when($request->input('discount_type') === 'percentage', ['lte:100']),
            ],
            'max_discount' => ['nullable', 'numeric', 'gt:0'],
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'quota_total' => ['nullable', 'integer', 'min:1'],
            'max_uses_per_user' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $voucher = Voucher::create([
            'campaign_id' => $validated['campaign_id'],
            'code' => $validated['code'],
            'discount_type' => $validated['discount_type'],
            'discount_value' => $validated['discount_value'],
            'max_discount' => $validated['discount_type'] === 'percentage'
                ? ($validated['max_discount'] ?? null)
                : null,
            'min_order_amount' => $validated['min_order_amount'] ?? 0,
            'quota_total' => $validated['quota_total'] ?? null,
            'quota_used' => 0,
            'max_uses_per_user' => $validated['max_uses_per_user'] ?? 1,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Voucher berhasil dibuat.',
            'data' => $voucher->load('campaign:id,name'),
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $merchantId = $this->merchant($request)->id;

        $voucher = Voucher::whereHas('campaign', function ($q) use ($merchantId) {
            $q->where('merchant_id', $merchantId);
        })->with('campaign:id,name,starts_at,ends_at,is_active')->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $voucher,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $merchantId = $this->merchant($request)->id;

        $voucher = Voucher::whereHas('campaign', function ($q) use ($merchantId) {
            $q->where('merchant_id', $merchantId);
        })->findOrFail($id);

        if ($request->has('code')) {
            $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);
        }

        $effectiveType = $request->input('discount_type', $voucher->discount_type);

        $validated = $request->validate([
            'campaign_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('campaigns', 'id')->where('merchant_id', $merchantId),
            ],
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                'alpha_dash',
                Rule::unique('vouchers', 'code')->ignore($voucher->id),
            ],
            'discount_type' => ['sometimes', 'required', Rule::in(['percentage', 'fixed'])],
            'discount_value' => [
                'sometimes',
                'required',
                'numeric',
                'gt:0',
                Rule::when($effectiveType === 'percentage', ['lte:100']),
            ],
            'max_discount' => ['nullable', 'numeric', 'gt:0'],
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'quota_total' => ['nullable', 'integer', 'min:' . max(1, $voucher->quota_used)],
            'max_uses_per_user' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $voucher->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Voucher berhasil diperbarui.',
            'data' => $voucher->fresh('campaign:id,name'),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $merchantId = $this->merchant($request)->id;

        $voucher = Voucher::whereHas('campaign', function ($q) use ($merchantId) {
            $q->where('merchant_id', $merchantId);
        })->findOrFail($id);

        $voucher->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Voucher berhasil dihapus.',
        ]);
    }
}