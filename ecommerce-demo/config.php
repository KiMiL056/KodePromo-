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

function parseWibDateTime(?string $dateStr): ?DateTimeImmutable
{
    if (!$dateStr) {
        return null;
    }
    $wib = new DateTimeZone('Asia/Jakarta');
    $trimmed = trim($dateStr);

    // Jika input dari <input type="datetime-local"> (tanpa timezone), anggap langsung WIB (+07:00)
    if (preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}(:\d{2})?$/', $trimmed)) {
        $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', str_replace('T', ' ', $trimmed) . (strlen($trimmed) <= 16 ? ':00' : ''), $wib);
        return $dt ? $dt->setTimezone($wib) : null;
    }

    // Jika string dari API sudah memiliki timezone/ISO (Z atau +07:00), parse lalu paksa ke WIB
    try {
        return (new DateTimeImmutable($trimmed))->setTimezone($wib);
    } catch (Throwable $e) {
        return null;
    }
}

function toIsoWib(string $datetimeLocal): string
{
    $dt = parseWibDateTime($datetimeLocal) ?? new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta'));
    return $dt->format('Y-m-d\TH:i:sP');
}

function toDatetimeLocal(?string $dateStr): string
{
    $dt = parseWibDateTime($dateStr);
    return $dt ? $dt->format('Y-m-d\TH:i') : '';
}

function formatWibDisplay(?string $dateStr, string $format = 'd M Y H:i'): string
{
    $dt = parseWibDateTime($dateStr);
    return $dt ? $dt->format($format) . ' WIB' : '-';
}