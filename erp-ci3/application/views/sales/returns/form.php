<?php $errs = $this->session->flashdata('errors') ?: []; ?>
<div class="erp-page-head"><h5 class="mb-0">Retur Penjualan Baru</h5><a class="btn btn-sm btn-link" href="<?= site_url('sales/returns') ?>">&larr; Daftar</a></div>
<form method="post" data-testid="srt-form" class="erp-doc-form"><?= csrf_field() ?>
<div class="card erp-card mb-3"><div class="card-body row g-2">
    <div class="col-md-3"><label class="form-label">Gudang *</label><select class="form-select form-select-sm" name="warehouse_id" id="warehouseSelect" required data-testid="srt-warehouse"><option value="">—</option><?php foreach ($warehouses as $w): ?><option value="<?= $w['id'] ?>"><?= e($w['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2"><label class="form-label">Tanggal *</label><input class="form-control form-control-sm" type="date" name="return_date" value="<?= e(old('return_date', date('Y-m-d'))) ?>" required data-testid="srt-date"></div>
    <div class="col-md-3"><label class="form-label">Alasan *</label><select class="form-select form-select-sm <?= isset($errs['reason_code_id']) ? 'is-invalid' : '' ?>" name="reason_code_id" required data-testid="srt-reason"><option value="">—</option><?php foreach ($reasons as $r): ?><option value="<?= $r['id'] ?>"><?= e($r['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2"><label class="form-label">No. Penjualan (opsional)</label><input class="form-control form-control-sm" name="sale_id" value="<?= e(old('sale_id')) ?>" placeholder="ID sale"></div>
    <div class="col-md-2"><label class="form-label">Catatan</label><input class="form-control form-control-sm" name="notes" value="<?= e(old('notes')) ?>"></div>
</div></div>
<div class="card erp-card mb-3"><div class="card-header">Item</div>
<div class="table-responsive"><table class="table table-sm erp-table mb-0 erp-items" id="itemsTable" data-testid="srt-items"><thead><tr><th style="width:38%">Produk *</th><th>Batch</th><th class="text-end">Qty *</th><th class="text-end">Harga</th><th></th></tr></thead><tbody>
<?php foreach ([[]] as $i => $it): ?><tr>
    <td><input type="hidden" name="items[<?= $i ?>][product_id]" class="js-product-id"><input class="form-control form-control-sm js-product-search" placeholder="Cari produk…" autocomplete="off" data-testid="srt-item-product-<?= $i ?>"><input type="hidden" name="items[<?= $i ?>][product_label]" class="js-product-label"></td>
    <td><select class="form-select form-select-sm js-batch" name="items[<?= $i ?>][batch_id]"><option value="">(tanpa batch)</option></select></td>
    <td><input class="form-control form-control-sm text-end" name="items[<?= $i ?>][qty]" inputmode="decimal" data-testid="srt-item-qty-<?= $i ?>"></td>
    <td><input class="form-control form-control-sm text-end" name="items[<?= $i ?>][unit_price]" inputmode="decimal"></td>
    <td><button type="button" class="btn btn-xs btn-outline-danger row-remove"><i class="bi bi-x"></i></button></td></tr><?php endforeach; ?>
</tbody></table></div><div class="p-2"><button type="button" class="btn btn-xs btn-outline-secondary" data-add-row="#itemsTable" data-testid="srt-add-row"><i class="bi bi-plus"></i> Tambah baris</button><?php if (isset($errs['items'])): ?><span class="text-danger small ms-2"><?= e($errs['items']) ?></span><?php endif; ?></div></div>
<button class="btn btn-primary" data-testid="srt-save"><i class="bi bi-save me-1"></i>Simpan sebagai Draft</button> <a class="btn btn-link" href="<?= site_url('sales/returns') ?>">Batal</a>
</form>
