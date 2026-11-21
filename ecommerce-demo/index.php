<?php
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart']) || empty($_SESSION['cart'])) {
    $_SESSION['cart'] = [
        [
            'sku' => 'SKU-01',
            'name' => 'Kopi Arabika Gayo 250g',
            'price' => 85000,
            'qty' => 2,
        ],
        [
            'sku' => 'SKU-02',
            'name' => 'Tumbler Stainless Matte 500ml',
            'price' => 110000,
            'qty' => 1,
        ],
    ];
}

if (!isset($_SESSION['checkout_customer'])) {
    $_SESSION['checkout_customer'] = [
        'customer_id' => '',
        'customer_name' => '',
    ];
}

if (!isset($_SESSION['applied_voucher_code'])) {
    $_SESSION['applied_voucher_code'] = '';
}

$flashSuccess = null;
$flashError = null;
$lastOrder = $_SESSION['last_order'] ?? null;

function calculateCartSubtotal(array $cart): float
{
    $total = 0;
    foreach ($cart as $item) {
        $total += ((float) ($item['price'] ?? 0)) * ((int) ($item['qty'] ?? 0));
    }
    return $total;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_customer') {
        $customerId = trim((string) ($_POST['customer_id'] ?? ''));
        $customerName = trim((string) ($_POST['customer_name'] ?? ''));

        if ($customerId === '') {
            $flashError = 'ID Akun / Customer wajib diisi.';
        } else {
            $_SESSION['checkout_customer'] = [
                'customer_id' => $customerId,
                'customer_name' => $customerName !== '' ? $customerName : $customerId,
            ];
            $flashSuccess = 'Informasi akun disimpan untuk ' . $_SESSION['checkout_customer']['customer_name'] . ' (' . $customerId . ').';
        }
    } elseif ($action === 'update_qty') {
        $sku = (string) ($_POST['sku'] ?? '');
        $qty = (int) ($_POST['qty'] ?? 1);
        foreach ($_SESSION['cart'] as &$item) {
            if ($item['sku'] === $sku) {
                if ($qty <= 0) {
                    $item['qty'] = 1;
                } elseif ($qty > 20) {
                    $item['qty'] = 20;
                } else {
                    $item['qty'] = $qty;
                }
                break;
            }
        }
        unset($item);
        $flashSuccess = 'Jumlah item di keranjang diperbarui.';
    } elseif ($action === 'remove_item') {
        $sku = (string) ($_POST['sku'] ?? '');
        if (count($_SESSION['cart']) > 1) {
            $_SESSION['cart'] = array_values(array_filter(
                $_SESSION['cart'],
                fn($item) => ($item['sku'] ?? '') !== $sku
            ));
            $flashSuccess = 'Item dihapus dari keranjang.';
        } else {
            $flashError = 'Minimal harus ada 1 item di keranjang.';
        }
    } elseif ($action === 'reset_cart') {
        $_SESSION['cart'] = [
            [
                'sku' => 'SKU-01',
                'name' => 'Kopi Arabika Gayo 250g',
                'price' => 85000,
                'qty' => 2,
            ],
            [
                'sku' => 'SKU-02',
                'name' => 'Tumbler Stainless Matte 500ml',
                'price' => 110000,
                'qty' => 1,
            ],
        ];
        $_SESSION['applied_voucher_code'] = '';
        unset($_SESSION['last_order']);
        $lastOrder = null;
        $flashSuccess = 'Keranjang dikembalikan ke daftar awal.';
    } elseif ($action === 'apply_voucher') {
        $code = strtoupper(trim((string) ($_POST['voucher_code'] ?? '')));
        $customerId = trim((string) ($_POST['customer_id'] ?? ($_SESSION['checkout_customer']['customer_id'] ?? '')));
        $customerName = trim((string) ($_POST['customer_name'] ?? ($_SESSION['checkout_customer']['customer_name'] ?? '')));

        if ($customerId !== '') {
            $_SESSION['checkout_customer'] = [
                'customer_id' => $customerId,
                'customer_name' => $customerName !== '' ? $customerName : $customerId,
            ];
        }

        if ($code === '') {
            $flashError = 'Masukkan atau pilih kode voucher terlebih dahulu.';
        } elseif ($customerId === '') {
            $flashError = 'Masukkan ID akun customer terlebih dahulu sebelum menggunakan voucher.';
        } else {
            $subtotal = calculateCartSubtotal($_SESSION['cart']);
            $validateRes = callKodePromoApi('POST', $apiBaseUrl . '/api/v1/vouchers/validate', $apiKey, [
                'code' => $code,
                'order_amount' => $subtotal,
                'user_identifier' => $customerId,
                'customer_id' => $customerId,
                'order_total' => $subtotal,
            ]);

            $vBody = $validateRes['body'];
            $isValid = ($validateRes['http_code'] >= 200 && $validateRes['http_code'] < 300)
                && (($vBody['valid'] ?? false) === true || ($vBody['status'] ?? '') === 'valid' || ($vBody['status'] ?? '') === 'success' || isset($vBody['data']['discount_amount']) || isset($vBody['discount_amount']));

            if ($isValid) {
                $_SESSION['applied_voucher_code'] = $code;
                $flashSuccess = 'Voucher ' . $code . ' berhasil diterapkan ke pesanan Anda.';
            } else {
                $_SESSION['applied_voucher_code'] = '';
                $flashError = $vBody['message'] ?? ($vBody['reason'] ?? 'Voucher tidak dapat digunakan untuk pesanan ini.');
            }
        }
    } elseif ($action === 'clear_voucher') {
        $_SESSION['applied_voucher_code'] = '';
        $flashSuccess = 'Voucher dilepas dari pesanan.';
    } elseif ($action === 'checkout_redeem') {
        $customerId = trim((string) ($_POST['customer_id'] ?? ($_SESSION['checkout_customer']['customer_id'] ?? '')));
        $customerName = trim((string) ($_POST['customer_name'] ?? ($_SESSION['checkout_customer']['customer_name'] ?? '')));
        $voucherCode = strtoupper(trim((string) ($_POST['voucher_code'] ?? ($_SESSION['applied_voucher_code'] ?? ''))));

        if ($customerId === '') {
            $flashError = 'Masukkan ID akun customer terlebih dahulu untuk melanjutkan pembayaran.';
        } else {
            $_SESSION['checkout_customer'] = [
                'customer_id' => $customerId,
                'customer_name' => $customerName !== '' ? $customerName : $customerId,
            ];

            $subtotal = calculateCartSubtotal($_SESSION['cart']);
            $orderId = 'ORD-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

            if ($voucherCode === '') {
                $lastOrder = [
                    'order_ref' => $orderId,
                    'customer_id' => $customerId,
                    'customer_name' => $_SESSION['checkout_customer']['customer_name'],
                    'voucher_code' => null,
                    'subtotal' => $subtotal,
                    'discount' => 0,
                    'total' => $subtotal,
                    'paid_at' => formatWibDisplay((new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta')))->format('c')),
                ];
                $_SESSION['last_order'] = $lastOrder;
                $_SESSION['applied_voucher_code'] = '';
                $flashSuccess = 'Pembayaran berhasil diproses dengan nomor pesanan ' . $orderId . '.';
            } else {
                $idemKey = 'order-' . $customerId . '-' . $voucherCode . '-' . substr(sha1($customerId . '|' . $voucherCode . '|' . $subtotal . '|' . date('YmdHis')), 0, 12);
                $redeemRes = callKodePromoApi('POST', $apiBaseUrl . '/api/v1/vouchers/redeem', $apiKey, [
                    'code' => $voucherCode,
                    'order_id' => $orderId,
                    'order_amount' => $subtotal,
                    'user_identifier' => $customerId,
                ], $idemKey);

                $rBody = $redeemRes['body'];
                if ($redeemRes['http_code'] >= 200 && $redeemRes['http_code'] < 300) {
                    $data = $rBody['data'] ?? $rBody;
                    $discount = (float) ($data['discount_amount'] ?? ($data['discount'] ?? 0));
                    $finalTotal = isset($data['final_amount'])
                        ? (float) $data['final_amount']
                        : (isset($data['final_total']) ? (float) $data['final_total'] : max(0, $subtotal - $discount));
                    $orderRef = (string) ($data['order_id'] ?? ($data['order_ref'] ?? ($data['redemption_code'] ?? $orderId)));

                    $lastOrder = [
                        'order_ref' => $orderRef,
                        'customer_id' => $customerId,
                        'customer_name' => $_SESSION['checkout_customer']['customer_name'],
                        'voucher_code' => $voucherCode,
                        'subtotal' => $subtotal,
                        'discount' => $discount,
                        'total' => $finalTotal,
                        'paid_at' => formatWibDisplay((new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta')))->format('c')),
                    ];
                    $_SESSION['last_order'] = $lastOrder;
                    $_SESSION['applied_voucher_code'] = '';
                    $flashSuccess = 'Pembayaran berhasil! Voucher ' . $voucherCode . ' terpakai (No. Pesanan: ' . $orderRef . ').';
                } else {
                    $flashError = $rBody['message'] ?? ($rBody['reason'] ?? 'Voucher tidak dapat digunakan atau batas pemakaian akun telah tercapai.');
                }
            }
        }
    }

    header('Location: index.php?flash=' . urlencode($flashSuccess ? 'ok:' . $flashSuccess : 'err:' . ($flashError ?? '')));
    exit;
}

if (isset($_GET['flash'])) {
    $rawFlash = (string) $_GET['flash'];
    if (str_starts_with($rawFlash, 'ok:')) {
        $flashSuccess = substr($rawFlash, 3);
    } elseif (str_starts_with($rawFlash, 'err:')) {
        $flashError = substr($rawFlash, 4);
    }
}

$customerId = (string) ($_SESSION['checkout_customer']['customer_id'] ?? '');
$customerName = (string) ($_SESSION['checkout_customer']['customer_name'] ?? '');
$appliedVoucherCode = (string) ($_SESSION['applied_voucher_code'] ?? '');
$subtotal = calculateCartSubtotal($_SESSION['cart']);

$vouchersList = [];
$campaignsList = [];
$vouchersRes = callKodePromoApi('GET', $apiBaseUrl . '/api/v1/vouchers', $apiKey);
if ($vouchersRes['http_code'] >= 200 && $vouchersRes['http_code'] < 300) {
    $rawV = $vouchersRes['body']['data'] ?? $vouchersRes['body'];
    if (is_array($rawV)) {
        $vouchersList = array_values(array_filter($rawV, 'is_array'));
    }
}

$campaignsRes = callKodePromoApi('GET', $apiBaseUrl . '/api/v1/campaigns', $apiKey);
if ($campaignsRes['http_code'] >= 200 && $campaignsRes['http_code'] < 300) {
    $rawC = $campaignsRes['body']['data'] ?? $campaignsRes['body'];
    if (is_array($rawC)) {
        foreach ($rawC as $c) {
            if (is_array($c) && isset($c['id'])) {
                $campaignsList[(int) $c['id']] = $c;
            }
        }
    }
}

$activeVoucher = null;
foreach ($vouchersList as $v) {
    if ($appliedVoucherCode !== '' && strtoupper((string) ($v['code'] ?? '')) === $appliedVoucherCode) {
        $activeVoucher = $v;
        break;
    }
}

$estimatedDiscount = 0.0;
$voucherNotice = null;
if ($activeVoucher !== null) {
    $minOrder = (float) ($activeVoucher['min_order_amount'] ?? ($activeVoucher['min_purchase'] ?? 0));
    $statusV = strtolower((string) ($activeVoucher['status'] ?? 'active'));
    $quotaTotal = isset($activeVoucher['quota_total']) ? (int) $activeVoucher['quota_total'] : (isset($activeVoucher['quota']) ? (int) $activeVoucher['quota'] : null);
    $quotaUsed = (int) ($activeVoucher['quota_used'] ?? ($activeVoucher['used_count'] ?? ($activeVoucher['redeemed_count'] ?? 0)));

    $campId = isset($activeVoucher['campaign_id']) ? (int) $activeVoucher['campaign_id'] : null;
    $camp = ($campId && isset($campaignsList[$campId])) ? $campaignsList[$campId] : null;
    $nowWib = new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta'));
    $startDt = parseWibDateTime($activeVoucher['starts_at'] ?? ($camp['starts_at'] ?? null));
    $endDt = parseWibDateTime($activeVoucher['ends_at'] ?? ($camp['ends_at'] ?? null));

    if ($statusV !== 'active' && $statusV !== 'aktif') {
        $voucherNotice = 'Voucher sedang tidak aktif.';
    } elseif ($startDt && $nowWib < $startDt) {
        $voucherNotice = 'Periode voucher belum dimulai.';
    } elseif ($endDt && $nowWib > $endDt) {
        $voucherNotice = 'Masa berlaku voucher sudah berakhir.';
    } elseif ($quotaTotal !== null && $quotaUsed >= $quotaTotal) {
        $voucherNotice = 'Kuota voucher sudah habis.';
    } elseif ($minOrder > 0 && $subtotal < $minOrder) {
        $voucherNotice = 'Minimal belanja ' . rupiah($minOrder) . ' untuk menggunakan voucher ini.';
    } else {
        $discType = strtolower((string) ($activeVoucher['discount_type'] ?? 'fixed'));
        $discVal = (float) ($activeVoucher['discount_value'] ?? 0);
        $maxDisc = isset($activeVoucher['max_discount']) && $activeVoucher['max_discount'] !== null ? (float) $activeVoucher['max_discount'] : null;

        if ($discType === 'percent' || $discType === 'percentage') {
            $estimatedDiscount = ($subtotal * $discVal) / 100.0;
            if ($maxDisc !== null && $maxDisc > 0) {
                $estimatedDiscount = min($estimatedDiscount, $maxDisc);
            }
        } else {
            $estimatedDiscount = $discVal;
        }
        $estimatedDiscount = min($estimatedDiscount, $subtotal);
    }
}

$estimatedTotal = max(0.0, $subtotal - $estimatedDiscount);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout — KodePromo</title>
    <style>
        :root {
            --bg-page: #f4f6f9;
            --bg-surface: #ffffff;
            --bg-subtle: #f8fafc;
            --border-color: #e2e8f0;
            --text-primary: #0f172a;
            --text-secondary: #475569;
            --text-muted: #64748b;
            --accent: #2563eb;
            --accent-hover: #1d4ed8;
            --accent-soft: #eff6ff;
            --success-bg: #f0fdf4;
            --success-border: #bbf7d0;
            --success-text: #166534;
            --error-bg: #fef2f2;
            --error-border: #fecaca;
            --error-text: #991b1b;
            --warn-bg: #fffbeb;
            --warn-border: #fde68a;
            --warn-text: #92400e;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: var(--bg-page);
            color: var(--text-primary);
            line-height: 1.5;
        }
        .topbar { background-color: var(--bg-surface); border-bottom: 1px solid var(--border-color); }
        .topbar-inner {
            max-width: 1120px; margin: 0 auto; padding: 16px 24px;
            display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap;
        }
        .brand { font-size: 20px; font-weight: 700; color: var(--text-primary); text-decoration: none; letter-spacing: -0.02em; }
        .brand span { color: var(--accent); }
        .nav-links { display: flex; align-items: center; gap: 24px; font-size: 14px; }
        .nav-links a { color: var(--text-secondary); text-decoration: none; font-weight: 500; }
        .nav-links a:hover, .nav-links a.active { color: var(--accent); }
        .container { max-width: 1120px; margin: 0 auto; padding: 28px 24px 48px; }
        .page-head { margin-bottom: 24px; }
        .page-head h1 { margin: 0 0 4px; font-size: 24px; font-weight: 700; }
        .page-head p { margin: 0; font-size: 14px; color: var(--text-muted); }
        .alert { padding: 12px 16px; border-radius: 8px; font-size: 14px; margin-bottom: 20px; border: 1px solid transparent; }
        .alert-success { background-color: var(--success-bg); border-color: var(--success-border); color: var(--success-text); }
        .alert-error { background-color: var(--error-bg); border-color: var(--error-border); color: var(--error-text); }
        .alert-warn { background-color: var(--warn-bg); border-color: var(--warn-border); color: var(--warn-text); }
        .checkout-grid { display: grid; grid-template-columns: 1.55fr 1fr; gap: 24px; align-items: start; }
        @media (max-width: 900px) { .checkout-grid { grid-template-columns: 1fr; } }
        .panel { background-color: var(--bg-surface); border: 1px solid var(--border-color); border-radius: 10px; padding: 20px 24px; margin-bottom: 20px; }
        .panel:last-child { margin-bottom: 0; }
        .panel-title { margin: 0 0 16px; font-size: 16px; font-weight: 600; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px; }
        @media (max-width: 640px) { .form-row { grid-template-columns: 1fr; } }
        .field label { display: block; font-size: 13px; font-weight: 500; color: var(--text-secondary); margin-bottom: 6px; }
        .field input[type="text"], .field input[type="number"] {
            width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: 8px;
            font-size: 14px; color: var(--text-primary); background-color: var(--bg-surface);
        }
        .field input:focus { outline: none; border-color: var(--accent); }
        .btn {
            display: inline-flex; align-items: center; justify-content: center; padding: 10px 16px;
            border-radius: 8px; font-size: 14px; font-weight: 600; border: 1px solid transparent;
            cursor: pointer; text-decoration: none; transition: background-color 0.15s ease;
        }
        .btn-primary { background-color: var(--accent); color: #ffffff; }
        .btn-primary:hover { background-color: var(--accent-hover); }
        .btn-secondary { background-color: var(--bg-surface); color: var(--text-primary); border-color: var(--border-color); }
        .btn-secondary:hover { background-color: var(--bg-subtle); }
        .btn-sm { padding: 6px 12px; font-size: 13px; }
        .btn-block { width: 100%; padding: 12px 18px; font-size: 15px; }
        .cart-table { width: 100%; border-collapse: collapse; }
        .cart-table th {
            text-align: left; font-size: 12px; font-weight: 600; color: var(--text-muted);
            text-transform: uppercase; letter-spacing: 0.03em; padding: 0 0 10px; border-bottom: 1px solid var(--border-color);
        }
        .cart-table td { padding: 14px 4px; border-bottom: 1px solid var(--border-color); font-size: 14px; vertical-align: middle; }
        .cart-table tr:last-child td { border-bottom: none; }
        .qty-form { display: inline-flex; align-items: center; gap: 6px; }
        .qty-input { width: 64px; padding: 6px 8px; border: 1px solid var(--border-color); border-radius: 6px; font-size: 14px; text-align: center; }
        .voucher-apply-box { display: flex; gap: 10px; margin-bottom: 16px; }
        .voucher-apply-box input { flex: 1; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 14px; text-transform: uppercase; }
        .voucher-list { display: grid; gap: 10px; }
        .voucher-item {
            display: flex; align-items: center; justify-content: space-between; gap: 12px;
            padding: 12px 14px; border: 1px solid var(--border-color); border-radius: 8px; background-color: var(--bg-subtle);
        }
        .voucher-item.is-selected { border-color: var(--accent); background-color: var(--accent-soft); }
        .voucher-code { font-weight: 700; font-size: 14px; color: var(--text-primary); }
        .voucher-meta { font-size: 12px; color: var(--text-muted); margin-top: 2px; }
        .voucher-state { font-size: 12px; font-weight: 600; color: var(--accent); margin-top: 4px; }
        .voucher-state.unavailable { color: var(--text-muted); font-weight: 500; }
        .summary-row { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; font-size: 14px; color: var(--text-secondary); }
        .summary-row.discount span:last-child { color: var(--success-text); font-weight: 600; }
        .summary-total {
            display: flex; justify-content: space-between; align-items: baseline; padding: 14px 0 18px;
            margin-top: 8px; border-top: 1px solid var(--border-color); font-size: 16px; font-weight: 700;
        }
        .summary-total .total-amount { font-size: 20px; color: var(--accent); }
        .promo-banner { margin-top: 20px; padding: 18px 20px; border-radius: 10px; background-color: var(--accent-soft); border: 1px solid #bfdbfe; }
        .promo-banner h3 { margin: 0 0 6px; font-size: 15px; font-weight: 700; }
        .promo-banner p { margin: 0; font-size: 13px; color: var(--text-secondary); }
        .receipt-box { margin-top: 16px; padding-top: 14px; border-top: 1px dashed var(--border-color); font-size: 13px; }
        .receipt-row { display: flex; justify-content: space-between; margin-bottom: 6px; color: var(--text-secondary); }
        .actions-inline { display: flex; justify-content: space-between; align-items: center; gap: 10px; margin-top: 14px; flex-wrap: wrap; }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="topbar-inner">
            <a href="index.php" class="brand">Kode<span>Promo</span></a>
            <nav class="nav-links">
                <a href="index.php" class="active">Checkout</a>
                <a href="#keranjang">Keranjang</a>
                <a href="#voucher">Voucher</a>
                <a href="monitor.php">Admin</a>
            </nav>
        </div>
    </header>

    <main class="container">
        <div class="page-head">
            <h1>Checkout</h1>
            <p>Masukkan informasi akun Anda, periksa keranjang belanja, dan gunakan voucher yang tersedia.</p>
        </div>

        <?php if ($flashSuccess): ?>
            <div class="alert alert-success"><?= htmlspecialchars($flashSuccess) ?></div>
        <?php endif; ?>

        <?php if ($flashError): ?>
            <div class="alert alert-error"><?= htmlspecialchars($flashError) ?></div>
        <?php endif; ?>

        <?php if ($voucherNotice): ?>
            <div class="alert alert-warn"><?= htmlspecialchars($voucherNotice) ?></div>
        <?php endif; ?>

        <div class="checkout-grid">
            <div>
                <section class="panel" id="akun">
                    <h2 class="panel-title">Informasi Akun</h2>
                    <form method="post">
                        <input type="hidden" name="action" value="update_customer">
                        <div class="form-row">
                            <div class="field">
                                <label for="customer_id_account">ID Akun Customer</label>
                                <input type="text" id="customer_id_account" name="customer_id" value="<?= htmlspecialchars($customerId) ?>" placeholder="Contoh: CUST-101" required>
                            </div>
                            <div class="field">
                                <label for="customer_name_account">Nama Lengkap</label>
                                <input type="text" id="customer_name_account" name="customer_name" value="<?= htmlspecialchars($customerName) ?>" placeholder="Masukkan nama Anda">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-secondary btn-sm">Simpan Informasi Akun</button>
                    </form>
                </section>

                <section class="panel" id="keranjang">
                    <h2 class="panel-title">Keranjang Belanja</h2>
                    <table class="cart-table">
                        <thead>
                            <tr>
                                <th>Produk</th>
                                <th>Harga</th>
                                <th>Jumlah</th>
                                <th>Subtotal</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($_SESSION['cart'] as $item): ?>
                                <?php
                                $sku = (string) ($item['sku'] ?? '');
                                $name = (string) ($item['name'] ?? '');
                                $price = (float) ($item['price'] ?? 0);
                                $qty = (int) ($item['qty'] ?? 1);
                                $lineTotal = $price * $qty;
                                ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($name) ?></strong></td>
                                    <td><?= rupiah($price) ?></td>
                                    <td>
                                        <form method="post" class="qty-form">
                                            <input type="hidden" name="action" value="update_qty">
                                            <input type="hidden" name="sku" value="<?= htmlspecialchars($sku) ?>">
                                            <input type="number" name="qty" value="<?= $qty ?>" min="1" max="20" class="qty-input" aria-label="Jumlah <?= htmlspecialchars($name) ?>">
                                            <button type="submit" class="btn btn-secondary btn-sm">Ubah</button>
                                        </form>
                                    </td>
                                    <td><strong><?= rupiah($lineTotal) ?></strong></td>
                                    <td>
                                        <?php if (count($_SESSION['cart']) > 1): ?>
                                            <form method="post" style="display:inline;">
                                                <input type="hidden" name="action" value="remove_item">
                                                <input type="hidden" name="sku" value="<?= htmlspecialchars($sku) ?>">
                                                <button type="submit" class="btn btn-secondary btn-sm">Hapus</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <div class="actions-inline">
                        <span class="voucher-meta">Total item: <?= array_sum(array_map(fn($i) => (int) ($i['qty'] ?? 0), $_SESSION['cart'])) ?> barang</span>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="action" value="reset_cart">
                            <button type="submit" class="btn btn-secondary btn-sm">Reset Keranjang</button>
                        </form>
                    </div>
                </section>

                <section class="panel" id="voucher">
                    <h2 class="panel-title">Kode Voucher</h2>

                    <form method="post" class="voucher-apply-box">
                        <input type="hidden" name="action" value="apply_voucher">
                        <input type="hidden" name="customer_id" value="<?= htmlspecialchars($customerId) ?>">
                        <input type="hidden" name="customer_name" value="<?= htmlspecialchars($customerName) ?>">
                        <input type="text" name="voucher_code" value="<?= htmlspecialchars($appliedVoucherCode) ?>" placeholder="Masukkan kode voucher" aria-label="Kode voucher">
                        <button type="submit" class="btn btn-primary">Gunakan</button>
                        <?php if ($appliedVoucherCode !== ''): ?>
                            <button type="submit" name="action" value="clear_voucher" class="btn btn-secondary">Lepas</button>
                        <?php endif; ?>
                    </form>

                    <?php if (!empty($vouchersList)): ?>
                        <div class="voucher-list">
                            <?php foreach ($vouchersList as $v): ?>
                                <?php
                                $vCode = strtoupper((string) ($v['code'] ?? ''));
                                if ($vCode === '') {
                                    continue;
                                }
                                $vType = strtolower((string) ($v['discount_type'] ?? 'fixed'));
                                $vVal = (float) ($v['discount_value'] ?? 0);
                                $vMax = isset($v['max_discount']) && $v['max_discount'] !== null ? (float) $v['max_discount'] : null;
                                $vMin = (float) ($v['min_order_amount'] ?? ($v['min_purchase'] ?? 0));
                                $vStatus = strtolower((string) ($v['status'] ?? 'active'));
                                $vQuota = isset($v['quota_total']) ? (int) $v['quota_total'] : (isset($v['quota']) ? (int) $v['quota'] : null);
                                $vUsed = (int) ($v['quota_used'] ?? ($v['used_count'] ?? ($v['redeemed_count'] ?? 0)));

                                $discLabel = ($vType === 'percent' || $vType === 'percentage')
                                    ? rtrim(rtrim(number_format($vVal, 2, '.', ''), '0'), '.') . '%' . ($vMax ? ' (maks ' . rupiah($vMax) . ')' : '')
                                    : rupiah($vVal);

                                $isSelected = ($appliedVoucherCode === $vCode);
                                $reasonText = 'Tersedia';
                                $canPick = true;

                                if ($vStatus !== 'active' && $vStatus !== 'aktif') {
                                    $reasonText = 'Tidak aktif';
                                    $canPick = false;
                                } elseif ($vQuota !== null && $vUsed >= $vQuota) {
                                    $reasonText = 'Kuota habis';
                                    $canPick = false;
                                } elseif ($vMin > 0 && $subtotal < $vMin) {
                                    $reasonText = 'Min. belanja ' . rupiah($vMin);
                                    $canPick = false;
                                }
                                ?>
                                <div class="voucher-item <?= $isSelected ? 'is-selected' : '' ?>">
                                    <div>
                                        <div class="voucher-code"><?= htmlspecialchars($vCode) ?></div>
                                        <div class="voucher-meta">
                                            Potongan <?= htmlspecialchars($discLabel) ?>
                                            <?php if ($vMin > 0): ?>
                                                &middot; Min. belanja <?= rupiah($vMin) ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="voucher-state <?= $canPick ? '' : 'unavailable' ?>">
                                            <?= $isSelected ? 'Sedang digunakan' : htmlspecialchars($reasonText) ?>
                                        </div>
                                    </div>

                                    <form method="post" style="margin:0;">
                                        <input type="hidden" name="action" value="apply_voucher">
                                        <input type="hidden" name="customer_id" value="<?= htmlspecialchars($customerId) ?>">
                                        <input type="hidden" name="customer_name" value="<?= htmlspecialchars($customerName) ?>">
                                        <input type="hidden" name="voucher_code" value="<?= htmlspecialchars($vCode) ?>">
                                        <button type="submit" class="btn <?= $isSelected ? 'btn-primary' : 'btn-secondary' ?> btn-sm" <?= $canPick ? '' : 'disabled' ?>>
                                            <?= $isSelected ? 'Dipakai' : 'Pakai' ?>
                                        </button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            </div>

            <div>
                <section class="panel">
                    <h2 class="panel-title">Ringkasan Pembayaran</h2>

                    <div class="summary-row">
                        <span>Akun Customer</span>
                        <strong><?= htmlspecialchars($customerId !== '' ? ($customerName !== '' ? $customerName . ' (' . $customerId . ')' : $customerId) : 'Belum diisi') ?></strong>
                    </div>

                    <div class="summary-row">
                        <span>Subtotal Belanja</span>
                        <span><?= rupiah($subtotal) ?></span>
                    </div>

                    <div class="summary-row discount">
                        <span>Diskon Voucher<?= $appliedVoucherCode !== '' ? ' (' . htmlspecialchars($appliedVoucherCode) . ')' : '' ?></span>
                        <span><?= $estimatedDiscount > 0 ? '- ' . rupiah($estimatedDiscount) : rupiah(0) ?></span>
                    </div>

                    <div class="summary-total">
                        <span>Total Pembayaran</span>
                        <span class="total-amount"><?= rupiah($estimatedTotal) ?></span>
                    </div>

                    <form method="post">
                        <input type="hidden" name="action" value="checkout_redeem">
                        <input type="hidden" name="customer_id" value="<?= htmlspecialchars($customerId) ?>">
                        <input type="hidden" name="customer_name" value="<?= htmlspecialchars($customerName) ?>">
                        <input type="hidden" name="voucher_code" value="<?= htmlspecialchars($appliedVoucherCode) ?>">
                        <button type="submit" class="btn btn-primary btn-block">Bayar Sekarang</button>
                    </form>

                    <?php if (is_array($lastOrder)): ?>
                        <div class="receipt-box">
                            <div class="receipt-row">
                                <span>No. Pesanan</span>
                                <strong><?= htmlspecialchars((string) ($lastOrder['order_ref'] ?? '-')) ?></strong>
                            </div>
                            <div class="receipt-row">
                                <span>Customer</span>
                                <span><?= htmlspecialchars((string) ($lastOrder['customer_id'] ?? '-')) ?></span>
                            </div>
                            <?php if (!empty($lastOrder['voucher_code'])): ?>
                                <div class="receipt-row">
                                    <span>Voucher</span>
                                    <span><?= htmlspecialchars((string) $lastOrder['voucher_code']) ?></span>
                                </div>
                            <?php endif; ?>
                            <div class="receipt-row">
                                <span>Total Dibayar</span>
                                <strong><?= rupiah((float) ($lastOrder['total'] ?? 0)) ?></strong>
                            </div>
                            <div class="receipt-row">
                                <span>Waktu</span>
                                <span><?= htmlspecialchars((string) ($lastOrder['paid_at'] ?? '-')) ?></span>
                            </div>
                        </div>
                    <?php endif; ?>
                </section>

                <div class="promo-banner">
                    <h3>Gunakan Voucher untuk Hemat Lebih!</h3>
                    <p>Pilih kode voucher yang sesuai dengan minimal belanja Anda sebelum menyelesaikan pembayaran.</p>
                </div>
            </div>
        </div>
    </main>
</body>
</html>