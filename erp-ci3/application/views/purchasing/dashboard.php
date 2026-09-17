<?php $d = $data; ?>
<div class="erp-page-head"><h5 class="mb-0">Ringkasan Pembelian</h5></div>
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><div class="card erp-card"><div class="card-body"><div class="text-muted small">PR Menunggu Persetujuan</div><div class="fs-3 fw-semibold" data-testid="kpi-pr-pending"><?= (int) $d['pr_pending'] ?></div><a class="small" href="<?= site_url('purchasing/requests?status=SUBMITTED') ?>">Lihat PR &rarr;</a></div></div></div>
    <div class="col-6 col-lg-3"><div class="card erp-card"><div class="card-body"><div class="text-muted small">PO Terbuka</div><div class="fs-3 fw-semibold" data-testid="kpi-po-open"><?= (int) $d['po_open'] ?></div><a class="small" href="<?= site_url('purchasing/orders?status=ORDERED') ?>">Lihat PO &rarr;</a></div></div></div>
    <div class="col-6 col-lg-3"><div class="card erp-card"><div class="card-body"><div class="text-muted small">GR Belum Diposting</div><div class="fs-3 fw-semibold" data-testid="kpi-gr-pending"><?= (int) $d['gr_pending'] ?></div><a class="small" href="<?= site_url('purchasing/receipts') ?>">Lihat GR &rarr;</a></div></div></div>
    <div class="col-6 col-lg-3"><div class="card erp-card"><div class="card-body"><div class="text-muted small">Hutang AP Terbuka (<?= (int) $d['ap_open_count'] ?>)</div><div class="fs-4 fw-semibold" data-testid="kpi-ap-outstanding"><?= fmt_idr($d['ap_outstanding']) ?></div><a class="small" href="<?= site_url('purchasing/ap-invoices?status=OPEN') ?>">Lihat AP &rarr;</a></div></div></div>
</div>
<div class="row g-3">
    <div class="col-lg-7"><div class="card erp-card"><div class="card-header py-1 small fw-semibold">PO Terbuka — Sisa Belum Diterima</div>
    <table class="table table-sm erp-table mb-0" data-testid="dash-open-po"><thead><tr><th>PO</th><th>Supplier</th><th>Produk</th><th class="text-end">Sisa</th></tr></thead><tbody>
    <?php foreach ($d['open_po_lines'] as $r): ?><tr><td class="font-monospace small"><a href="<?= site_url('purchasing/orders/' . $r['id']) ?>"><?= e($r['po_no']) ?></a></td><td class="small"><?= e($r['supplier_name']) ?></td><td class="small"><?= e($r['product_name']) ?> <span class="text-muted font-monospace">(<?= e($r['sku']) ?>)</span></td><td class="text-end"><?= fmt_num($r['outstanding'], 0) ?></td></tr><?php endforeach; ?>
    <?php if (!$d['open_po_lines']): ?><tr><td colspan="4" class="text-center text-muted py-3">Tidak ada PO terbuka</td></tr><?php endif; ?></tbody></table></div>
    <div class="card erp-card mt-3"><div class="card-header py-1 small fw-semibold">GR Menunggu Inspeksi / Posting</div>
    <table class="table table-sm erp-table mb-0" data-testid="dash-gr-inspect"><thead><tr><th>GR</th><th>Tanggal</th><th>Supplier</th><th>Status</th></tr></thead><tbody>
    <?php foreach ($d['gr_inspect'] as $r): ?><tr><td class="font-monospace small"><a href="<?= site_url('purchasing/receipts/' . $r['id']) ?>"><?= e($r['gr_no']) ?></a></td><td class="small"><?= fmt_date($r['receipt_date']) ?></td><td class="small"><?= e($r['supplier_name']) ?></td><td><?= status_badge($r['status']) ?></td></tr><?php endforeach; ?>
    <?php if (!$d['gr_inspect']): ?><tr><td colspan="4" class="text-center text-muted py-3">Tidak ada GR tertunda</td></tr><?php endif; ?></tbody></table></div></div>
    <div class="col-lg-5"><div class="card erp-card"><div class="card-header py-1 small fw-semibold">Varian Harga (GR ≠ PO)</div>
    <table class="table table-sm erp-table mb-0" data-testid="dash-variances"><thead><tr><th>Tgl</th><th>Produk</th><th class="text-end">Harga GR</th><th class="text-end">Harga PO</th></tr></thead><tbody>
    <?php foreach ($d['variances'] as $r): ?><tr><td class="small"><?= fmt_date($r['effective_date']) ?></td><td class="small font-monospace"><?= e($r['sku']) ?></td><td class="text-end small"><?= fmt_idr($r['price']) ?></td><td class="text-end small text-muted"><?= fmt_idr($r['po_price']) ?></td></tr><?php endforeach; ?>
    <?php if (!$d['variances']): ?><tr><td colspan="4" class="text-center text-muted py-3">Tidak ada varian</td></tr><?php endif; ?></tbody></table></div></div>
</div>
