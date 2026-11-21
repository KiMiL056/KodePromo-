<?php

namespace Database\Seeders;

use App\Models\Campaign;
use App\Models\Merchant;
use App\Models\Voucher;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $merchant = Merchant::updateOrCreate(
            ['api_key' => 'kp_demo_merchant_key_2026'],
            [
                'name' => 'Toko Demo KodePromo',
                'is_active' => true,
            ]
        );

        $activeCampaign = Campaign::updateOrCreate(
            ['merchant_id' => $merchant->id, 'name' => 'Flash Sale Komputasi Awan 2026'],
            [
                'description' => 'Kampanye promo aktif untuk pengujian validasi dan redeem voucher.',
                'starts_at' => now()->subDays(1),
                'ends_at' => now()->addDays(30),
                'is_active' => true,
            ]
        );

        $expiredCampaign = Campaign::updateOrCreate(
            ['merchant_id' => $merchant->id, 'name' => 'Promo Lampau 2026'],
            [
                'description' => 'Kampanye kedaluwarsa untuk skenario pengujian negatif.',
                'starts_at' => now()->subDays(30),
                'ends_at' => now()->subDays(5),
                'is_active' => true,
            ]
        );

        Voucher::updateOrCreate(
            ['code' => 'HEMAT20'],
            [
                'campaign_id' => $activeCampaign->id,
                'discount_type' => 'percentage',
                'discount_value' => 20,
                'max_discount' => 30000,
                'min_order_amount' => 100000,
                'quota_total' => 100,
                'quota_used' => 0,
                'max_uses_per_user' => 2,
                'is_active' => true,
            ]
        );

        Voucher::updateOrCreate(
            ['code' => 'POTONG50RB'],
            [
                'campaign_id' => $activeCampaign->id,
                'discount_type' => 'fixed',
                'discount_value' => 50000,
                'max_discount' => null,
                'min_order_amount' => 200000,
                'quota_total' => 50,
                'quota_used' => 0,
                'max_uses_per_user' => 1,
                'is_active' => true,
            ]
        );

        Voucher::updateOrCreate(
            ['code' => 'HABIS10'],
            [
                'campaign_id' => $activeCampaign->id,
                'discount_type' => 'percentage',
                'discount_value' => 10,
                'max_discount' => 15000,
                'min_order_amount' => 50000,
                'quota_total' => 1,
                'quota_used' => 1,
                'max_uses_per_user' => 1,
                'is_active' => true,
            ]
        );

        Voucher::updateOrCreate(
            ['code' => 'KADALUARSA'],
            [
                'campaign_id' => $expiredCampaign->id,
                'discount_type' => 'percentage',
                'discount_value' => 15,
                'max_discount' => 25000,
                'min_order_amount' => 50000,
                'quota_total' => 20,
                'quota_used' => 0,
                'max_uses_per_user' => 1,
                'is_active' => true,
            ]
        );
    }
}
