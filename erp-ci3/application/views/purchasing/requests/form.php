<?php $d = $doc ?? []; $items = old('items', $d['items'] ?? []); $errs = $this->session->flashdata('errors') ?: []; ?>
<form method="post" data-testid="pr-form" class="erp-doc-form"><?= csrf_field() ?>
<div class="card erp-card mb-3"><div class="card-body row g-2">
    <div class="col-md-3"><label class="form-label">Gudang Tujuan</label><select class="form-select form-select-sm" name="warehouse_id" id="warehouseSelect" data-testid="pr-warehouse"><option value="">— (opsional)</option><?php foreach ($warehouses as $w): ?><option value="<?= $w['id'] ?>" <?= (string) old('warehouse_id', $d['warehouse_id'] ?? '') === (string) $w['id'] ? 'selected' : '' ?>><?= e($w['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2"><label class="form-label">Tanggal *</label><input class="form-control form-control-sm" type="date" name="request_date" value="<?= e(old('request_date', $d['request_date'] ?? date('Y-m-d'))) ?>" required data-testid="pr-date"></div>
    <div class="col-md-2"><label class="form-label">Dibutuhkan</label><input class="form-control form-control-sm" type="date" name="required_date" value="<?= e(old('required_date', $d['required_date'] ?? '')) ?>"></div>
    <div class="col-md-5"><label class="form-label">Catatan</label><input class="form-control form-control-sm" name="notes" value="<?= e(old('notes', $d['notes'] ?? '')) ?>"></div>
</div></div>
<div class="card erp-card mb-3"><div class="card-header">Item</div>
<div class="table-responsive"><table class="table table-sm erp-table mb-0 erp-items" id="itemsTable" data-testid="pr-items"><thead><tr><th style="width:45%">Produk *</th><th class="text-end">Qty *</th><th class="text-end">Estimasi Harga</th><th>Catatan</th><th></th></tr></thead><tbody>
<?php foreach (array_merge($items, [[]]) as $i => $it): ?><tr>
    <td><input type="hidden" name="items[<?= $i ?>][product_id]" value="<?= e($it['product_id'] ?? '') ?>" class="js-product-id"><input class="form-control form-control-sm js-product-search" value="<?= e(isset($it['product_name']) ? $it['sku'] . ' — ' . $it['product_name'] : ($it['product_label'] ?? '')) ?>" placeholder="Cari produk…" autocomplete="off" data-testid="pr-item-product-<?= $i ?>"><input type="hidden" name="items[<?= $i ?>][product_label]" class="js-product-label"></td>
    <td><input class="form-control form-control-sm text-end" name="items[<?= $i ?>][qty]" value="<?= e($it['qty'] ?? '') ?>" inputmode="decimal" data-testid="pr-item-qty-<?= $i ?>"></td>
    <td><input class="form-control form-control-sm text-end" name="items[<?= $i ?>][estimated_price]" value="<?= e($it['estimated_price'] ?? '') ?>" inputmode="decimal"></td>
    <td><input class="form-control form-control-sm" name="items[<?= $i ?>][notes]" value="<?= e($it['notes'] ?? '') ?>"></td><td><button type="button" class="btn btn-xs btn-outline-danger row-remove"><i class="bi bi-x"></i></button></td></tr><?php endforeach; ?>
</tbody></table></div><div class="p-2"><button type="button" class="btn btn-xs btn-outline-secondary" data-add-row="#itemsTable" data-testid="pr-add-row"><i class="bi bi-plus"></i> Tambah baris</button><?php if (isset($errs['items'])): ?><span class="text-danger small ms-2"><?= e($errs['items']) ?></span><?php endif; ?></div></div>
<button class="btn btn-primary" data-testid="pr-save"><i class="bi bi-save me-1"></i>Simpan sebagai Draft</button> <a class="btn btn-link" href="<?= site_url('purchasing/requests') ?>">Batal</a>
</form>
