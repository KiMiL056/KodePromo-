<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        try {
            DB::select('SELECT 1');

            return response()->json([
                'status' => 'ok',
                'service' => 'kodepromo-api',
                'database' => 'connected',
                'timestamp' => now()->toIso8601String(),
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'degraded',
                'service' => 'kodepromo-api',
                'database' => 'disconnected',
                'message' => $e->getMessage(),
                'timestamp' => now()->toIso8601String(),
            ], 503);
        }
    }
}