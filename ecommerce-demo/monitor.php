<?php
require_once __DIR__ . '/config.php';

$flashSuccess = null;
$flashError = null;
$activeTab = $_GET['tab'] ?? 'dashboard';
if (!in_array($activeTab, ['dashboard', 'campaigns', 'vouchers', 'transactions'], true)) {
    $activeTab = 'dashboard';
}

$editCampaign = null;
$editVoucher = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $redirectTab = $_POST['redirect_tab'] ?? $activeTab;
    if (!in_array($redirectTab, ['dashboard', 'campaigns', 'vouchers', 'transactions'], true)) {
        $redirectTab = 'dashboard';
    }

    if ($action === 'create_campaign') {
        $payload = [
            'name' => trim((string) ($_POST['name'] ?? '')),
            'description' => trim((string) ($_POST['description'] ?? '')),
            'starts_at' => toIsoWib((string) ($_POST['starts_at'] ?? '')),
            'ends_at' => toIsoWib((string) ($_POST['ends_at'] ?? '')),
            'status' => trim((string) ($_POST['status'] ?? 'active')),
        ];
        $res = callKodePromoApi('POST', $apiBaseUrl . '/api/v1/campaigns', $apiKey, $payload);
        if ($res['http_code'] >= 200 && $res['http_code'] < 300) {
            $flashSuccess = 'Kampanye baru berhasil ditambahkan.';
        } else {
            $flashError = $res['body']['message'] ?? 'Gagal menambahkan kampanye.';
        }
    } elseif ($action === 'update_campaign') {
        $id = (int) ($_POST['id'] ?? 0);
        $payload = [
            'name' => trim((string) ($_POST['name'] ?? '')),
            'description' => trim((string) ($_POST['description'] ?? '')),
            'starts_at' => toIsoWib((string) ($_POST['starts_at'] ?? '')),
            'ends_at' => toIsoWib((string) ($_POST['ends_at'] ?? '')),
            'status' => trim((string) ($_POST['status'] ?? 'active')),
        ];
        $res = callKodePromoApi('PUT', $apiBaseUrl . '/api/v1/campaigns/' . $id, $apiKey, $payload);
        if ($res['http_code'] >= 200 && $res['http_code'] < 300) {
            $flashSuccess = 'Data kampanye berhasil diperbarui.';
        } else {
            $flashError = $res['body']['message'] ?? 'Gagal memperbarui kampanye.';
        }
    } elseif ($action === 'delete_campaign') {
        $id = (int) ($_POST['id'] ?? 0);
        $res = callKodePromoApi('DELETE', $apiBaseUrl . '/api/v1/campaigns/' . $id, $apiKey);
        if ($res['http_code'] >= 200 && $res['http_code'] < 300) {
            $flashSuccess = 'Kampanye berhasil dihapus.';
        } else {
            $flashError = $res['body']['message'] ?? 'Gagal menghapus kampanye.';
        }
    } elseif ($action === 'create_voucher') {
        $maxDiscRaw = trim((string) ($_POST['max_discount'] ?? ''));
        $payload = [
            'campaign_id' => (int) ($_POST['campaign_id'] ?? 0),
            'code' => strtoupper(trim((string) ($_POST['code'] ?? ''))),
            'discount_type' => trim((string) ($_POST['discount_type'] ?? 'percentage')),
            'discount_value' => (float) ($_POST['discount_value'] ?? 0),
            'max_discount' => $maxDiscRaw !== '' ? (float) $maxDiscRaw : null,
            'min_order_amount' => (float) ($_POST['min_order_amount'] ?? 0),
            'quota' => (int) ($_POST['quota'] ?? 1),
            'per_user_limit' => (int) ($_POST['per_user_limit'] ?? 1),
            'starts_at' => toIsoWib((string) ($_POST['starts_at'] ?? '')),
            'ends_at' => toIsoWib((string) ($_POST['ends_at'] ?? '')),
            'status' => trim((string) ($_POST['status'] ?? 'active')),
        ];
        $res = callKodePromoApi('POST', $apiBaseUrl . '/api/v1/vouchers', $apiKey, $payload);
        if ($res['http_code'] >= 200 && $res['http_code'] < 300) {
            $flashSuccess = 'Kode voucher berhasil dibuat.';
        } else {
            $flashError = $res['body']['message'] ?? 'Gagal membuat kode voucher.';
        }
    } elseif ($action === 'update_voucher') {
        $id = (int) ($_POST['id'] ?? 0);
        $maxDiscRaw = trim((string) ($_POST['max_discount'] ?? ''));
        $payload = [
            'campaign_id' => (int) ($_POST['campaign_id'] ?? 0),
            'code' => strtoupper(trim((string) ($_POST['code'] ?? ''))),
            'discount_type' => trim((string) ($_POST['discount_type'] ?? 'percentage')),
            'discount_value' => (float) ($_POST['discount_value'] ?? 0),
            'max_discount' => $maxDiscRaw !== '' ? (float) $maxDiscRaw : null,
            'min_order_amount' => (float) ($_POST['min_order_amount'] ?? 0),
            'quota' => (int) ($_POST['quota'] ?? 1),
            'per_user_limit' => (int) ($_POST['per_user_limit'] ?? 1),
            'starts_at' => toIsoWib((string) ($_POST['starts_at'] ?? '')),
            'ends_at' => toIsoWib((string) ($_POST['ends_at'] ?? '')),
            'status' => trim((string) ($_POST['status'] ?? 'active')),
        ];
        $res = callKodePromoApi('PUT', $apiBaseUrl . '/api/v1/vouchers/' . $id, $apiKey, $payload);
        if ($res['http_code'] >= 200 && $res['http_code'] < 300) {
            $flashSuccess = 'Data voucher berhasil diperbarui.';
        } else {
            $flashError = $res['body']['message'] ?? 'Gagal memperbarui voucher.';
        }
    } elseif ($action === 'delete_voucher') {
        $id = (int) ($_POST['id'] ?? 0);
        $res = callKodePromoApi('DELETE', $apiBaseUrl . '/api/v1/vouchers/' . $id, $apiKey);
        if ($res['http_code'] >= 200 && $res['http_code'] < 300) {
            $flashSuccess = 'Voucher berhasil dihapus.';
        } else {
            $flashError = $res['body']['message'] ?? 'Gagal menghapus voucher.';
        }
    }

    $q = '?tab=' . urlencode($redirectTab);
    if ($flashSuccess) {
        $q .= '&msg=' . urlencode('ok:' . $flashSuccess);
    } elseif ($flashError) {
        $q .= '&msg=' . urlencode('err:' . $flashError);
    }
    header('Location: monitor.php' . $q);
    exit;
}

