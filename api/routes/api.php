<?php

use App\Http\Controllers\Api\V1\CampaignController;
use App\Http\Controllers\Api\V1\VoucherController;
use App\Http\Controllers\Api\V1\VoucherRedemptionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('api.key')->group(function () {
    Route::get('redemptions', [VoucherRedemptionController::class, 'index']);
    Route::post('vouchers/validate', [VoucherRedemptionController::class, 'validateCode']);
    Route::post('vouchers/redeem', [VoucherRedemptionController::class, 'redeem']);

    Route::apiResource('campaigns', CampaignController::class);
    Route::get('campaigns/{id}/report', [CampaignController::class, 'report']);

    Route::apiResource('vouchers', VoucherController::class);
});