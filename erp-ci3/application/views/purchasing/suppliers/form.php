<?php $s = $supplier ?? []; $errs = $this->session->flashdata('errors') ?: []; $v = fn($k, $def = '') => e(old($k, $s[$k] ?? $def)); ?>
<div class="erp-page-head"><h5 class="mb-0"><?= $s ? 'Ubah Supplier' : 'Supplier Baru' ?></h5><a class="btn btn-sm btn-link" href="<?= site_url('purchasing/suppliers') ?>">&larr; Daftar</a></div>
<form method="post" data-testid="supplier-form"><?= csrf_field() ?>
<div class="card erp-card mb-3"><div class="card-body row g-2">
    <div class="col-md-3"><label class="form-label">Kode *</label><input class="form-control form-control-sm <?= isset($errs['code']) ? 'is-invalid' : '' ?>" name="code" value="<?= $v('code') ?>" required data-testid="sup-code"><div class="invalid-feedback"><?= e($errs['code'] ?? '') ?></div></div>
    <div class="col-md-6"><label class="form-label">Nama *</label><input class="form-control form-control-sm" name="name" value="<?= $v('name') ?>" required data-testid="sup-name"></div>
    <div class="col-md-3"><label class="form-label">Tipe</label><select class="form-select form-select-sm" name="supplier_type" data-testid="sup-type"><?php foreach (['DISTRIBUTOR', 'MANUFACTURER', 'PBF', 'IMPORTER', 'OTHER'] as $t): ?><option <?= (old('supplier_type', $s['supplier_type'] ?? 'DISTRIBUTOR') === $t) ? 'selected' : '' ?>><?= $t ?></option><?php endforeach; ?></select></div>
    <div class="col-md-6"><label class="form-label">Nama Legal</label><input class="form-control form-control-sm" name="legal_name" value="<?= $v('legal_name') ?>"></div>
    <div class="col-md-3"><label class="form-label">NPWP</label><input class="form-control form-control-sm" name="npwp" value="<?= $v('npwp') ?>"></div>
    <div class="col-md-3"><label class="form-label">No. Izin (PBF)</label><input class="form-control form-control-sm" name="license_no" value="<?= $v('license_no') ?>"></div>
    <div class="col-md-4"><label class="form-label">Kontak Person</label><input class="form-control form-control-sm" name="contact_person" value="<?= $v('contact_person') ?>"></div>
    <div class="col-md-3"><label class="form-label">Telepon</label><input class="form-control form-control-sm" name="phone" value="<?= $v('phone') ?>"></div>
    <div class="col-md-5"><label class="form-label">Email</label><input class="form-control form-control-sm <?= isset($errs['email']) ? 'is-invalid' : '' ?>" name="email" value="<?= $v('email') ?>"><div class="invalid-feedback"><?= e($errs['email'] ?? '') ?></div></div>
    <div class="col-md-8"><label class="form-label">Alamat</label><input class="form-control form-control-sm" name="address" value="<?= $v('address') ?>"></div>
    <div class="col-md-2"><label class="form-label">Tempo (hari)</label><input class="form-control form-control-sm text-end" name="payment_term_days" value="<?= $v('payment_term_days', '0') ?>" inputmode="numeric"></div>
    <div class="col-md-2"><label class="form-label">Mata Uang</label><input class="form-control form-control-sm" name="currency" value="<?= $v('currency', 'IDR') ?>"></div>
    <div class="col-12"><div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="isActive" <?= old('is_active', $s['is_active'] ?? 1) ? 'checked' : '' ?>><label class="form-check-label" for="isActive">Aktif</label></div></div>
</div></div>
<button class="btn btn-primary" data-testid="sup-save"><i class="bi bi-save me-1"></i>Simpan</button> <a class="btn btn-link" href="<?= site_url('purchasing/suppliers') ?>">Batal</a>
</form>
