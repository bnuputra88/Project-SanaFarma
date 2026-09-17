<?php $errs = $this->session->flashdata('errors') ?: []; ?>
<div class="erp-page-head"><h5 class="mb-0">POS — Transaksi Baru</h5><a class="btn btn-sm btn-link" href="<?= site_url('sales/pos') ?>">&larr; Daftar</a></div>
<?php if (!$current): ?><div class="alert alert-warning" data-testid="pos-no-shift">Belum ada shift kasir terbuka. Buka shift dulu di menu <a href="<?= site_url('sales/shifts') ?>">Shift Kasir</a> (transaksi tetap bisa dibuat tanpa shift, namun tidak terekap).</div><?php endif; ?>
<form method="post" data-testid="pos-form" class="erp-doc-form"><?= csrf_field() ?>
<div class="card erp-card mb-3"><div class="card-body row g-2">
    <div class="col-md-4"><label class="form-label">Gudang/Etalase *</label><select class="form-select form-select-sm" name="warehouse_id" id="warehouseSelect" required data-testid="pos-warehouse"><option value="">—</option><?php foreach ($warehouses as $w): ?><option value="<?= $w['id'] ?>" <?= (string) old('warehouse_id') === (string) $w['id'] ? 'selected' : '' ?>><?= e($w['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-4"><label class="form-label">Pelanggan (opsional)</label><input type="hidden" name="customer_id" class="js-customer-id" value="<?= e(old('customer_id')) ?>"><input class="form-control form-control-sm" name="customer_label" value="<?= e(old('customer_label')) ?>" placeholder="Umum / cari pelanggan"></div>
    <div class="col-md-4"><label class="form-label">Catatan</label><input class="form-control form-control-sm" name="notes" value="<?= e(old('notes')) ?>"></div>
</div></div>
<div class="card erp-card mb-3"><div class="card-header d-flex justify-content-between"><span>Item</span><small class="text-muted">Batch kosong = FEFO otomatis. Harga kosong = harga jual produk.</small></div>
<div class="table-responsive"><table class="table table-sm erp-table mb-0 erp-items" id="itemsTable" data-testid="pos-items"><thead><tr><th style="width:34%">Produk *</th><th>Batch</th><th class="text-end">Qty *</th><th class="text-end">Harga</th><th class="text-end">Disk%</th><th></th></tr></thead><tbody>
<?php foreach ([[]] as $i => $it): ?><tr>
    <td><input type="hidden" name="items[<?= $i ?>][product_id]" class="js-product-id"><input class="form-control form-control-sm js-product-search" placeholder="Cari produk / scan barcode…" autocomplete="off" data-testid="pos-item-product-<?= $i ?>"><input type="hidden" name="items[<?= $i ?>][product_label]" class="js-product-label"></td>
    <td><select class="form-select form-select-sm js-batch" name="items[<?= $i ?>][batch_id]"><option value="">FEFO otomatis</option></select></td>
    <td><input class="form-control form-control-sm text-end" name="items[<?= $i ?>][qty]" inputmode="decimal" data-testid="pos-item-qty-<?= $i ?>"></td>
    <td><input class="form-control form-control-sm text-end" name="items[<?= $i ?>][unit_price]" inputmode="decimal"></td>
    <td><input class="form-control form-control-sm text-end" name="items[<?= $i ?>][discount_pct]" inputmode="decimal"></td>
    <td><button type="button" class="btn btn-xs btn-outline-danger row-remove"><i class="bi bi-x"></i></button></td></tr><?php endforeach; ?>
</tbody></table></div><div class="p-2"><button type="button" class="btn btn-xs btn-outline-secondary" data-add-row="#itemsTable" data-testid="pos-add-row"><i class="bi bi-plus"></i> Tambah baris</button><?php if (isset($errs['items'])): ?><span class="text-danger small ms-2"><?= e($errs['items']) ?></span><?php endif; ?></div></div>
<div class="card erp-card mb-3"><div class="card-header">Pembayaran</div><div class="card-body row g-2">
    <div class="col-md-3"><label class="form-label">Metode</label><select class="form-select form-select-sm" name="payments[0][method]"><?php foreach (['CASH' => 'Tunai', 'CARD' => 'Kartu', 'TRANSFER' => 'Transfer', 'QRIS' => 'QRIS', 'OTHER' => 'Lainnya'] as $k => $l): ?><option value="<?= $k ?>"><?= $l ?></option><?php endforeach; ?></select></div>
    <div class="col-md-3"><label class="form-label">Jumlah Bayar *</label><input class="form-control form-control-sm text-end" name="payments[0][amount]" inputmode="decimal" data-testid="pos-pay-amount" required></div>
    <div class="col-md-3"><label class="form-label">Referensi</label><input class="form-control form-control-sm" name="payments[0][reference]"></div>
    <?php if (isset($errs['payments'])): ?><div class="col-12 text-danger small"><?= e($errs['payments']) ?></div><?php endif; ?>
</div></div>
<button class="btn btn-success" data-testid="pos-checkout"><i class="bi bi-cash-coin me-1"></i>Bayar & Selesaikan</button> <a class="btn btn-link" href="<?= site_url('sales/pos') ?>">Batal</a>
</form>
