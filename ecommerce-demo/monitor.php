<?php
require_once __DIR__ . '/config.php';

$flashMessage = null;
$flashType = 'ok';

// Proses aksi CRUD Kampanye & Voucher dari Portal Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $crudAction = $_POST['crud_action'] ?? '';

    if ($crudAction === 'create_campaign') {
        $resp = callKodePromoApi('POST', $apiBaseUrl . '/api/v1/campaigns', $apiKey, [
            'name' => trim((string) $_POST['name']),
            'starts_at' => toIsoWib((string) $_POST['starts_at']),
            'ends_at' => toIsoWib((string) $_POST['ends_at']),
            'is_active' => isset($_POST['is_active']),
        ]);
        $flashType = ($resp['body']['status'] ?? '') === 'success' ? 'ok' : 'err';
        $flashMessage = $resp['body']['message'] ?? json_encode($resp['body']);
    } elseif ($crudAction === 'update_campaign') {
        $id = (int) $_POST['campaign_id'];
        $resp = callKodePromoApi('PUT', $apiBaseUrl . '/api/v1/campaigns/' . $id, $apiKey, [
            'name' => trim((string) $_POST['name']),
            'starts_at' => toIsoWib((string) $_POST['starts_at']),
            'ends_at' => toIsoWib((string) $_POST['ends_at']),
            'is_active' => isset($_POST['is_active']),
        ]);
        $flashType = ($resp['body']['status'] ?? '') === 'success' ? 'ok' : 'err';
        $flashMessage = $resp['body']['message'] ?? json_encode($resp['body']);
    } elseif ($crudAction === 'delete_campaign') {
        $id = (int) $_POST['campaign_id'];
        $resp = callKodePromoApi('DELETE', $apiBaseUrl . '/api/v1/campaigns/' . $id, $apiKey);
        $flashType = ($resp['body']['status'] ?? '') === 'success' ? 'ok' : 'err';
        $flashMessage = $resp['body']['message'] ?? json_encode($resp['body']);
    } elseif ($crudAction === 'create_voucher') {
        $discountType = (string) $_POST['discount_type'];
        $maxDisc = $_POST['max_discount'] !== '' ? (float) $_POST['max_discount'] : null;
        $quotaTotal = $_POST['quota_total'] !== '' ? (int) $_POST['quota_total'] : null;

        $resp = callKodePromoApi('POST', $apiBaseUrl . '/api/v1/vouchers', $apiKey, [
            'campaign_id' => (int) $_POST['campaign_id'],
            'code' => strtoupper(trim((string) $_POST['code'])),
            'discount_type' => $discountType,
            'discount_value' => (float) $_POST['discount_value'],
            'max_discount' => $discountType === 'percentage' ? $maxDisc : null,
            'min_order_amount' => (float) ($_POST['min_order_amount'] ?: 0),
            'quota_total' => $quotaTotal,
            'max_uses_per_user' => max(1, (int) ($_POST['max_uses_per_user'] ?: 1)),
            'is_active' => isset($_POST['is_active']),
        ]);
        $flashType = ($resp['body']['status'] ?? '') === 'success' ? 'ok' : 'err';
        $flashMessage = $resp['body']['message'] ?? json_encode($resp['body']);
    } elseif ($crudAction === 'update_voucher') {
        $id = (int) $_POST['voucher_id'];
        $discountType = (string) $_POST['discount_type'];
        $maxDisc = $_POST['max_discount'] !== '' ? (float) $_POST['max_discount'] : null;
        $quotaTotal = $_POST['quota_total'] !== '' ? (int) $_POST['quota_total'] : null;

        $resp = callKodePromoApi('PUT', $apiBaseUrl . '/api/v1/vouchers/' . $id, $apiKey, [
            'campaign_id' => (int) $_POST['campaign_id'],
            'code' => strtoupper(trim((string) $_POST['code'])),
            'discount_type' => $discountType,
            'discount_value' => (float) $_POST['discount_value'],
            'max_discount' => $discountType === 'percentage' ? $maxDisc : null,
            'min_order_amount' => (float) ($_POST['min_order_amount'] ?: 0),
            'quota_total' => $quotaTotal,
            'max_uses_per_user' => max(1, (int) ($_POST['max_uses_per_user'] ?: 1)),
            'is_active' => isset($_POST['is_active']),
        ]);
        $flashType = ($resp['body']['status'] ?? '') === 'success' ? 'ok' : 'err';
        $flashMessage = $resp['body']['message'] ?? json_encode($resp['body']);
    } elseif ($crudAction === 'delete_voucher') {
        $id = (int) $_POST['voucher_id'];
        $resp = callKodePromoApi('DELETE', $apiBaseUrl . '/api/v1/vouchers/' . $id, $apiKey);
        $flashType = ($resp['body']['status'] ?? '') === 'success' ? 'ok' : 'err';
        $flashMessage = $resp['body']['message'] ?? json_encode($resp['body']);
    }
}

