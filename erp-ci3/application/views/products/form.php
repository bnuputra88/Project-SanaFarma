<?php $p = $product ?? []; $v = fn($k, $d = '') => e(old($k, $p[$k] ?? $d)); $chk = fn($k, $d = 0) => old($k, $p[$k] ?? $d) ? 'checked' : ''; $errs = $this->session->flashdata('errors') ?: [];
$sel = fn($k, $val) => (string) old($k, $p[$k] ?? '') === (string) $val ? 'selected' : '';
$inp = function ($name, $label, $type = 'text', $extra = '') use ($v, $errs) { return '<label class="form-label">' . $label . '</label><input class="form-control form-control-sm ' . (isset($errs[$name]) ? 'is-invalid' : '') . '" type="' . $type . '" name="' . $name . '" value="' . $v($name) . '" ' . $extra . ' data-testid="product-' . $name . '"><div class="invalid-feedback">' . e($errs[$name] ?? '') . '</div>'; }; ?>
<form method="post" class="row g-3" data-testid="product-form"><?= csrf_field() ?>
<div class="col-lg-8">
<div class="card erp-card mb-3"><div class="card-header">Identitas Produk</div><div class="card-body row g-2">
    <div class="col-md-3"><?= $inp('sku', 'SKU / Kode *', 'text', 'required') ?></div><div class="col-md-3"><?= $inp('barcode', 'Barcode') ?></div><div class="col-md-6"><?= $inp('name', 'Nama Produk *', 'text', 'required') ?></div>
    <div class="col-md-4"><?= $inp('generic_name', 'Nama Generik') ?></div><div class="col-md-4"><?= $inp('brand', 'Brand') ?></div><div class="col-md-4"><?= $inp('manufacturer_name', 'Manufacturer') ?></div>
    <div class="col-md-4"><?= $inp('principal_name', 'Principal') ?></div>
    <div class="col-md-4"><label class="form-label">Kategori</label><select class="form-select form-select-sm" name="category_id" data-testid="product-category_id"><option value="">—</option><?php foreach ($refs['categories'] as $c): if ($c['parent_id']) continue; ?><option value="<?= $c['id'] ?>" <?= $sel('category_id', $c['id']) ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-4"><label class="form-label">Subkategori</label><select class="form-select form-select-sm" name="subcategory_id"><option value="">—</option><?php foreach ($refs['categories'] as $c): if (!$c['parent_id']) continue; ?><option value="<?= $c['id'] ?>" <?= $sel('subcategory_id', $c['id']) ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-4"><label class="form-label">Golongan Obat</label><select class="form-select form-select-sm" name="classification_id" data-testid="product-classification_id"><option value="">—</option><?php foreach ($refs['classifications'] as $c): ?><option value="<?= $c['id'] ?>" <?= $sel('classification_id', $c['id']) ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-3"><?= $inp('dosage_form', 'Bentuk Sediaan') ?></div><div class="col-md-3"><?= $inp('preparation_type', 'Jenis Sediaan') ?></div><div class="col-md-2"><?= $inp('strength', 'Kekuatan') ?></div>
    <div class="col-md-2"><label class="form-label">Satuan Dasar *</label><select class="form-select form-select-sm" name="base_uom_id" required data-testid="product-base_uom_id"><option value="">—</option><?php foreach ($refs['uoms'] as $u): ?><option value="<?= $u['id'] ?>" <?= $sel('base_uom_id', $u['id']) ?>><?= e($u['code']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-4"><?= $inp('packaging', 'Kemasan') ?></div>
</div></div>
<div class="card erp-card mb-3"><div class="card-header">Konversi Satuan (UOM)</div><div class="card-body p-0"><table class="table table-sm mb-0 erp-table" id="unitsTable" data-testid="product-units">
<thead><tr><th>Satuan</th><th>Faktor (× satuan dasar)</th><th>Barcode</th><th>Harga Jual</th><th class="text-center">Beli</th><th class="text-center">Jual</th><th></th></tr></thead><tbody>
<?php $units = old('units', $p['units'] ?? []); foreach (array_merge($units, [[]]) as $i => $u): ?><tr>
    <td><select class="form-select form-select-sm" name="units[<?= $i ?>][uom_id]"><option value="">—</option><?php foreach ($refs['uoms'] as $uo): ?><option value="<?= $uo['id'] ?>" <?= ($u['uom_id'] ?? '') == $uo['id'] ? 'selected' : '' ?>><?= e($uo['code']) ?></option><?php endforeach; ?></select></td>
    <td><input class="form-control form-control-sm" name="units[<?= $i ?>][conversion_factor]" value="<?= e($u['conversion_factor'] ?? '') ?>" inputmode="decimal"></td><td><input class="form-control form-control-sm" name="units[<?= $i ?>][barcode]" value="<?= e($u['barcode'] ?? '') ?>"></td>
    <td><input class="form-control form-control-sm" name="units[<?= $i ?>][selling_price]" value="<?= e($u['selling_price'] ?? '') ?>" inputmode="decimal"></td>
    <td class="text-center"><input type="checkbox" class="form-check-input" name="units[<?= $i ?>][is_purchase_uom]" value="1" <?= !empty($u['is_purchase_uom']) ? 'checked' : '' ?>></td><td class="text-center"><input type="checkbox" class="form-check-input" name="units[<?= $i ?>][is_sales_uom]" value="1" <?= !empty($u['is_sales_uom']) ? 'checked' : '' ?>></td>
    <td><button type="button" class="btn btn-xs btn-outline-danger row-remove"><i class="bi bi-x"></i></button></td></tr><?php endforeach; ?>
</tbody></table><div class="p-2"><button type="button" class="btn btn-xs btn-outline-secondary" data-add-row="#unitsTable" data-testid="product-add-unit"><i class="bi bi-plus"></i> Tambah satuan</button></div></div></div>
</div>
<div class="col-lg-4">
<div class="card erp-card mb-3"><div class="card-header">Harga & Pajak</div><div class="card-body row g-2">
    <div class="col-6"><?= $inp('purchase_price', 'Harga Beli', 'number', 'step="0.01" min="0"') ?></div><div class="col-6"><?= $inp('selling_price', 'Harga Jual', 'number', 'step="0.01" min="0"') ?></div>
    <div class="col-8"><?= $inp('tax_code', 'Kode Pajak (tax engine, Phase 6)') ?></div><div class="col-4 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_taxable" value="1" id="tx" <?= $chk('is_taxable', 1) ?>><label class="form-check-label" for="tx">Kena PPN</label></div></div>
</div></div>
<div class="card erp-card mb-3"><div class="card-header">Parameter Stok</div><div class="card-body row g-2">
    <div class="col-6"><?= $inp('min_stock', 'Stok Min', 'number', 'step="any" min="0"') ?></div><div class="col-6"><?= $inp('max_stock', 'Stok Maks', 'number', 'step="any" min="0"') ?></div>
    <div class="col-6"><?= $inp('reorder_point', 'Reorder Point', 'number', 'step="any" min="0"') ?></div><div class="col-6"><?= $inp('safety_stock', 'Safety Stock', 'number', 'step="any" min="0"') ?></div>
    <div class="col-6"><?= $inp('lead_time_days', 'Lead Time (hari)', 'number', 'min="0"') ?></div>
    <div class="col-6"><label class="form-label">Status</label><select class="form-select form-select-sm" name="status" data-testid="product-status"><?php foreach (['ACTIVE', 'INACTIVE', 'DISCONTINUED'] as $s): ?><option <?= $sel('status', $s) ?: ($s === 'ACTIVE' && !$p ? 'selected' : '') ?>><?= $s ?></option><?php endforeach; ?></select></div>
</div></div>
<div class="card erp-card mb-3"><div class="card-header">Pelacakan & Flag Farmasi</div><div class="card-body">
    <?php foreach (['is_batch_tracked' => ['Batch tracking', 1], 'is_expiry_tracked' => ['Expiry tracking', 1], 'is_serial_tracked' => ['Serial tracking', 0], 'requires_prescription' => ['Wajib resep (Rx)', 0], 'is_controlled' => ['Obat terkontrol (OOT/Psikotropika/Narkotika)', 0], 'is_cold_chain' => ['Cold chain', 0]] as $f => [$l, $d]): ?>
    <div class="form-check"><input class="form-check-input" type="checkbox" name="<?= $f ?>" value="1" id="<?= $f ?>" <?= $chk($f, $p ? 0 : $d) ?> data-testid="product-<?= $f ?>"><label class="form-check-label" for="<?= $f ?>"><?= $l ?></label></div><?php endforeach; ?>
    <div class="row g-2 mt-2"><div class="col-6"><?= $inp('storage_min_temp', 'Suhu Min (°C)', 'number', 'step="0.1"') ?></div><div class="col-6"><?= $inp('storage_max_temp', 'Suhu Maks (°C)', 'number', 'step="0.1"') ?></div></div>
</div></div>
</div>
<div class="col-12"><button class="btn btn-primary" data-testid="product-save"><i class="bi bi-save me-1"></i>Simpan</button> <a class="btn btn-link" href="<?= site_url('master/products') ?>">Batal</a></div>
</form>
