<?php
require_once __DIR__ . '/config.php';

$healthCheck = callKodePromoApi('GET', $apiBaseUrl . '/health', $apiKey);
$vouchersList = callKodePromoApi('GET', $apiBaseUrl . '/api/v1/vouchers', $apiKey);
$campaignsList = callKodePromoApi('GET', $apiBaseUrl . '/api/v1/campaigns', $apiKey);

$vouchers = $vouchersList['body']['data'] ?? [];
$campaigns = $campaignsList['body']['data'] ?? [];

$campaignReports = [];
$totalRedemptions = 0;
$totalOrderAmount = 0;
$totalDiscountGiven = 0;

foreach ($campaigns as $camp) {
    $rep = callKodePromoApi('GET', $apiBaseUrl . '/api/v1/campaigns/' . $camp['id'] . '/report', $apiKey);
    if (($rep['body']['status'] ?? '') === 'success') {
        $data = $rep['body']['data'];
        $campaignReports[] = $data;
        $totalRedemptions += (int) ($data['summary']['total_redemptions'] ?? 0);
        $totalOrderAmount += (float) ($data['summary']['total_order_amount'] ?? 0);
        $totalDiscountGiven += (float) ($data['summary']['total_discount_given'] ?? 0);
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KodePromo Portal Merchant — Monitor Kuota & Laporan</title>
    <style>
        :root { --bg: #0f172a; --card: #1e293b; --border: #334155; --text: #f8fafc; --muted: #94a3b8; --primary: #3b82f6; --success: #10b981; --danger: #ef4444; --warn: #f59e0b; }
        * { box-sizing: border-box; font-family: 'Segoe UI', system-ui, sans-serif; }
        body { margin: 0; background: var(--bg); color: var(--text); padding: 24px; }
        .container { max-width: 1120px; margin: 0 auto; }
        .navbar { display: flex; justify-content: space-between; align-items: center; background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 14px 22px; margin-bottom: 22px; flex-wrap: wrap; gap: 12px; }
        .nav-links { display: flex; gap: 10px; align-items: center; }
        .nav-tab { text-decoration: none; padding: 8px 14px; border-radius: 8px; font-size: 0.9rem; font-weight: 600; color: var(--muted); border: 1px solid transparent; }
        .nav-tab.active { background: rgba(59,130,246,0.18); color: #60a5fa; border-color: #2563eb; }
        .nav-tab:hover:not(.active) { color: var(--text); background: #0f172a; }
        .badge { padding: 5px 10px; border-radius: 999px; font-size: 0.78rem; font-weight: 600; }
        .badge-ok { background: rgba(16,185,129,0.18); color: #34d399; border: 1px solid #059669; }
        .badge-err { background: rgba(239,68,68,0.18); color: #f87171; border: 1px solid #dc2626; }
        .badge-warn { background: rgba(245,158,11,0.18); color: #fbbf24; border: 1px solid #d97706; }
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 22px; }
        @media (max-width: 850px) { .stats-grid { grid-template-columns: 1fr 1fr; } }
        .stat-card { background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 16px 18px; }
        .stat-card small { color: var(--muted); font-size: 0.8rem; display: block; margin-bottom: 6px; }
        .stat-card strong { font-size: 1.35rem; color: #38bdf8; }
        .card { background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 20px; margin-bottom: 20px; }
        .card-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); padding-bottom: 10px; margin-bottom: 14px; }
        .card-header h2 { margin: 0; font-size: 1.05rem; }
        .refresh-btn { text-decoration: none; background: var(--primary); color: #fff; padding: 7px 14px; border-radius: 8px; font-size: 0.85rem; font-weight: 600; }
        table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        th, td { text-align: left; padding: 11px 10px; border-bottom: 1px solid var(--border); }
        th { color: var(--muted); font-size: 0.78rem; text-transform: uppercase; }
        .progress-bg { background: #0f172a; border-radius: 999px; height: 8px; width: 120px; overflow: hidden; margin-top: 5px; }
        .progress-fill { background: var(--success); height: 100%; }
    </style>
</head>
<body>
<div class="container">
    <div class="navbar">
        <div>
            <strong style="font-size:1.15rem;">KodePromo Merchant Portal</strong>
            <div style="font-size:0.78rem; color:var(--muted);">Monitoring Kuota Voucher & Laporan Kampanye</div>
        </div>
        <div class="nav-links">
            <a href="index.php" class="nav-tab">Halaman Customer (Checkout)</a>
            <a href="monitor.php" class="nav-tab active">Portal Merchant (Monitor Kuota & Laporan)</a>
        </div>
        <div>
            <?php if (($healthCheck['body']['status'] ?? '') === 'ok'): ?>
                <span class="badge badge-ok">API Connected</span>
            <?php else: ?>
                <span class="badge badge-err">API Disconnected</span>
            <?php endif; ?>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <small>Total Voucher Terdaftar</small>
            <strong><?= count($vouchers) ?> Kode</strong>
        </div>
        <div class="stat-card">
            <small>Total Penukaran (Redemptions)</small>
            <strong><?= $totalRedemptions ?> Transaksi</strong>
        </div>
        <div class="stat-card">
            <small>Total Nilai Pesanan Promo</small>
            <strong><?= rupiah($totalOrderAmount) ?></strong>
        </div>
        <div class="stat-card">
            <small>Total Subsidi Diskon Diberikan</small>
            <strong style="color:#34d399;"><?= rupiah($totalDiscountGiven) ?></strong>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h2>1. Monitor Sisa Kuota Voucher (Live dari GET /api/v1/vouchers)</h2>
            <a href="monitor.php" class="refresh-btn">Refresh Data Live</a>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Kode Voucher</th>
                    <th>Kampanye</th>
                    <th>Nilai Diskon</th>
                    <th>Min. Belanja</th>
                    <th>Kuota Terpakai</th>
                    <th>Status</th>
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
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <div class="card-header">
            <h2>2. Ringkasan Laporan Per Kampanye (GET /api/v1/campaigns/{id}/report)</h2>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Nama Kampanye</th>
                    <th>Periode Berlaku</th>
                    <th>Jumlah Voucher</th>
                    <th>Total Redeem</th>
                    <th>Total Diskon Diberikan</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($campaignReports as $rep): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($rep['campaign']['name']) ?></strong></td>
                    <td>
                        <?= date('d M Y', strtotime($rep['campaign']['starts_at'])) ?> —
                        <?= date('d M Y', strtotime($rep['campaign']['ends_at'])) ?>
                    </td>
                    <td><?= (int) $rep['summary']['total_vouchers'] ?> voucher</td>
                    <td><strong><?= (int) $rep['summary']['total_redemptions'] ?>x</strong></td>
                    <td style="color:#34d399;"><strong><?= rupiah($rep['summary']['total_discount_given']) ?></strong></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>