<?php

use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'service' => 'KodePromo REST API',
        'version' => 'v1',
        'health_check' => url('/health'),
    ]);
});

Route::get('/health', HealthController::class);