if (isset($_GET['msg'])) {
    $rawMsg = (string) $_GET['msg'];
    if (str_starts_with($rawMsg, 'ok:')) {
        $flashSuccess = substr($rawMsg, 3);
    } elseif (str_starts_with($rawMsg, 'err:')) {
        $flashError = substr($rawMsg, 4);
    }
}

$campaigns = [];
$campMap = [];
$campRes = callKodePromoApi('GET', $apiBaseUrl . '/api/v1/campaigns', $apiKey);
if ($campRes['http_code'] >= 200 && $campRes['http_code'] < 300) {
    $rawC = $campRes['body']['data'] ?? $campRes['body'];
    if (is_array($rawC)) {
        foreach ($rawC as $c) {
            if (is_array($c)) {
                $campaigns[] = $c;
                if (isset($c['id'])) {
                    $campMap[(int) $c['id']] = $c;
                }
            }
        }
    }
}

$vouchers = [];
$voucherRes = callKodePromoApi('GET', $apiBaseUrl . '/api/v1/vouchers', $apiKey);
if ($voucherRes['http_code'] >= 200 && $voucherRes['http_code'] < 300) {
    $rawV = $voucherRes['body']['data'] ?? $voucherRes['body'];
    if (is_array($rawV)) {
        foreach ($rawV as $v) {
            if (is_array($v)) {
                $vouchers[] = $v;
            }
        }
    }
}

$redemptions = [];
$redempRes = callKodePromoApi('GET', $apiBaseUrl . '/api/v1/redemptions', $apiKey);
if ($redempRes['http_code'] >= 200 && $redempRes['http_code'] < 300) {
    $bodyR = $redempRes['body'];
    $rawR = $bodyR['data']['data'] ?? ($bodyR['data']['items'] ?? ($bodyR['data']['redemptions'] ?? ($bodyR['data'] ?? ($bodyR['redemptions'] ?? $bodyR))));
    if (is_array($rawR)) {
        if (array_is_list($rawR)) {
            foreach ($rawR as $r) {
                if (is_array($r)) {
                    $redemptions[] = $r;
                }
            }
        } elseif (isset($rawR['user_identifier']) || isset($rawR['customer_id']) || isset($rawR['voucher_code']) || isset($rawR['discount_amount']) || isset($rawR['order_amount'])) {
            $redemptions[] = $rawR;
        }
    }
}

