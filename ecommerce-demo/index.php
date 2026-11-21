<?php
session_start();

$apiBaseUrl = rtrim(getenv('API_BASE_URL') ?: 'http://127.0.0.1:8000', '/');
$apiKey = getenv('API_KEY') ?: 'kp_demo_merchant_key_2026';

$products = [
    'p1' => ['name' => 'Mechanical Keyboard Wireless 75%', 'price' => 450000, 'tag' => 'Elektronik'],
    'p2' => ['name' => 'Mouse Gaming Ergonomic RGB', 'price' => 180000, 'tag' => 'Aksesoris'],
    'p3' => ['name' => 'USB-C Hub 7-in-1 Aluminum', 'price' => 120000, 'tag' => 'Aksesoris'],
    'p4' => ['name' => 'Kabel Braided Fast Charging 2M', 'price' => 45000, 'tag' => 'Hemat'],
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

// Inisialisasi state default form
$selectedQuantities = $_POST['qty'] ?? ['p1' => 0, 'p2' => 1, 'p3' => 1, 'p4' => 0];
$voucherCode = strtoupper(trim((string) ($_POST['voucher_code'] ?? 'HEMAT20')));
$userIdentifier = trim((string) ($_POST['user_identifier'] ?? 'user_01'));
$orderId = trim((string) ($_POST['order_id'] ?? ('ORD-' . date('His'))));
$action = $_POST['action'] ?? null;

$orderAmount = 0;
foreach ($products as $id => $item) {
    $qty = max(0, (int) ($selectedQuantities[$id] ?? 0));
    $selectedQuantities[$id] = $qty;
    $orderAmount += $item['price'] * $qty;
}

$apiResult = null;
$lastEndpoint = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action) {
    if ($action === 'validate') {
        $lastEndpoint = 'POST /api/v1/vouchers/validate';
        $apiResult = callKodePromoApi('POST', $apiBaseUrl . '/api/v1/vouchers/validate', $apiKey, [
            'code' => $voucherCode,
            'order_amount' => $orderAmount,
            'user_identifier' => $userIdentifier,
        ]);
    } elseif ($action === 'redeem') {
        $idemKey = 'idem-' . $orderId . '-' . bin2hex(random_bytes(3));
        $_SESSION['last_redeem'] = [
            'code' => $voucherCode,
            'order_id' => $orderId,
            'order_amount' => $orderAmount,
            'user_identifier' => $userIdentifier,
            'idempotency_key' => $idemKey,
        ];
        $lastEndpoint = "POST /api/v1/vouchers/redeem (Idempotency-Key: {$idemKey})";
        $apiResult = callKodePromoApi('POST', $apiBaseUrl . '/api/v1/vouchers/redeem', $apiKey, [
            'code' => $voucherCode,
            'order_id' => $orderId,
            'order_amount' => $orderAmount,
            'user_identifier' => $userIdentifier,
        ], $idemKey);
    } elseif ($action === 'retry_idempotent' && !empty($_SESSION['last_redeem'])) {
        $last = $_SESSION['last_redeem'];
        $voucherCode = $last['code'];
        $orderId = $last['order_id'];
        $orderAmount = $last['order_amount'];
        $userIdentifier = $last['user_identifier'];
        $idemKey = $last['idempotency_key'];

        $lastEndpoint = "RETRY POST /api/v1/vouchers/redeem (Idempotency-Key: {$idemKey})";
        $apiResult = callKodePromoApi('POST', $apiBaseUrl . '/api/v1/vouchers/redeem', $apiKey, [
            'code' => $voucherCode,
            'order_id' => $orderId,
            'order_amount' => $orderAmount,
            'user_identifier' => $userIdentifier,
        ], $idemKey);
    }
}

$healthCheck = callKodePromoApi('GET', $apiBaseUrl . '/health', $apiKey);
$vouchersList = callKodePromoApi('GET', $apiBaseUrl . '/api/v1/vouchers', $apiKey);

