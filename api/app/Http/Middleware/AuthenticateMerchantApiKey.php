<?php

namespace App\Http\Middleware;

use App\Models\Merchant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateMerchantApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('X-API-Key');

        if (!$apiKey) {
            return response()->json([
                'status' => 'error',
                'code' => 'UNAUTHORIZED',
                'message' => 'Header X-API-Key wajib disertakan.',
            ], 401);
        }

        $merchant = Merchant::where('api_key', $apiKey)
            ->where('is_active', true)
            ->first();

        if (!$merchant) {
            return response()->json([
                'status' => 'error',
                'code' => 'UNAUTHORIZED',
                'message' => 'X-API-Key tidak valid atau merchant tidak aktif.',
            ], 401);
        }

        $request->attributes->set('merchant', $merchant);

        return $next($request);
    }
}