$healthCheck = callKodePromoApi('GET', $apiBaseUrl . '/health', $apiKey);
$campaignsList = callKodePromoApi('GET', $apiBaseUrl . '/api/v1/campaigns', $apiKey);
$vouchersList = callKodePromoApi('GET', $apiBaseUrl . '/api/v1/vouchers', $apiKey);
$redemptionsList = callKodePromoApi('GET', $apiBaseUrl . '/api/v1/redemptions', $apiKey);

$campaigns = $campaignsList['body']['data'] ?? [];
$vouchers = $vouchersList['body']['data'] ?? [];
$redemptions = $redemptionsList['body']['data'] ?? [];

$campaignReportsById = [];
$totalRedemptions = 0;
$totalOrderAmount = 0;
$totalDiscountGiven = 0;

foreach ($campaigns as $camp) {
    $rep = callKodePromoApi('GET', $apiBaseUrl . '/api/v1/campaigns/' . $camp['id'] . '/report', $apiKey);
    if (($rep['body']['status'] ?? '') === 'success') {
        $data = $rep['body']['data'];
        $campaignReportsById[$camp['id']] = $data;
        $totalRedemptions += (int) ($data['summary']['total_redemptions'] ?? 0);
        $totalOrderAmount += (float) ($data['summary']['total_order_amount'] ?? 0);
        $totalDiscountGiven += (float) ($data['summary']['total_discount_given'] ?? 0);
    }
}

// Mode edit jika admin menekan tombol Edit pada tabel
$editCampaignId = isset($_GET['edit_campaign']) ? (int) $_GET['edit_campaign'] : null;
$editingCampaign = null;
foreach ($campaigns as $c) {
    if ((int) $c['id'] === $editCampaignId) {
        $editingCampaign = $c;
        break;
    }
}