function rupiah(float|int $amount): string
{
    return 'Rp ' . number_format($amount, 0, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KodePromo Store — E-Commerce Demo Client</title>
    <style>
        :root { --bg: #0f172a; --card: #1e293b; --border: #334155; --text: #f8fafc; --muted: #94a3b8; --primary: #3b82f6; --success: #10b981; --danger: #ef4444; --warn: #f59e0b; }
        * { box-sizing: border-box; font-family: 'Segoe UI', system-ui, sans-serif; }
        body { margin: 0; background: var(--bg); color: var(--text); padding: 24px; }
        .container { max-width: 1180px; margin: 0 auto; }
        .topbar { display: flex; justify-content: space-between; align-items: center; background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 16px 24px; margin-bottom: 24px; flex-wrap: wrap; gap: 12px; }
        .topbar h1 { margin: 0; font-size: 1.3rem; }
        .topbar small { color: var(--muted); display: block; margin-top: 4px; }
        .badge { padding: 6px 12px; border-radius: 999px; font-size: 0.82rem; font-weight: 600; }
        .badge-ok { background: rgba(16,185,129,0.18); color: #34d399; border: 1px solid #059669; }
        .badge-err { background: rgba(239,68,68,0.18); color: #f87171; border: 1px solid #dc2626; }
        .grid { display: grid; grid-template-columns: 1.15fr 0.85fr; gap: 20px; }
        @media (max-width: 900px) { .grid { grid-template-columns: 1fr; } }
        .card { background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 20px; margin-bottom: 20px; }
        .card h2 { margin-top: 0; font-size: 1.05rem; border-bottom: 1px solid var(--border); padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        th, td { text-align: left; padding: 10px 8px; border-bottom: 1px solid var(--border); }
        th { color: var(--muted); font-weight: 600; font-size: 0.8rem; text-transform: uppercase; }
        input[type="number"], input[type="text"] { background: #0f172a; border: 1px solid var(--border); color: var(--text); padding: 8px 10px; border-radius: 8px; width: 100%; }
        input[type="number"] { width: 72px; text-align: center; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 14px; }
        label { display: block; font-size: 0.82rem; color: var(--muted); margin-bottom: 5px; }
        .pills { display: flex; gap: 8px; flex-wrap: wrap; margin: 10px 0 14px; }
        .pill { background: #0f172a; border: 1px solid var(--border); color: #cbd5e1; padding: 4px 10px; border-radius: 6px; font-size: 0.78rem; cursor: pointer; }
        .pill:hover { border-color: var(--primary); color: #fff; }
        .btn-group { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 16px; }
        button { border: none; padding: 10px 16px; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 0.9rem; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-success { background: var(--success); color: #052e16; }
        .btn-warn { background: var(--warn); color: #451a03; }
        .summary-box { background: #0f172a; border: 1px solid var(--border); border-radius: 10px; padding: 14px; margin-top: 16px; }
        .summary-line { display: flex; justify-content: space-between; margin: 6px 0; font-size: 0.95rem; }
        .summary-total { border-top: 1px dashed var(--border); padding-top: 8px; font-weight: 700; font-size: 1.1rem; color: #38bdf8; }
        pre { background: #020617; border: 1px solid var(--border); padding: 14px; border-radius: 8px; overflow-x: auto; font-size: 0.82rem; color: #a5f3fc; max-height: 310px; }
        .alert { padding: 12px 14px; border-radius: 8px; margin-bottom: 12px; font-size: 0.9rem; }
        .alert-ok { background: rgba(16,185,129,0.15); border: 1px solid #059669; color: #6ee7b7; }
        .alert-err { background: rgba(239,68,68,0.15); border: 1px solid #dc2626; color: #fca5a5; }
    </style>
</head>
<body>
<div class="container">
    <div class="topbar">
        <div>
            <h1>KodePromo Store — E-Commerce Checkout Demo</h1>
            <small>Terhubung ke REST API: <strong><?= htmlspecialchars($apiBaseUrl) ?></strong></small>
        </div>
        <div>
            <?php if (($healthCheck['body']['status'] ?? '') === 'ok'): ?>
                <span class="badge badge-ok">API Connected (MySQL OK)</span>
            <?php else: ?>
                <span class="badge badge-err">API Disconnected</span>
            <?php endif; ?>
        </div>
    </div>

    <div class="grid">
        <div>
            <form method="POST" class="card">
                <h2>1. Keranjang Belanja & Pengujian Kode Promo</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Produk</th>
                            <th>Harga</th>
                            <th>Qty</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($products as $id => $item): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($item['name']) ?></strong><br>
                                <small style="color:var(--muted)"><?= htmlspecialchars($item['tag']) ?></small>
                            </td>
                            <td><?= rupiah($item['price']) ?></td>
                            <td>
                                <input type="number" name="qty[<?= $id ?>]" min="0" max="20" value="<?= (int) $selectedQuantities[$id] ?>">
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="form-row">
                    <div>
                        <label>User Identifier (BR-05)</label>
                        <input type="text" name="user_identifier" value="<?= htmlspecialchars($userIdentifier) ?>" required>
                    </div>
                    <div>
                        <label>Order ID (BR-08)</label>
                        <input type="text" name="order_id" value="<?= htmlspecialchars($orderId) ?>" required>
                    </div>
                </div>

                <div style="margin-top: 14px;">
                    <label>Kode Voucher Promo</label>
                    <input type="text" id="voucher_code" name="voucher_code" value="<?= htmlspecialchars($voucherCode) ?>" placeholder="Contoh: HEMAT20" required>
                    <div class="pills">
                        <span class="pill" onclick="document.getElementById('voucher_code').value='HEMAT20'">HEMAT20 (20% maks 30rb, min 100rb)</span>
                        <span class="pill" onclick="document.getElementById('voucher_code').value='POTONG50RB'">POTONG50RB (Fixed 50rb, min 200rb)</span>
                        <span class="pill" onclick="document.getElementById('voucher_code').value='HABIS10'">HABIS10 (Kuota Habis)</span>
                        <span class="pill" onclick="document.getElementById('voucher_code').value='KADALUARSA'">KADALUARSA (Expired)</span>
                    </div>
                </div>

                <div class="summary-box">
                    <div class="summary-line">
                        <span>Subtotal Belanja:</span>
                        <strong><?= rupiah($orderAmount) ?></strong>
                    </div>
                    <?php
                    $calc = $apiResult['body']['data']['calculation'] ?? $apiResult['body']['data'] ?? null;
                    if (is_array($calc) && isset($calc['discount_amount'])):
                    ?>
                        <div class="summary-line" style="color:#34d399;">
                            <span>Potongan Voucher (<?= htmlspecialchars($voucherCode) ?>):</span>
                            <strong>- <?= rupiah($calc['discount_amount']) ?></strong>
                        </div>
                        <div class="summary-line summary-total">
                            <span>Total Akhir Bayar:</span>
                            <span><?= rupiah($calc['final_amount']) ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="btn-group">
                    <button type="submit" name="action" value="validate" class="btn-primary">Cek / Validasi Voucher</button>
                    <button type="submit" name="action" value="redeem" class="btn-success">Checkout & Redeem Voucher</button>
                    <?php if (!empty($_SESSION['last_redeem'])): ?>
                        <button type="submit" name="action" value="retry_idempotent" class="btn-warn">Simulasi Retry (Idempotency-Key Sama)</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div>
            <div class="card">
                <h2>2. Hasil Respons KodePromo REST API</h2>
                <?php if ($apiResult === null): ?>
                    <p style="color:var(--muted); font-size:0.9rem;">Pilih jumlah produk, pilih kode voucher di sebelah kiri, lalu klik <strong>Cek / Validasi Voucher</strong> atau <strong>Checkout & Redeem Voucher</strong> untuk melihat respons JSON dari API.</p>
                <?php else: ?>
                    <?php $isSuccess = ($apiResult['body']['status'] ?? '') === 'success'; ?>
                    <div class="alert <?= $isSuccess ? 'alert-ok' : 'alert-err' ?>">
                        <strong>HTTP <?= $apiResult['http_code'] ?></strong> — <?= htmlspecialchars($apiResult['body']['message'] ?? 'Respons diterima') ?>
                        <?php if (!empty($apiResult['body']['idempotent_replay'])): ?>
                            <br><small><strong>[BR-08 Idempotent Replay]</strong> Transaksi dikembalikan dari cache tanpa memotong kuota ulang.</small>
                        <?php endif; ?>
                    </div>
                    <small style="color:var(--muted); display:block; margin-bottom:6px;"><?= htmlspecialchars((string) $lastEndpoint) ?></small>
                    <pre><?= htmlspecialchars(json_encode($apiResult['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre>
                <?php endif; ?>
            </div>

            <div class="card">
                <h2>3. Monitor Kuota Voucher Aktif (Live dari API)</h2>
                <?php $vouchers = $vouchersList['body']['data'] ?? []; ?>
                <?php if (!empty($vouchers)): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Kode</th>
                                <th>Diskon</th>
                                <th>Min. Belanja</th>
                                <th>Kuota Terpakai</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($vouchers as $v): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($v['code']) ?></strong></td>
                                <td>
                                    <?= $v['discount_type'] === 'percentage'
                                        ? $v['discount_value'] . '%'
                                        : rupiah($v['discount_value']) ?>
                                </td>
                                <td><?= rupiah($v['min_order_amount']) ?></td>
                                <td>
                                    <strong><?= (int) $v['quota_used'] ?> / <?= $v['quota_total'] ?? '∞' ?></strong>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="color:var(--muted); font-size:0.9rem;">Data voucher belum tersedia. Pastikan server API menyala.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</body>
</html>