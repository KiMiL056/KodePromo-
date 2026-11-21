<?php
date_default_timezone_set('Asia/Jakarta');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$apiBaseUrl = rtrim(getenv('API_BASE_URL') ?: 'http://127.0.0.1:8000', '/');
$apiKey = getenv('API_KEY') ?: 'kp_demo_merchant_key_2026';

$presetCustomers = [
    'CUST-01' => 'Mubarak Subkhi',
    'CUST-02' => 'Muchel Munthe',
    'CUST-03' => 'Anjelli Tria',
];

function callKodePromoApi(string $method, string $url, string $apiKey, ?array $payload = null, ?string $idempotencyKey = null): array
{
    $ch = curl_init($url);
    $headers = [
        'Accept: application/json',
        'Content-Type: application/json',
        'X-API-Key: ' . $apiKey,
    ];

    if ($idempotencyKey) {
        $headers[] = 'Idempotency-Key: ' . $idempotencyKey;
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 8,
    ]);

    if ($payload !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    }

    $rawBody = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($rawBody === false) {
        return [
            'http_code' => 503,
            'body' => [
                'status' => 'error',
                'code' => 'API_UNREACHABLE',
                'message' => 'Gagal terhubung ke KodePromo API: ' . $curlError,
            ],
        ];
    }

    return [
        'http_code' => $httpCode,
        'body' => json_decode($rawBody, true) ?? ['raw' => $rawBody],
    ];
}

function rupiah(float|int $amount): string
{
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

function toIsoWib(string $datetimeLocal): string
{
    $dt = new DateTime($datetimeLocal, new DateTimeZone('Asia/Jakarta'));
    return $dt->format(DateTime::ATOM);
}

function toDatetimeLocal(?string $isoString): string
{
    if (!$isoString) {
        return '';
    }
    $dt = new DateTime($isoString);
    $dt->setTimezone(new DateTimeZone('Asia/Jakarta'));
    return $dt->format('Y-m-d\TH:i');
}