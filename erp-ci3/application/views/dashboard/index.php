<div class="row g-3 mb-3" data-testid="dashboard-kpis">
    <?php $cards = [
        ['Nilai Persediaan', fmt_idr($kpi['valuation']['total_value']), fmt_num($kpi['valuation']['total_qty'], 0) . ' unit · ' . $kpi['valuation']['sku_count'] . ' SKU', 'bi-box-seam', 'primary'],
        ['Stok Kedaluwarsa', fmt_num($kpi['expiry']['expired_qty'] ?? 0, 0) . ' unit', fmt_idr($kpi['expiry']['expired_value'] ?? 0), 'bi-exclamation-octagon', 'danger'],
        ['Mendekati ED (≤' . $kpi['near_expiry_days'] . ' hari)', fmt_num($kpi['expiry']['near_qty'] ?? 0, 0) . ' unit', fmt_idr($kpi['expiry']['near_value'] ?? 0), 'bi-hourglass-split', 'warning'],
        ['Mutasi Hari Ini', $kpi['movements_today'], 'dokumen mutasi', 'bi-arrow-left-right', 'success'],
    ]; foreach ($cards as $i => [$t, $v, $s, $ic, $c]): ?>
    <div class="col-sm-6 col-xl-3"><div class="card erp-card erp-kpi border-start border-4 border-<?= $c ?>" data-testid="kpi-<?= $i ?>"><div class="card-body">
        <div class="d-flex justify-content-between"><div><div class="erp-kpi-label"><?= e($t) ?></div><div class="erp-kpi-value"><?= e($v) ?></div><div class="text-muted small"><?= e($s) ?></div></div><i class="bi <?= $ic ?> erp-kpi-icon text-<?= $c ?>"></i></div>
    </div></div></div>
    <?php endforeach; ?>
</div>
<div class="row g-3">
    <div class="col-lg-4"><div class="card erp-card h-100"><div class="card-header">Menunggu Tindakan</div><ul class="list-group list-group-flush" data-testid="pending-list">
        <li class="list-group-item d-flex justify-content-between"><a href="<?= site_url('inventory/adjustments?status=SUBMITTED') ?>">Penyesuaian menunggu persetujuan</a><span class="badge text-bg-info"><?= $kpi['pending']['adjustments'] ?></span></li>
        <li class="list-group-item d-flex justify-content-between"><a href="<?= site_url('inventory/transfers') ?>">Transfer diajukan / dalam perjalanan</a><span class="badge text-bg-info"><?= $kpi['pending']['transfers'] ?></span></li>
        <li class="list-group-item d-flex justify-content-between"><a href="<?= site_url('inventory/opnames') ?>">Opname berjalan</a><span class="badge text-bg-info"><?= $kpi['pending']['opnames'] ?></span></li>
    </ul></div></div>
    <div class="col-lg-4"><div class="card erp-card h-100"><div class="card-header">Stok di Bawah Reorder Point</div>
        <table class="table table-sm mb-0 erp-table" data-testid="low-stock-table"><thead><tr><th>Produk</th><th class="text-end">Stok</th><th class="text-end">ROP</th></tr></thead><tbody>
        <?php foreach ($kpi['low_stock'] as $r): ?><tr><td><a href="<?= site_url('inventory/stock/card/' . $r['id']) ?>"><?= e($r['name']) ?></a><br><small class="text-muted"><?= e($r['sku']) ?></small></td><td class="text-end text-danger fw-semibold"><?= fmt_num($r['qty_on_hand'], 0) ?></td><td class="text-end"><?= fmt_num($r['reorder_point'], 0) ?></td></tr><?php endforeach; ?>
        <?php if (!$kpi['low_stock']): ?><tr><td colspan="3" class="text-muted text-center py-3">Tidak ada</td></tr><?php endif; ?>
        </tbody></table></div></div>
    <div class="col-lg-4"><div class="card erp-card h-100"><div class="card-header">Mutasi Terakhir</div>
        <table class="table table-sm mb-0 erp-table" data-testid="recent-movements-table"><tbody>
        <?php foreach ($kpi['recent_movements'] as $m): ?><tr><td><a href="<?= site_url('inventory/movements/' . $m['id']) ?>" class="font-monospace small"><?= e($m['movement_no']) ?></a><br><small class="text-muted"><?= e($m['movement_type']) ?> · <?= $m['line_count'] ?> baris</small></td><td class="text-end small text-muted"><?= fmt_dt($m['posted_at']) ?><br><?= status_badge($m['status']) ?></td></tr><?php endforeach; ?>
        </tbody></table></div></div>
</div>