$editVoucherId = isset($_GET['edit_voucher']) ? (int) $_GET['edit_voucher'] : null;
$editingVoucher = null;
foreach ($vouchers as $v) {
    if ((int) $v['id'] === $editVoucherId) {
        $editingVoucher = $v;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KodePromo Portal Admin — Full CRUD Kampanye, Voucher & Laporan</title>
    <style>
        :root { --bg: #0f172a; --card: #1e293b; --border: #334155; --text: #f8fafc; --muted: #94a3b8; --primary: #3b82f6; --success: #10b981; --danger: #ef4444; --warn: #f59e0b; }
        * { box-sizing: border-box; font-family: 'Segoe UI', system-ui, sans-serif; }
        body { margin: 0; background: var(--bg); color: var(--text); padding: 24px; }
        .container { max-width: 1240px; margin: 0 auto; }
        .navbar { display: flex; justify-content: space-between; align-items: center; background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 14px 22px; margin-bottom: 20px; flex-wrap: wrap; gap: 12px; }
        .nav-links { display: flex; gap: 10px; align-items: center; }
        .nav-tab { text-decoration: none; padding: 8px 14px; border-radius: 8px; font-size: 0.9rem; font-weight: 600; color: var(--muted); border: 1px solid transparent; }
        .nav-tab.active { background: rgba(59,130,246,0.18); color: #60a5fa; border-color: #2563eb; }
        .nav-tab:hover:not(.active) { color: var(--text); background: #0f172a; }
        .badge { padding: 4px 10px; border-radius: 999px; font-size: 0.76rem; font-weight: 600; display: inline-block; }
        .badge-ok { background: rgba(16,185,129,0.18); color: #34d399; border: 1px solid #059669; }
        .badge-err { background: rgba(239,68,68,0.18); color: #f87171; border: 1px solid #dc2626; }
        .badge-warn { background: rgba(245,158,11,0.18); color: #fbbf24; border: 1px solid #d97706; }
        .flash { padding: 12px 16px; border-radius: 10px; margin-bottom: 18px; font-size: 0.9rem; font-weight: 600; }
        .flash-ok { background: rgba(16,185,129,0.18); border: 1px solid #059669; color: #6ee7b7; }
        .flash-err { background: rgba(239,68,68,0.18); border: 1px solid #dc2626; color: #fca5a5; }
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 20px; }
        @media (max-width: 900px) { .stats-grid { grid-template-columns: 1fr 1fr; } }
        .stat-card { background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 16px 18px; }
        .stat-card small { color: var(--muted); font-size: 0.78rem; display: block; margin-bottom: 6px; }
        .stat-card strong { font-size: 1.32rem; color: #38bdf8; }
        .card { background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 20px; margin-bottom: 22px; }
        .card-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); padding-bottom: 10px; margin-bottom: 16px; }
        .card-header h2 { margin: 0; font-size: 1.05rem; }
        .crud-box { background: #0f172a; border: 1px solid var(--border); border-radius: 10px; padding: 16px; margin-bottom: 18px; }
        .crud-box h3 { margin: 0 0 12px; font-size: 0.95rem; color: #38bdf8; }
        .form-grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; }
        @media (max-width: 950px) { .form-grid-4 { grid-template-columns: 1fr 1fr; } }
        label { display: block; font-size: 0.78rem; color: var(--muted); margin-bottom: 4px; }
        input[type="text"], input[type="number"], input[type="datetime-local"], select { background: #1e293b; border: 1px solid var(--border); color: var(--text); padding: 8px 10px; border-radius: 7px; width: 100%; font-size: 0.86rem; }
        .btn { border: none; padding: 8px 13px; border-radius: 7px; font-weight: 600; cursor: pointer; font-size: 0.82rem; text-decoration: none; display: inline-block; }
        .btn-primary { background: var(--primary); color: #fff; }
        .btn-success { background: var(--success); color: #052e16; }
        .btn-warn { background: var(--warn); color: #451a03; }
        .btn-danger { background: var(--danger); color: #fff; }
        .btn-muted { background: #334155; color: #e2e8f0; }
        table { width: 100%; border-collapse: collapse; font-size: 0.86rem; }
        th, td { text-align: left; padding: 10px 8px; border-bottom: 1px solid var(--border); vertical-align: middle; }
        th { color: var(--muted); font-size: 0.75rem; text-transform: uppercase; }
        .actions-cell { display: flex; gap: 6px; flex-wrap: wrap; }
        .progress-bg { background: #0f172a; border-radius: 999px; height: 7px; width: 100px; overflow: hidden; margin-top: 4px; }
        .progress-fill { background: var(--success); height: 100%; }
    </style>
</head>
<body>
<div class="container">
    <div class="navbar">
        <div>
            <strong style="font-size:1.15rem;">KodePromo Owner / Admin Portal</strong>
            <div style="font-size:0.78rem; color:var(--muted);">Full CRUD Kampanye, Pengaturan Kuota & Tanggal Voucher, Serta Audit Pemakaian</div>
        </div>
        <div class="nav-links">
            <a href="index.php" class="nav-tab">Halaman Customer (Checkout)</a>
            <a href="monitor.php" class="nav-tab active">Portal Admin (CRUD & Laporan)</a>
        </div>
        <div>
            <?php if (($healthCheck['body']['status'] ?? '') === 'ok'): ?>
                <span class="badge badge-ok">API Connected</span>
            <?php else: ?>
                <span class="badge badge-err">API Disconnected</span>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($flashMessage !== null): ?>
        <div class="flash <?= $flashType === 'ok' ? 'flash-ok' : 'flash-err' ?>">
            <?= htmlspecialchars($flashMessage) ?>
        </div>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card">
            <small>Total Kampanye</small>
            <strong><?= count($campaigns) ?> Kampanye</strong>
        </div>
        <div class="stat-card">
            <small>Total Kode Voucher</small>
            <strong><?= count($vouchers) ?> Voucher</strong>
        </div>
        <div class="stat-card">
            <small>Total Penukaran (Redemptions)</small>
            <strong><?= $totalRedemptions ?> Transaksi</strong>
        </div>
        <div class="stat-card">
            <small>Total Subsidi Diskon Diberikan</small>
            <strong style="color:#34d399;"><?= rupiah($totalDiscountGiven) ?></strong>
        </div>
    </div>

    <!-- BAGIAN 1: CRUD KAMPANYE & LAPORAN PER KAMPANYE -->
    <div class="card">
        <div class="card-header">
            <h2>1. Manajemen Kampanye & Laporan Pemakaian (CRUD `/api/v1/campaigns`)</h2>
            <a href="monitor.php" class="btn btn-muted">Refresh Data</a>
        </div>

        <form method="POST" action="monitor.php" class="crud-box">
            <h3><?= $editingCampaign ? 'Edit Kampanye #' . $editingCampaign['id'] . ' (' . htmlspecialchars($editingCampaign['name']) . ')' : '+ Buat Kampanye Promo Baru' ?></h3>
            <?php if ($editingCampaign): ?>
                <input type="hidden" name="crud_action" value="update_campaign">
                <input type="hidden" name="campaign_id" value="<?= (int) $editingCampaign['id'] ?>">
            <?php else: ?>
                <input type="hidden" name="crud_action" value="create_campaign">
            <?php endif; ?>

            <div class="form-grid-4">
                <div>
                    <label>Nama Kampanye</label>
                    <input type="text" name="name" value="<?= htmlspecialchars($editingCampaign['name'] ?? '') ?>" placeholder="Contoh: Promo Akhir Bulan" required>
                </div>
                <div>
                    <label>Tanggal Mulai (Start)</label>
                    <input type="datetime-local" name="starts_at" value="<?= $editingCampaign ? toDatetimeLocal($editingCampaign['starts_at']) : date('Y-m-d\TH:i') ?>" required>
                </div>
                <div>
                    <label>Tanggal Berakhir (End)</label>
                    <input type="datetime-local" name="ends_at" value="<?= $editingCampaign ? toDatetimeLocal($editingCampaign['ends_at']) : date('Y-m-d\TH:i', strtotime('+14 days')) ?>" required>
                </div>
                <div>
                    <label>Status & Aksi</label>
                    <div style="display:flex; gap:10px; align-items:center; margin-top:4px;">
                        <label style="margin:0; color:#e2e8f0; display:flex; align-items:center; gap:5px;">
                            <input type="checkbox" name="is_active" <?= (!$editingCampaign || !empty($editingCampaign['is_active'])) ? 'checked' : '' ?>> Aktif
                        </label>
                        <button type="submit" class="btn <?= $editingCampaign ? 'btn-warn' : 'btn-success' ?>">
                            <?= $editingCampaign ? 'Simpan Perubahan' : 'Tambah Kampanye' ?>
                        </button>
                        <?php if ($editingCampaign): ?>
                            <a href="monitor.php" class="btn btn-muted">Batal</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </form>

        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nama Kampanye</th>
                    <th>Rentang Tanggal Berlaku</th>
                    <th>Status</th>
                    <th>Total Redeem</th>
                    <th>Total Diskon</th>
                    <th>Aksi CRUD</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($campaigns as $c):
                $rep = $campaignReportsById[$c['id']] ?? null;
                $now = time();
                $isExp = strtotime($c['ends_at']) < $now;
            ?>
                <tr>
                    <td>#<?= (int) $c['id'] ?></td>
                    <td><strong><?= htmlspecialchars($c['name']) ?></strong></td>
                    <td>
                        <?= date('d M Y H:i', strtotime($c['starts_at'])) ?> —<br>
                        <?= date('d M Y H:i', strtotime($c['ends_at'])) ?>
                    </td>
                    <td>
                        <?php if ($isExp): ?>
                            <span class="badge badge-err">Expired</span>
                        <?php elseif (!empty($c['is_active'])): ?>
                            <span class="badge badge-ok">Aktif</span>
                        <?php else: ?>
                            <span class="badge badge-warn">Nonaktif</span>
                        <?php endif; ?>
                    </td>
                    <td><strong><?= (int) ($rep['summary']['total_redemptions'] ?? 0) ?>x</strong></td>
                    <td style="color:#34d399;"><strong><?= rupiah($rep['summary']['total_discount_given'] ?? 0) ?></strong></td>
                    <td class="actions-cell">
                        <a href="monitor.php?edit_campaign=<?= (int) $c['id'] ?>" class="btn btn-warn">Edit</a>
                        <form method="POST" action="monitor.php" onsubmit="return confirm('Hapus kampanye ini beserta seluruh vouchernya?');">
                            <input type="hidden" name="crud_action" value="delete_campaign">
                            <input type="hidden" name="campaign_id" value="<?= (int) $c['id'] ?>">
                            <button type="submit" class="btn btn-danger">Hapus</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- BAGIAN 2: CRUD KODE VOUCHER & MONITOR KUOTA -->
    <div class="card">
        <div class="card-header">
            <h2>2. Manajemen Kode Voucher & Kuota Maksimal (CRUD `/api/v1/vouchers`)</h2>
        </div>

        <form method="POST" action="monitor.php" class="crud-box">
            <h3><?= $editingVoucher ? 'Edit Voucher #' . $editingVoucher['id'] . ' (' . htmlspecialchars($editingVoucher['code']) . ')' : '+ Buat Kode Voucher Baru' ?></h3>
            <?php if ($editingVoucher): ?>
                <input type="hidden" name="crud_action" value="update_voucher">
                <input type="hidden" name="voucher_id" value="<?= (int) $editingVoucher['id'] ?>">
            <?php else: ?>
                <input type="hidden" name="crud_action" value="create_voucher">
            <?php endif; ?>

            <div class="form-grid-4" style="margin-bottom:12px;">
                <div>
                    <label>Kampanye Induk</label>
                    <select name="campaign_id" required>
                        <?php foreach ($campaigns as $c): ?>
                            <option value="<?= (int) $c['id'] ?>" <?= ($editingVoucher && (int) $editingVoucher['campaign_id'] === (int) $c['id']) ? 'selected' : '' ?>>
                                #<?= (int) $c['id'] ?> — <?= htmlspecialchars($c['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Kode Voucher</label>
                    <input type="text" name="code" value="<?= htmlspecialchars($editingVoucher['code'] ?? '') ?>" placeholder="Contoh: GAJIAN30" required>
                </div>
                <div>
                    <label>Tipe Diskon</label>
                    <select name="discount_type" required>
                        <option value="percentage" <?= ($editingVoucher['discount_type'] ?? '') === 'percentage' ? 'selected' : '' ?>>Persentase (%)</option>
                        <option value="fixed" <?= ($editingVoucher['discount_type'] ?? '') === 'fixed' ? 'selected' : '' ?>>Nominal Tetap (Rp)</option>
                    </select>
                </div>
                <div>
                    <label>Nilai Diskon (% atau Rp)</label>
                    <input type="number" step="0.01" name="discount_value" value="<?= htmlspecialchars((string) ($editingVoucher['discount_value'] ?? '25')) ?>" required>
                </div>
            </div>

            <div class="form-grid-4">
                <div>
                    <label>Maks. Potongan (Khusus %)</label>
                    <input type="number" step="0.01" name="max_discount" value="<?= htmlspecialchars((string) ($editingVoucher['max_discount'] ?? '40000')) ?>" placeholder="Kosongkan jika nominal">
                </div>
                <div>
                    <label>Minimal Belanja (Rp)</label>
                    <input type="number" step="0.01" name="min_order_amount" value="<?= htmlspecialchars((string) ($editingVoucher['min_order_amount'] ?? '150000')) ?>" required>
                </div>
                <div>
                    <label>Kuota Maksimal Voucher</label>
                    <input type="number" min="1" name="quota_total" value="<?= htmlspecialchars((string) ($editingVoucher['quota_total'] ?? '50')) ?>" placeholder="Kosongkan jika unlimited">
                </div>
                <div>
                    <label>Batas/User & Aksi</label>
                    <div style="display:flex; gap:8px; align-items:center;">
                        <input type="number" min="1" name="max_uses_per_user" value="<?= (int) ($editingVoucher['max_uses_per_user'] ?? 1) ?>" style="width:58px;" title="Batas pemakaian per customer">
                        <label style="margin:0; color:#e2e8f0; display:flex; align-items:center; gap:4px;">
                            <input type="checkbox" name="is_active" <?= (!$editingVoucher || !empty($editingVoucher['is_active'])) ? 'checked' : '' ?>> Aktif
                        </label>
                        <button type="submit" class="btn <?= $editingVoucher ? 'btn-warn' : 'btn-primary' ?>">
                            <?= $editingVoucher ? 'Update' : 'Simpan' ?>
                        </button>
                        <?php if ($editingVoucher): ?>
                            <a href="monitor.php" class="btn btn-muted">Batal</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </form>

        <table>
            <thead>
                <tr>
                    <th>Kode</th>
                    <th>Kampanye</th>
                    <th>Diskon</th>
                    <th>Min. Belanja</th>
                    <th>Kuota Terpakai</th>
                    <th>Batas/User</th>
                    <th>Status</th>
                    <th>Aksi CRUD</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($vouchers as $v):
                $used = (int) $v['quota_used'];
                $total = $v['quota_total'] !== null ? (int) $v['quota_total'] : null;
                $pct = ($total && $total > 0) ? min(100, round(($used / $total) * 100)) : 0;
                $isExpired = isset($v['campaign']['ends_at']) && strtotime($v['campaign']['ends_at']) < time();
                $isSoldOut = ($total !== null && $used >= $total);
            ?>
                <tr>
                    <td><strong><?= htmlspecialchars($v['code']) ?></strong></td>
                    <td><?= htmlspecialchars($v['campaign']['name'] ?? '-') ?></td>
                    <td>
                        <?= $v['discount_type'] === 'percentage'
                            ? $v['discount_value'] . '% (maks ' . rupiah($v['max_discount'] ?? 0) . ')'
                            : rupiah($v['discount_value']) ?>
                    </td>
                    <td><?= rupiah($v['min_order_amount']) ?></td>
                    <td>
                        <strong><?= $used ?> / <?= $total ?? '∞' ?></strong>
                        <?php if ($total !== null): ?>
                            <div class="progress-bg">
                                <div class="progress-fill" style="width: <?= $pct ?>%; background: <?= $isSoldOut ? '#ef4444' : '#10b981' ?>;"></div>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td><?= (int) ($v['max_uses_per_user'] ?? 1) ?>x</td>
                    <td>
                        <?php if ($isExpired): ?>
                            <span class="badge badge-err">Expired</span>
                        <?php elseif ($isSoldOut): ?>
                            <span class="badge badge-warn">Kuota Habis</span>
                        <?php elseif (!empty($v['is_active'])): ?>
                            <span class="badge badge-ok">Aktif</span>
                        <?php else: ?>
                            <span class="badge badge-err">Nonaktif</span>
                        <?php endif; ?>
                    </td>
                    <td class="actions-cell">
                        <a href="monitor.php?edit_voucher=<?= (int) $v['id'] ?>" class="btn btn-warn">Edit</a>
                        <form method="POST" action="monitor.php" onsubmit="return confirm('Hapus voucher <?= htmlspecialchars($v['code']) ?>?');">
                            <input type="hidden" name="crud_action" value="delete_voucher">
                            <input type="hidden" name="voucher_id" value="<?= (int) $v['id'] ?>">
                            <button type="submit" class="btn btn-danger">Hapus</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- BAGIAN 3: AUDIT LOG PEMAKAIAN PER CUSTOMER -->
    <div class="card">
        <div class="card-header">
            <h2>3. Log Penukaran Customer (Bukti Batas 1x Pemakaian per Akun)</h2>
        </div>
        <?php if (empty($redemptions)): ?>
            <p style="color:var(--muted); font-size:0.88rem;">Belum ada transaksi redeem. Silakan coba transaksi di <strong>Halaman Customer (Checkout)</strong>.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ID Akun & Nama Customer</th>
                        <th>Kode Voucher</th>
                        <th>Order ID</th>
                        <th>Total Belanja</th>
                        <th>Potongan</th>
                        <th>Waktu Redeem</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($redemptions as $r): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($r['user_identifier']) ?></strong></td>
                        <td><span class="badge badge-ok"><?= htmlspecialchars($r['voucher']['code'] ?? '-') ?></span></td>
                        <td><?= htmlspecialchars($r['order_id']) ?></td>
                        <td><?= rupiah($r['order_amount']) ?></td>
                        <td style="color:#34d399;">- <?= rupiah($r['discount_amount']) ?></td>
                        <td><?= date('d M Y H:i:s', strtotime($r['redeemed_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
</body>
</html>