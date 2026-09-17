<div class="erp-page-head"><h5 class="mb-0">Shift Kasir</h5></div>
<?php if ($current): ?>
<div class="card erp-card mb-3" data-testid="shift-current"><div class="card-body d-flex justify-content-between align-items-center">
    <div>Shift aktif: <strong class="font-monospace"><?= e($current['shift_no']) ?></strong> · dibuka <?= fmt_dt($current['opened_at']) ?> · kas awal <?= fmt_idr($current['opening_cash']) ?></div>
    <?php if (can('sales.shift.close')): ?><form method="post" action="<?= site_url('sales/shifts/' . $current['id'] . '/close') ?>" class="d-inline-flex gap-1 needs-confirm" data-confirm="Tutup shift ini? Rekap kas akan dihitung."><?= csrf_field() ?><input class="form-control form-control-sm" name="closing_cash" placeholder="Kas akhir (Rp)" inputmode="decimal" data-testid="shift-closing-cash"><button class="btn btn-sm btn-dark" data-testid="shift-close-btn">Tutup Shift</button></form><?php endif; ?>
</div></div>
<?php elseif (can('sales.shift.open')): ?>
<form method="post" action="<?= site_url('sales/shifts/open') ?>" class="card erp-card mb-3"><div class="card-body row g-2 align-items-end"><?= csrf_field() ?>
    <div class="col-md-4"><label class="form-label">Gudang/Etalase *</label><select class="form-select form-select-sm" name="warehouse_id" required data-testid="shift-warehouse"><option value="">—</option><?php foreach ($warehouses as $w): ?><option value="<?= $w['id'] ?>"><?= e($w['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-3"><label class="form-label">Kas Awal</label><input class="form-control form-control-sm" name="opening_cash" value="0" inputmode="decimal" data-testid="shift-opening-cash"></div>
    <div class="col-md-3"><button class="btn btn-primary btn-sm" data-testid="shift-open-btn"><i class="bi bi-unlock me-1"></i>Buka Shift</button></div>
</div></form>
<?php endif; ?>
<div class="card erp-card"><table class="table table-sm erp-table mb-0" data-testid="shift-table"><thead><tr><th>No. Shift</th><th>Kasir</th><th>Dibuka</th><th>Ditutup</th><th class="text-end">Total Penjualan</th><th class="text-end">Selisih Kas</th><th>Status</th></tr></thead><tbody>
<?php foreach ($page->items as $s): ?><tr><td class="font-monospace small"><a href="<?= site_url('sales/shifts/' . $s['id']) ?>"><?= e($s['shift_no']) ?></a></td><td class="small"><?= e($s['cashier_name']) ?></td><td class="small"><?= fmt_dt($s['opened_at']) ?></td><td class="small"><?= $s['closed_at'] ? fmt_dt($s['closed_at']) : '-' ?></td><td class="text-end"><?= fmt_idr($s['total_sales']) ?></td><td class="text-end"><?= $s['status'] === 'CLOSED' ? fmt_idr($s['closing_cash'] - $s['expected_cash']) : '-' ?></td><td><?= status_badge($s['status']) ?></td></tr><?php endforeach; ?>
<?php if (!$page->items): ?><tr><td colspan="7" class="text-center text-muted py-4">Belum ada shift</td></tr><?php endif; ?></tbody></table><div class="card-footer py-1"><?php $this->load->view('partials/pagination', ['page' => $page]); ?></div></div>