if (isset($_GET['edit_campaign'])) {
    $targetId = (int) $_GET['edit_campaign'];
    foreach ($campaigns as $c) {
        if ((int) ($c['id'] ?? 0) === $targetId) {
            $editCampaign = $c;
            $activeTab = 'campaigns';
            break;
        }
    }
}

if (isset($_GET['edit_voucher'])) {
    $targetId = (int) $_GET['edit_voucher'];
    foreach ($vouchers as $v) {
        if ((int) ($v['id'] ?? 0) === $targetId) {
            $editVoucher = $v;
            $activeTab = 'vouchers';
            break;
        }
    }
}

$totalCampaigns = count($campaigns);
$totalVouchers = count($vouchers);
$totalTransactions = count($redemptions);
$totalDiscountGiven = 0.0;
foreach ($redemptions as $r) {
    $totalDiscountGiven += (float) ($r['discount_amount'] ?? ($r['discount'] ?? 0));
}

$nowWib = new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta'));
$defaultStartLocal = $nowWib->format('Y-m-d\TH:i');
$defaultEndLocal = $nowWib->modify('+7 days')->format('Y-m-d\TH:i');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Voucher — KodePromo</title>
    <style>
        :root {
            --sidebar-bg: #0f1f3d;
            --sidebar-hover: #1a305c;
            --sidebar-active: #2563eb;
            --bg-main: #f4f6f9;
            --bg-card: #ffffff;
            --bg-subtle: #f8fafc;
            --border-color: #e2e8f0;
            --text-primary: #0f172a;
            --text-secondary: #475569;
            --text-muted: #64748b;
            --accent: #2563eb;
            --accent-hover: #1d4ed8;
            --danger: #dc2626;
            --danger-hover: #b91c1c;
            --success-bg: #f0fdf4;
            --success-border: #bbf7d0;
            --success-text: #166534;
            --error-bg: #fef2f2;
            --error-border: #fecaca;
            --error-text: #991b1b;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: var(--bg-main);
            color: var(--text-primary);
            line-height: 1.5;
        }
        .layout { display: flex; min-height: 100vh; }
        .sidebar {
            width: 240px; flex-shrink: 0; background-color: var(--sidebar-bg);
            color: #e2e8f0; display: flex; flex-direction: column; justify-content: space-between;
        }
        .sidebar-brand {
            padding: 22px 20px; font-size: 19px; font-weight: 700;
            color: #ffffff; border-bottom: 1px solid rgba(255, 255, 255, 0.08); letter-spacing: -0.02em;
        }
        .sidebar-nav { padding: 16px 12px; display: flex; flex-direction: column; gap: 6px; }
        .nav-item {
            display: block; padding: 10px 14px; border-radius: 8px; color: #cbd5e1;
            text-decoration: none; font-size: 14px; font-weight: 500;
            transition: background-color 0.15s ease, color 0.15s ease;
        }
        .nav-item:hover { background-color: var(--sidebar-hover); color: #ffffff; }
        .nav-item.active { background-color: var(--sidebar-active); color: #ffffff; font-weight: 600; }
        .sidebar-footer { padding: 16px 12px; border-top: 1px solid rgba(255, 255, 255, 0.08); }
        .main-wrapper { flex: 1; min-width: 0; display: flex; flex-direction: column; }
        .top-header {
            background-color: var(--bg-card); border-bottom: 1px solid var(--border-color);
            padding: 14px 28px; display: flex; align-items: center; justify-content: space-between; gap: 16px;
        }
        .admin-badge { font-size: 13px; font-weight: 600; color: var(--text-secondary); }
        .content { padding: 26px 28px 44px; max-width: 1280px; width: 100%; }
        .page-title-row {
            display: flex; align-items: center; justify-content: space-between;
            gap: 16px; margin-bottom: 22px; flex-wrap: wrap;
        }
        .page-title-row h1 { margin: 0 0 4px; font-size: 24px; font-weight: 700; }
        .page-title-row p { margin: 0; font-size: 14px; color: var(--text-muted); }
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
        @media (max-width: 1024px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 560px) { .stats-grid { grid-template-columns: 1fr; } }
        .stat-card { background-color: var(--bg-card); border: 1px solid var(--border-color); border-radius: 10px; padding: 18px 20px; }
        .stat-label { font-size: 13px; color: var(--text-muted); margin-bottom: 8px; }
        .stat-value { font-size: 24px; font-weight: 700; color: var(--text-primary); }
        .card { background-color: var(--bg-card); border: 1px solid var(--border-color); border-radius: 10px; padding: 20px 24px; margin-bottom: 22px; }
        .card-header { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 16px; flex-wrap: wrap; }
        .card-header h2 { margin: 0; font-size: 16px; font-weight: 600; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th {
            text-align: left; font-size: 12px; font-weight: 600; color: var(--text-muted);
            padding: 10px 12px; border-bottom: 1px solid var(--border-color); background-color: var(--bg-subtle);
        }
        td { padding: 12px; font-size: 14px; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        .status-text { font-size: 13px; font-weight: 600; color: var(--accent); }
        .status-text.inactive { color: var(--text-muted); }
        .btn {
            display: inline-flex; align-items: center; justify-content: center; padding: 8px 14px;
            border-radius: 8px; font-size: 13px; font-weight: 600; border: 1px solid transparent;
            cursor: pointer; text-decoration: none;
        }
        .btn-primary { background-color: var(--accent); color: #ffffff; }
        .btn-primary:hover { background-color: var(--accent-hover); }
        .btn-secondary { background-color: var(--bg-card); color: var(--text-primary); border-color: var(--border-color); }
        .btn-secondary:hover { background-color: var(--bg-subtle); }
        .btn-danger { background-color: var(--danger); color: #ffffff; }
        .btn-danger:hover { background-color: var(--danger-hover); }
        .action-group { display: inline-flex; align-items: center; gap: 8px; }
        .form-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; }
        @media (max-width: 900px) { .form-grid { grid-template-columns: 1fr 1fr; } }
        @media (max-width: 600px) { .form-grid { grid-template-columns: 1fr; } }
        .field label { display: block; font-size: 13px; font-weight: 500; color: var(--text-secondary); margin-bottom: 6px; }
        .field input, .field select {
            width: 100%; padding: 9px 12px; border: 1px solid var(--border-color); border-radius: 8px;
            font-size: 14px; color: var(--text-primary); background-color: var(--bg-card);
        }
        .field input:focus, .field select:focus { outline: none; border-color: var(--accent); }
        .form-actions { margin-top: 16px; display: flex; align-items: center; gap: 10px; }
        .alert { padding: 12px 16px; border-radius: 8px; font-size: 14px; margin-bottom: 18px; border: 1px solid transparent; }
        .alert-success { background-color: var(--success-bg); border-color: var(--success-border); color: var(--success-text); }
        .alert-error { background-color: var(--error-bg); border-color: var(--error-border); color: var(--error-text); }
        .welcome-strip {
            background-color: var(--bg-card); border: 1px solid var(--border-color);
            border-radius: 10px; padding: 16px 20px; font-size: 14px; color: var(--text-secondary);
        }
        .welcome-strip strong { color: var(--text-primary); }
        @media (max-width: 780px) {
            .layout { flex-direction: column; }
            .sidebar { width: 100%; }
            .sidebar-nav { flex-direction: row; flex-wrap: wrap; }
        }
    </style>
</head>
<body>
    <div class="layout">
        <aside class="sidebar">
            <div>
                <div class="sidebar-brand">KodePromo</div>
                <nav class="sidebar-nav">
                    <a href="monitor.php?tab=dashboard" class="nav-item <?= $activeTab === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
                    <a href="monitor.php?tab=campaigns" class="nav-item <?= $activeTab === 'campaigns' ? 'active' : '' ?>">Kampanye</a>
                    <a href="monitor.php?tab=vouchers" class="nav-item <?= $activeTab === 'vouchers' ? 'active' : '' ?>">Kode Voucher</a>
                    <a href="monitor.php?tab=transactions" class="nav-item <?= $activeTab === 'transactions' ? 'active' : '' ?>">Riwayat Penukaran</a>
                </nav>
            </div>
            <div class="sidebar-footer">
                <a href="index.php" class="nav-item">Halaman Checkout</a>
            </div>
        </aside>

        <div class="main-wrapper">
            <header class="top-header">
                <div class="admin-badge">Admin</div>
                <a href="index.php" class="btn btn-secondary">Lihat Toko</a>
            </header>

            <main class="content">
                <div class="page-title-row">
                    <div>
                        <h1>
                            <?php
                            if ($activeTab === 'campaigns') {
                                echo 'Kampanye';
                            } elseif ($activeTab === 'vouchers') {
                                echo 'Kode Voucher';
                            } elseif ($activeTab === 'transactions') {
                                echo 'Riwayat Penukaran';
                            } else {
                                echo 'Dashboard';
                            }
                            ?>
                        </h1>
                        <p>Kelola kampanye, kode voucher, kuota, dan pantau penggunaan promo.</p>
                    </div>

                    <?php if ($activeTab === 'dashboard'): ?>
                        <div class="action-group">
                            <a href="monitor.php?tab=campaigns" class="btn btn-primary">+ Buat Kampanye</a>
                            <a href="monitor.php?tab=vouchers" class="btn btn-secondary">+ Buat Kode Voucher</a>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($flashSuccess): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($flashSuccess) ?></div>
                <?php endif; ?>

                <?php if ($flashError): ?>
                    <div class="alert alert-error"><?= htmlspecialchars($flashError) ?></div>
                <?php endif; ?>

                <section class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-label">Total Kampanye</div>
                        <div class="stat-value"><?= $totalCampaigns ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Total Kode Voucher</div>
                        <div class="stat-value"><?= $totalVouchers ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Total Transaksi</div>
                        <div class="stat-value"><?= $totalTransactions ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Total Diskon Diberikan</div>
                        <div class="stat-value"><?= rupiah($totalDiscountGiven) ?></div>
                    </div>
                </section>

                <?php if ($activeTab === 'dashboard' || $activeTab === 'campaigns'): ?>
                    <section class="card">
                        <div class="card-header">
                            <h2><?= $editCampaign ? 'Edit Kampanye' : 'Tambah Kampanye' ?></h2>
                            <?php if ($editCampaign): ?>
                                <a href="monitor.php?tab=campaigns" class="btn btn-secondary">Batal Edit</a>
                            <?php endif; ?>
                        </div>

                        <form method="post">
                            <input type="hidden" name="action" value="<?= $editCampaign ? 'update_campaign' : 'create_campaign' ?>">
                            <input type="hidden" name="redirect_tab" value="<?= htmlspecialchars($activeTab) ?>">
                            <?php if ($editCampaign): ?>
                                <input type="hidden" name="id" value="<?= (int) ($editCampaign['id'] ?? 0) ?>">
                            <?php endif; ?>

                            <div class="form-grid">
                                <div class="field">
                                    <label for="camp_name">Nama Kampanye</label>
                                    <input type="text" id="camp_name" name="name" value="<?= htmlspecialchars((string) ($editCampaign['name'] ?? '')) ?>" placeholder="Contoh: Promo Akhir Tahun" required>
                                </div>
                                <div class="field">
                                    <label for="camp_status">Status</label>
                                    <?php $cStatus = strtolower((string) ($editCampaign['status'] ?? 'active')); ?>
                                    <select id="camp_status" name="status">
                                        <option value="active" <?= $cStatus === 'active' || $cStatus === 'aktif' ? 'selected' : '' ?>>Aktif</option>
                                        <option value="inactive" <?= $cStatus === 'inactive' || $cStatus === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
                                    </select>
                                </div>
                                <div class="field">
                                    <label for="camp_desc">Keterangan</label>
                                    <input type="text" id="camp_desc" name="description" value="<?= htmlspecialchars((string) ($editCampaign['description'] ?? '')) ?>" placeholder="Deskripsi singkat kampanye">
                                </div>
                                <div class="field">
                                    <label for="camp_start">Tanggal & Jam Mulai (WIB)</label>
                                    <input type="datetime-local" id="camp_start" name="starts_at" value="<?= htmlspecialchars($editCampaign ? toDatetimeLocal($editCampaign['starts_at'] ?? null) : $defaultStartLocal) ?>" required>
                                </div>
                                <div class="field">
                                    <label for="camp_end">Tanggal & Jam Selesai (WIB)</label>
                                    <input type="datetime-local" id="camp_end" name="ends_at" value="<?= htmlspecialchars($editCampaign ? toDatetimeLocal($editCampaign['ends_at'] ?? null) : $defaultEndLocal) ?>" required>
                                </div>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary"><?= $editCampaign ? 'Simpan Perubahan' : '+ Buat Kampanye' ?></button>
                            </div>
                        </form>
                    </section>

                    <section class="card">
                        <div class="card-header">
                            <h2>Daftar Kampanye</h2>
                        </div>
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nama Kampanye</th>
                                        <th>Periode (WIB)</th>
                                        <th>Status</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($campaigns)): ?>
                                        <tr><td colspan="5">Belum ada kampanye.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($campaigns as $c): ?>
                                            <?php
                                            $cId = (int) ($c['id'] ?? 0);
                                            $st = strtolower((string) ($c['status'] ?? 'active'));
                                            $isAct = ($st === 'active' || $st === 'aktif');
                                            ?>
                                            <tr>
                                                <td>#<?= $cId ?></td>
                                                <td><strong><?= htmlspecialchars((string) ($c['name'] ?? '-')) ?></strong></td>
                                                <td>
                                                    <?= htmlspecialchars(formatWibDisplay($c['starts_at'] ?? null, 'd M Y H:i')) ?>
                                                    &ndash;
                                                    <?= htmlspecialchars(formatWibDisplay($c['ends_at'] ?? null, 'd M Y H:i')) ?>
                                                </td>
                                                <td><span class="status-text <?= $isAct ? '' : 'inactive' ?>"><?= $isAct ? 'Aktif' : 'Nonaktif' ?></span></td>
                                                <td>
                                                    <div class="action-group">
                                                        <a href="monitor.php?tab=campaigns&edit_campaign=<?= $cId ?>" class="btn btn-secondary">Edit</a>
                                                        <form method="post" style="display:inline;" onsubmit="return confirm('Hapus kampanye ini?');">
                                                            <input type="hidden" name="action" value="delete_campaign">
                                                            <input type="hidden" name="id" value="<?= $cId ?>">
                                                            <input type="hidden" name="redirect_tab" value="<?= htmlspecialchars($activeTab) ?>">
                                                            <button type="submit" class="btn btn-danger">Hapus</button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>
                <?php endif; ?>

                <?php if ($activeTab === 'dashboard' || $activeTab === 'vouchers'): ?>
                    <section class="card">
                        <div class="card-header">
                            <h2><?= $editVoucher ? 'Edit Kode Voucher' : 'Tambah Kode Voucher' ?></h2>
                            <?php if ($editVoucher): ?>
                                <a href="monitor.php?tab=vouchers" class="btn btn-secondary">Batal Edit</a>
                            <?php endif; ?>
                        </div>

                        <form method="post">
                            <input type="hidden" name="action" value="<?= $editVoucher ? 'update_voucher' : 'create_voucher' ?>">
                            <input type="hidden" name="redirect_tab" value="<?= htmlspecialchars($activeTab) ?>">
                            <?php if ($editVoucher): ?>
                                <input type="hidden" name="id" value="<?= (int) ($editVoucher['id'] ?? 0) ?>">
                            <?php endif; ?>

                            <div class="form-grid">
                                <div class="field">
                                    <label for="v_campaign">Kampanye</label>
                                    <?php $selCampId = (int) ($editVoucher['campaign_id'] ?? ($campaigns[0]['id'] ?? 0)); ?>
                                    <select id="v_campaign" name="campaign_id" required>
                                        <?php foreach ($campaigns as $c): ?>
                                            <?php $cid = (int) ($c['id'] ?? 0); ?>
                                            <option value="<?= $cid ?>" <?= $cid === $selCampId ? 'selected' : '' ?>>
                                                <?= htmlspecialchars((string) ($c['name'] ?? ('Kampanye #' . $cid))) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="field">
                                    <label for="v_code">Kode Voucher</label>
                                    <input type="text" id="v_code" name="code" value="<?= htmlspecialchars((string) ($editVoucher['code'] ?? '')) ?>" placeholder="Contoh: HEMAT25" style="text-transform:uppercase;" required>
                                </div>
                                <div class="field">
                                    <label for="v_status">Status</label>
                                    <?php $vSt = strtolower((string) ($editVoucher['status'] ?? 'active')); ?>
                                    <select id="v_status" name="status">
                                        <option value="active" <?= $vSt === 'active' || $vSt === 'aktif' ? 'selected' : '' ?>>Aktif</option>
                                        <option value="inactive" <?= $vSt === 'inactive' || $vSt === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
                                    </select>
                                </div>
                                <div class="field">
                                    <label for="v_dtype">Tipe Diskon</label>
                                    <?php $dType = strtolower((string) ($editVoucher['discount_type'] ?? 'percentage')); ?>
                                    <select id="v_dtype" name="discount_type">
                                        <option value="percentage" <?= $dType === 'percent' || $dType === 'percentage' ? 'selected' : '' ?>>Persentase (%)</option>
                                        <option value="fixed" <?= $dType === 'fixed' ? 'selected' : '' ?>>Nominal (Rp)</option>
                                    </select>
                                </div>
                                <div class="field">
                                    <label for="v_dval">Nilai Diskon</label>
                                    <input type="number" step="any" id="v_dval" name="discount_value" value="<?= htmlspecialchars((string) ($editVoucher['discount_value'] ?? '20')) ?>" required>
                                </div>
                                <div class="field">
                                    <label for="v_maxdisc">Maksimal Diskon (Rp, opsional)</label>
                                    <input type="number" step="any" id="v_maxdisc" name="max_discount" value="<?= htmlspecialchars((string) ($editVoucher['max_discount'] ?? '')) ?>">
                                </div>
                                <div class="field">
                                    <label for="v_minorder">Minimal Belanja (Rp)</label>
                                    <input type="number" step="any" id="v_minorder" name="min_order_amount" value="<?= htmlspecialchars((string) ($editVoucher['min_order_amount'] ?? ($editVoucher['min_purchase'] ?? '100000'))) ?>" required>
                                </div>
                                <div class="field">
                                    <label for="v_quota">Kuota Total</label>
                                    <input type="number" id="v_quota" name="quota" value="<?= htmlspecialchars((string) ($editVoucher['quota_total'] ?? ($editVoucher['quota'] ?? '10'))) ?>" min="1" required>
                                </div>
                                <div class="field">
                                    <label for="v_limit">Batas Pakai per Akun</label>
                                    <input type="number" id="v_limit" name="per_user_limit" value="<?= htmlspecialchars((string) ($editVoucher['per_user_limit'] ?? '1')) ?>" min="1" required>
                                </div>
                                <div class="field">
                                    <label for="v_start">Berlaku Mulai (WIB)</label>
                                    <input type="datetime-local" id="v_start" name="starts_at" value="<?= htmlspecialchars($editVoucher ? toDatetimeLocal($editVoucher['starts_at'] ?? null) : $defaultStartLocal) ?>" required>
                                </div>
                                <div class="field">
                                    <label for="v_end">Berlaku Sampai (WIB)</label>
                                    <input type="datetime-local" id="v_end" name="ends_at" value="<?= htmlspecialchars($editVoucher ? toDatetimeLocal($editVoucher['ends_at'] ?? null) : $defaultEndLocal) ?>" required>
                                </div>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary"><?= $editVoucher ? 'Simpan Perubahan' : '+ Buat Kode Voucher' ?></button>
                            </div>
                        </form>
                    </section>

                    <section class="card">
                        <div class="card-header">
                            <h2>Kode Voucher & Kuota</h2>
                        </div>
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Kode</th>
                                        <th>Kampanye</th>
                                        <th>Diskon</th>
                                        <th>Min. Belanja</th>
                                        <th>Kuota Terpakai</th>
                                        <th>Batas / Akun</th>
                                        <th>Periode (WIB)</th>
                                        <th>Status</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($vouchers)): ?>
                                        <tr><td colspan="9">Belum ada kode voucher.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($vouchers as $v): ?>
                                            <?php
                                            $vId = (int) ($v['id'] ?? 0);
                                            $cId = (int) ($v['campaign_id'] ?? 0);
                                            $cName = isset($campMap[$cId]) ? (string) ($campMap[$cId]['name'] ?? '-') : ('#' . $cId);
                                            $dType = strtolower((string) ($v['discount_type'] ?? 'fixed'));
                                            $dVal = (float) ($v['discount_value'] ?? 0);
                                            $maxD = isset($v['max_discount']) && $v['max_discount'] !== null ? (float) $v['max_discount'] : null;
                                            $minOrd = (float) ($v['min_order_amount'] ?? ($v['min_purchase'] ?? 0));
                                            $qTot = (int) ($v['quota_total'] ?? ($v['quota'] ?? 0));
                                            $qUsed = (int) ($v['quota_used'] ?? ($v['used_count'] ?? ($v['redeemed_count'] ?? 0)));
                                            $perLim = (int) ($v['per_user_limit'] ?? 1);
                                            $vSt = strtolower((string) ($v['status'] ?? 'active'));
                                            $vAct = ($vSt === 'active' || $vSt === 'aktif');

                                            $discText = ($dType === 'percent' || $dType === 'percentage')
                                                ? rtrim(rtrim(number_format($dVal, 2, '.', ''), '0'), '.') . '%' . ($maxD ? ' (maks ' . rupiah($maxD) . ')' : '')
                                                : rupiah($dVal);
                                            ?>
                                            <tr>
                                                <td><strong><?= htmlspecialchars((string) ($v['code'] ?? '-')) ?></strong></td>
                                                <td><?= htmlspecialchars($cName) ?></td>
                                                <td><?= htmlspecialchars($discText) ?></td>
                                                <td><?= rupiah($minOrd) ?></td>
                                                <td><?= $qUsed ?> / <?= $qTot ?></td>
                                                <td><?= $perLim ?>x</td>
                                                <td>
                                                    <?= htmlspecialchars(formatWibDisplay($v['starts_at'] ?? null, 'd M Y H:i')) ?>
                                                    &ndash;
                                                    <?= htmlspecialchars(formatWibDisplay($v['ends_at'] ?? null, 'd M Y H:i')) ?>
                                                </td>
                                                <td><span class="status-text <?= $vAct ? '' : 'inactive' ?>"><?= $vAct ? 'Aktif' : 'Nonaktif' ?></span></td>
                                                <td>
                                                    <div class="action-group">
                                                        <a href="monitor.php?tab=vouchers&edit_voucher=<?= $vId ?>" class="btn btn-secondary">Edit</a>
                                                        <form method="post" style="display:inline;" onsubmit="return confirm('Hapus voucher ini?');">
                                                            <input type="hidden" name="action" value="delete_voucher">
                                                            <input type="hidden" name="id" value="<?= $vId ?>">
                                                            <input type="hidden" name="redirect_tab" value="<?= htmlspecialchars($activeTab) ?>">
                                                            <button type="submit" class="btn btn-danger">Hapus</button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>
                <?php endif; ?>

                <?php if ($activeTab === 'dashboard' || $activeTab === 'transactions'): ?>
                    <section class="card">
                        <div class="card-header">
                            <h2>Riwayat Penukaran Voucher</h2>
                        </div>
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Waktu (WIB)</th>
                                        <th>Customer</th>
                                        <th>Kode Voucher</th>
                                        <th>Belanja Awal</th>
                                        <th>Diskon</th>
                                        <th>Total Bayar</th>
                                        <th>Ref. Pesanan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($redemptions)): ?>
                                        <tr><td colspan="7">Belum ada riwayat penukaran voucher.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($redemptions as $r): ?>
                                            <?php
                                            $origAmt = (float) ($r['order_amount'] ?? ($r['order_total'] ?? ($r['original_amount'] ?? 0)));
                                            $discAmt = (float) ($r['discount_amount'] ?? ($r['discount'] ?? 0));
                                            $finalAmt = isset($r['final_amount']) ? (float) $r['final_amount'] : (isset($r['final_total']) ? (float) $r['final_total'] : max(0, $origAmt - $discAmt));
                                            $vCodeRow = strtoupper((string) ($r['voucher_code'] ?? ($r['code'] ?? ($r['voucher']['code'] ?? ($r['voucher_id'] ?? '-')))));
                                            $custRow = (string) ($r['user_identifier'] ?? ($r['customer_id'] ?? '-'));
                                            $orderRow = (string) ($r['order_id'] ?? ($r['order_ref'] ?? ($r['reference_code'] ?? '-')));
                                            ?>
                                            <tr>
                                                <td><?= htmlspecialchars(formatWibDisplay($r['redeemed_at'] ?? ($r['created_at'] ?? null), 'd M Y H:i')) ?></td>
                                                <td><strong><?= htmlspecialchars($custRow) ?></strong></td>
                                                <td><?= htmlspecialchars($vCodeRow) ?></td>
                                                <td><?= rupiah($origAmt) ?></td>
                                                <td><?= rupiah($discAmt) ?></td>
                                                <td><strong><?= rupiah($finalAmt) ?></strong></td>
                                                <td><?= htmlspecialchars($orderRow) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>
                <?php endif; ?>

                <div class="welcome-strip">
                    <strong>Selamat datang kembali, Admin!</strong> Kelola kampanye dan pantau penggunaan voucher dengan mudah.
                </div>
            </main>
        </div>
    </div>
</body>
</html>