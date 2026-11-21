<?php
require_once __DIR__ . '/config.php';

$products = [
    'p1' => ['name' => 'Mechanical Keyboard Wireless 75%', 'price' => 450000, 'tag' => 'Elektronik'],
    'p2' => ['name' => 'Mouse Gaming Ergonomic RGB', 'price' => 180000, 'tag' => 'Aksesoris'],
    'p3' => ['name' => 'USB-C Hub 7-in-1 Aluminum', 'price' => 120000, 'tag' => 'Aksesoris'],
    'p4' => ['name' => 'Kabel Braided Fast Charging 2M', 'price' => 45000, 'tag' => 'Hemat'],
];

// Kelola identitas customer (ID Akun + Nama) di session
if (!isset($_SESSION['customer'])) {
    $_SESSION['customer'] = ['id' => 'CUST-01', 'name' => 'Mubarak Subkhi'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedId = strtoupper(trim((string) ($_POST['customer_id'] ?? $_SESSION['customer']['id'])));
    $postedName = trim((string) ($_POST['customer_name'] ?? $_SESSION['customer']['name']));
    if ($postedId !== '' && $postedName !== '') {
        $_SESSION['customer'] = ['id' => $postedId, 'name' => $postedName];
    }
}

$customerId = $_SESSION['customer']['id'];
$customerName = $_SESSION['customer']['name'];
$userIdentifier = $customerId . ' - ' . $customerName;

$selectedQuantities = $_POST['qty'] ?? ['p1' => 0, 'p2' => 1, 'p3' => 0, 'p4' => 0];
$voucherCode = strtoupper(trim((string) ($_POST['voucher_code'] ?? '')));
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action && $action !== 'switch_customer') {
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
$availableVouchers = $vouchersList['body']['data'] ?? [];

// Ambil riwayat redeem milik akun customer aktif untuk mengunci voucher yang sudah dipakai 1x
$userRedemptionsResp = callKodePromoApi(
    'GET',
    $apiBaseUrl . '/api/v1/redemptions?user_identifier=' . urlencode($userIdentifier),
    $apiKey
);
$userRedemptions = $userRedemptionsResp['body']['data'] ?? [];
$usedVoucherCounts = [];
foreach ($userRedemptions as $red) {
    $c = strtoupper((string) ($red['voucher']['code'] ?? ''));
    if ($c !== '') {
        $usedVoucherCounts[$c] = ($usedVoucherCounts[$c] ?? 0) + 1;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KodePromo Store — Halaman Customer (Checkout)</title>
    <style>
        :root { --bg: #0f172a; --card: #1e293b; --border: #334155; --text: #f8fafc; --muted: #94a3b8; --primary: #3b82f6; --success: #10b981; --danger: #ef4444; --warn: #f59e0b; }
        * { box-sizing: border-box; font-family: 'Segoe UI', system-ui, sans-serif; }
        body { margin: 0; background: var(--bg); color: var(--text); padding: 24px; }
        .container { max-width: 1160px; margin: 0 auto; }
        .navbar { display: flex; justify-content: space-between; align-items: center; background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 14px 22px; margin-bottom: 22px; flex-wrap: wrap; gap: 12px; }
        .nav-links { display: flex; gap: 10px; align-items: center; }
        .nav-tab { text-decoration: none; padding: 8px 14px; border-radius: 8px; font-size: 0.9rem; font-weight: 600; color: var(--muted); border: 1px solid transparent; }
        .nav-tab.active { background: rgba(59,130,246,0.18); color: #60a5fa; border-color: #2563eb; }
        .nav-tab:hover:not(.active) { color: var(--text); background: #0f172a; }
        .badge { padding: 6px 12px; border-radius: 999px; font-size: 0.8rem; font-weight: 600; }
        .badge-ok { background: rgba(16,185,129,0.18); color: #34d399; border: 1px solid #059669; }
        .badge-err { background: rgba(239,68,68,0.18); color: #f87171; border: 1px solid #dc2626; }
        .grid { display: grid; grid-template-columns: 1.05fr 0.95fr; gap: 20px; }
        @media (max-width: 920px) { .grid { grid-template-columns: 1fr; } }
        .card { background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 20px; margin-bottom: 20px; }
        .card h2 { margin-top: 0; font-size: 1.05rem; border-bottom: 1px solid var(--border); padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; font-size: 0.92rem; }
        th, td { text-align: left; padding: 11px 8px; border-bottom: 1px solid var(--border); }
        th { color: var(--muted); font-size: 0.78rem; text-transform: uppercase; }
        input[type="number"], input[type="text"], select { background: #0f172a; border: 1px solid var(--border); color: var(--text); padding: 9px 11px; border-radius: 8px; width: 100%; }
        input[type="number"] { width: 74px; text-align: center; font-weight: 600; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px; }
        .form-row-3 { display: grid; grid-template-columns: 0.9fr 1.3fr 0.8fr; gap: 10px; align-items: end; margin-bottom: 12px; }
        label { display: block; font-size: 0.8rem; color: var(--muted); margin-bottom: 5px; }
        .preset-bar { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 12px; }
        .preset-pill { background: #0f172a; border: 1px solid var(--border); color: #cbd5e1; padding: 5px 10px; border-radius: 6px; font-size: 0.78rem; cursor: pointer; }
        .preset-pill:hover { border-color: var(--primary); color: #fff; }
        .voucher-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin: 10px 0 14px; }
        .voucher-btn { background: #0f172a; border: 1px solid var(--border); color: var(--text); padding: 10px 12px; border-radius: 10px; text-align: left; cursor: pointer; transition: 0.15s; display: flex; flex-direction: column; gap: 3px; }
        .voucher-btn:hover:not(:disabled) { border-color: var(--primary); background: #172554; }
        .voucher-btn.selected { border-color: #34d399; background: rgba(16,185,129,0.12); }
        .voucher-btn:disabled { opacity: 0.45; cursor: not-allowed; background: #090d16; border-style: dashed; }
        .voucher-btn .v-code { font-weight: 700; font-size: 0.92rem; color: #38bdf8; }
        .voucher-btn:disabled .v-code { color: #64748b; }
        .voucher-btn .v-desc { font-size: 0.77rem; color: #cbd5e1; }
        .voucher-btn .v-date { font-size: 0.72rem; color: #94a3b8; }
        .voucher-btn .v-status { font-size: 0.74rem; font-weight: 600; margin-top: 3px; }
        .btn-group { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 14px; }
        button.action-btn { border: none; padding: 10px 14px; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 0.88rem; flex: 1; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-success { background: var(--success); color: #052e16; }
        .btn-warn { background: var(--warn); color: #451a03; width: 100%; }
        .btn-secondary { background: #334155; color: #f8fafc; padding: 9px 12px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 0.82rem; }
        .summary-box { background: #0f172a; border: 1px solid var(--border); border-radius: 10px; padding: 14px; margin-top: 14px; }
        .summary-line { display: flex; justify-content: space-between; margin: 6px 0; font-size: 0.95rem; }
        .summary-total { border-top: 1px dashed var(--border); padding-top: 8px; font-weight: 700; font-size: 1.12rem; color: #38bdf8; }
        .alert { padding: 12px 14px; border-radius: 8px; margin-top: 14px; font-size: 0.88rem; }
        .alert-ok { background: rgba(16,185,129,0.15); border: 1px solid #059669; color: #6ee7b7; }
        .alert-err { background: rgba(239,68,68,0.15); border: 1px solid #dc2626; color: #fca5a5; }
        details { margin-top: 12px; background: #020617; border: 1px solid var(--border); border-radius: 8px; padding: 10px 12px; }
        summary { cursor: pointer; font-size: 0.8rem; color: var(--muted); font-weight: 600; }
        pre { margin: 10px 0 0; overflow-x: auto; font-size: 0.78rem; color: #a5f3fc; }
    </style>
</head>
<body>
<div class="container">
    <div class="navbar">
        <div>
            <strong style="font-size:1.15rem;">KodePromo Store</strong>
            <div style="font-size:0.78rem; color:var(--muted);">Simulasi E-Commerce Customer Checkout</div>
        </div>
        <div class="nav-links">
            <a href="index.php" class="nav-tab active">Halaman Customer (Checkout)</a>
            <a href="monitor.php" class="nav-tab">Portal Admin (CRUD & Laporan)</a>
        </div>
        <div>
            <?php if (($healthCheck['body']['status'] ?? '') === 'ok'): ?>
                <span class="badge badge-ok">API Connected</span>
            <?php else: ?>
                <span class="badge badge-err">API Disconnected</span>
            <?php endif; ?>
        </div>
    </div>

    <form method="POST" class="grid" id="checkoutForm">
        <div>
            <div class="card">
                <h2>1. Identitas Akun Customer (Deteksi Batas 1x Pakai)</h2>
                <div class="preset-bar">
                    <span style="font-size:0.78rem; color:var(--muted); align-self:center;">Akun Cepat:</span>
                    <?php foreach ($presetCustomers as $pId => $pName): ?>
                        <button type="button" class="preset-pill" onclick="setCustomer('<?= $pId ?>', '<?= htmlspecialchars($pName) ?>')">
                            <?= $pId ?> (<?= htmlspecialchars($pName) ?>)
                        </button>
                    <?php endforeach; ?>
                </div>
                <div class="form-row-3">
                    <div>
                        <label>ID Akun Customer</label>
                        <input type="text" id="customer_id" name="customer_id" value="<?= htmlspecialchars($customerId) ?>" required>
                    </div>
                    <div>
                        <label>Nama Customer</label>
                        <input type="text" id="customer_name" name="customer_name" value="<?= htmlspecialchars($customerName) ?>" required>
                    </div>
                    <div>
                        <button type="submit" name="action" value="switch_customer" class="btn-secondary" style="width:100%;">Muat Status Akun</button>
                    </div>
                </div>
                <div style="font-size:0.8rem; color:var(--muted);">
                    Identitas dikirim ke API: <strong style="color:#38bdf8;"><?= htmlspecialchars($userIdentifier) ?></strong>
                    (Sudah menukarkan <strong><?= count($userRedemptions) ?></strong> voucher)
                </div>
            </div>

            <div class="card">
                <h2>2. Keranjang Belanja</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Produk</th>
                            <th>Harga Satuan</th>
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
                                <input
                                    type="number"
                                    class="qty-input"
                                    data-price="<?= (int) $item['price'] ?>"
                                    name="qty[<?= $id ?>]"
                                    min="0"
                                    max="20"
                                    value="<?= (int) $selectedQuantities[$id] ?>"
                                >
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="summary-box">
                    <div class="summary-line">
                        <span>Subtotal Belanja Saat Ini:</span>
                        <strong id="liveSubtotalText"><?= rupiah($orderAmount) ?></strong>
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
            </div>
        </div>

        <div class="card">
            <h2>3. Pilih Promo & Pembayaran (Maks. 1 Voucher / Order)</h2>

            <div style="margin-bottom:12px;">
                <label>Order ID Transaksi (BR-08 & Larangan Gabung Voucher)</label>
                <input type="text" name="order_id" value="<?= htmlspecialchars($orderId) ?>" required>
            </div>

            <label>Daftar Voucher (Otomatis Terkunci Jika Sudah Dipakai 1x, Expired, Kuota Habis, atau Kurang Min. Belanja)</label>
            <div class="voucher-grid" id="voucherGrid">
                <?php
                $nowTs = time();
                foreach ($availableVouchers as $v):
                    $code = strtoupper((string) $v['code']);
                    $minOrder = (float) $v['min_order_amount'];
                    $maxPerUser = (int) ($v['max_uses_per_user'] ?? 1);
                    $usedByThisUser = (int) ($usedVoucherCounts[$code] ?? 0);
                    $startsAt = $v['campaign']['starts_at'] ?? null;
                    $endsAt = $v['campaign']['ends_at'] ?? null;

                    $discLabel = $v['discount_type'] === 'percentage'
                        ? ('Diskon ' . $v['discount_value'] . '%' . ($v['max_discount'] ? ' (maks ' . rupiah($v['max_discount']) . ')' : ''))
                        : ('Potongan ' . rupiah($v['discount_value']));

                    $hardLockReason = '';
                    if ($usedByThisUser >= $maxPerUser) {
                        $hardLockReason = "Sudah Dipakai oleh {$customerId} (Batas {$maxPerUser}x/Akun)";
                    } elseif (empty($v['is_active']) || empty($v['campaign']['is_active'])) {
                        $hardLockReason = 'Voucher / Kampanye Nonaktif';
                    } elseif ($startsAt && strtotime($startsAt) > $nowTs) {
                        $hardLockReason = 'Belum Mulai (' . date('d M Y', strtotime($startsAt)) . ')';
                    } elseif ($endsAt && strtotime($endsAt) < $nowTs) {
                        $hardLockReason = 'Kadaluarsa / Expired';
                    } elseif ($v['quota_total'] !== null && (int) $v['quota_used'] >= (int) $v['quota_total']) {
                        $hardLockReason = 'Kuota Voucher Habis';
                    }

                    $isEligible = ($hardLockReason === '' && $orderAmount >= $minOrder);
                    $isSelected = ($voucherCode === $code && $isEligible);
                ?>
                    <button
                        type="button"
                        class="voucher-btn <?= $isSelected ? 'selected' : '' ?>"
                        data-code="<?= htmlspecialchars($code) ?>"
                        data-min-order="<?= $minOrder ?>"
                        data-hard-lock="<?= htmlspecialchars($hardLockReason) ?>"
                        <?= $isEligible ? '' : 'disabled' ?>
                    >
                        <span class="v-code"><?= htmlspecialchars($code) ?></span>
                        <span class="v-desc"><?= htmlspecialchars($discLabel) ?></span>
                        <span class="v-desc">Min. belanja: <?= rupiah($minOrder) ?> | Kuota: <?= (int) $v['quota_used'] ?>/<?= $v['quota_total'] ?? '∞' ?></span>
                        <?php if ($startsAt && $endsAt): ?>
                            <span class="v-date">Berlaku: <?= date('d M Y', strtotime($startsAt)) ?> — <?= date('d M Y', strtotime($endsAt)) ?></span>
                        <?php endif; ?>
                        <span class="v-status" style="color: <?= $isEligible ? '#34d399' : '#f87171' ?>;">
                            <?php if ($hardLockReason !== ''): ?>
                                Terkunci — <?= htmlspecialchars($hardLockReason) ?>
                            <?php elseif ($orderAmount < $minOrder): ?>
                                Terkunci — Kurang <?= rupiah($minOrder - $orderAmount) ?>
                            <?php else: ?>
                                Memenuhi syarat (Klik untuk pilih)
                            <?php endif; ?>
                        </span>
                    </button>
                <?php endforeach; ?>
            </div>

            <div>
                <label>Kode Voucher Terpilih (atau Ketik Manual untuk Menguji Validasi Error API)</label>
                <input type="text" id="voucher_code" name="voucher_code" value="<?= htmlspecialchars($voucherCode) ?>" placeholder="Pilih voucher di atas atau ketik kode...">
            </div>

            <div class="btn-group">
                <button type="submit" name="action" value="validate" class="action-btn btn-primary">Cek / Validasi Voucher</button>
                <button type="submit" name="action" value="redeem" class="action-btn btn-success">Checkout & Redeem</button>
            </div>

            <?php if (!empty($_SESSION['last_redeem'])): ?>
                <div class="btn-group" style="margin-top:10px;">
                    <button type="submit" name="action" value="retry_idempotent" class="action-btn btn-warn">Simulasi Retry (Idempotency-Key Sama — BR-08)</button>
                </div>
            <?php endif; ?>

            <?php if ($apiResult !== null): ?>
                <?php $isSuccess = ($apiResult['body']['status'] ?? '') === 'success'; ?>
                <div class="alert <?= $isSuccess ? 'alert-ok' : 'alert-err' ?>">
                    <strong>HTTP <?= $apiResult['http_code'] ?></strong> — <?= htmlspecialchars($apiResult['body']['message'] ?? 'Respons diterima') ?>
                    <?php if (!empty($apiResult['body']['idempotent_replay'])): ?>
                        <br><small><strong>[BR-08 Idempotent Replay]</strong> Kuota tidak terpotong dua kali.</small>
                    <?php endif; ?>
                </div>
                <details open>
                    <summary>Lihat Payload Respons JSON API (<?= htmlspecialchars((string) $lastEndpoint) ?>)</summary>
                    <pre><?= htmlspecialchars(json_encode($apiResult['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre>
                </details>
            <?php endif; ?>
        </div>
    </form>
</div>

<script>
function setCustomer(id, name) {
    document.getElementById('customer_id').value = id;
    document.getElementById('customer_name').value = name;
    const form = document.getElementById('checkoutForm');
    const hidden = document.createElement('input');
    hidden.type = 'hidden';
    hidden.name = 'action';
    hidden.value = 'switch_customer';
    form.appendChild(hidden);
    form.submit();
}

(function () {
    const qtyInputs = document.querySelectorAll('.qty-input');
    const subtotalEl = document.getElementById('liveSubtotalText');
    const voucherBtns = document.querySelectorAll('.voucher-btn');
    const voucherInput = document.getElementById('voucher_code');

    function formatRupiah(angka) {
        return 'Rp ' + Math.round(angka).toLocaleString('id-ID');
    }

    function updateCartAndVouchers() {
        let subtotal = 0;
        qtyInputs.forEach(input => {
            const qty = Math.max(0, parseInt(input.value || '0', 10));
            const price = parseFloat(input.dataset.price || '0');
            subtotal += qty * price;
        });

        subtotalEl.textContent = formatRupiah(subtotal);

        voucherBtns.forEach(btn => {
            const minOrder = parseFloat(btn.dataset.minOrder || '0');
            const hardLock = btn.dataset.hardLock || '';
            const code = btn.dataset.code;
            const statusEl = btn.querySelector('.v-status');

            if (hardLock !== '') {
                btn.disabled = true;
                btn.classList.remove('selected');
                statusEl.textContent = 'Terkunci — ' + hardLock;
                statusEl.style.color = '#f87171';
                return;
            }

            if (subtotal >= minOrder) {
                btn.disabled = false;
                statusEl.textContent = 'Memenuhi syarat (Klik untuk pilih)';
                statusEl.style.color = '#34d399';
            } else {
                btn.disabled = true;
                btn.classList.remove('selected');
                statusEl.textContent = 'Terkunci — Kurang ' + formatRupiah(minOrder - subtotal);
                statusEl.style.color = '#f87171';

                if (voucherInput.value.toUpperCase() === code) {
                    voucherInput.value = '';
                }
            }
        });
    }

    voucherBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            if (btn.disabled) return;
            voucherBtns.forEach(b => b.classList.remove('selected'));
            btn.classList.add('selected');
            voucherInput.value = btn.dataset.code;
        });
    });

    qtyInputs.forEach(input => {
        input.addEventListener('input', updateCartAndVouchers);
        input.addEventListener('change', updateCartAndVouchers);
    });

    updateCartAndVouchers();
})();
</script>
</body>
</html>