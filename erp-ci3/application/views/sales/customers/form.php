<?php $c = $customer ?? []; $errs = $this->session->flashdata('errors') ?: []; $v = fn($k, $def = '') => e(old($k, $c[$k] ?? $def)); ?>
<div class="erp-page-head"><h5 class="mb-0"><?= $c ? 'Ubah Pelanggan' : 'Pelanggan Baru' ?></h5><a class="btn btn-sm btn-link" href="<?= site_url('sales/customers') ?>">&larr; Daftar</a></div>
<form method="post" data-testid="customer-form"><?= csrf_field() ?>
<div class="card erp-card mb-3"><div class="card-body row g-2">
    <div class="col-md-6"><label class="form-label">Nama *</label><input class="form-control form-control-sm" name="name" value="<?= $v('name') ?>" required data-testid="cust-name"></div>
    <div class="col-md-3"><label class="form-label">Tipe</label><select class="form-select form-select-sm" name="customer_type" data-testid="cust-type"><?php foreach (['WALK_IN', 'MEMBER', 'PATIENT', 'CORPORATE'] as $t): ?><option <?= (old('customer_type', $c['customer_type'] ?? 'WALK_IN') === $t) ? 'selected' : '' ?>><?= $t ?></option><?php endforeach; ?></select></div>
    <div class="col-md-3"><label class="form-label">Kode</label><input class="form-control form-control-sm" name="code" value="<?= $v('code') ?>" placeholder="otomatis bila kosong"></div>
    <div class="col-md-3"><label class="form-label">NIK</label><input class="form-control form-control-sm" name="nik" value="<?= $v('nik') ?>"></div>
    <div class="col-md-3"><label class="form-label">Telepon</label><input class="form-control form-control-sm" name="phone" value="<?= $v('phone') ?>"></div>
    <div class="col-md-3"><label class="form-label">Email</label><input class="form-control form-control-sm" name="email" value="<?= $v('email') ?>"></div>
    <div class="col-md-3"><label class="form-label">Tgl Lahir</label><input class="form-control form-control-sm" type="date" name="date_of_birth" value="<?= $v('date_of_birth') ?>"></div>
    <div class="col-md-8"><label class="form-label">Alamat</label><input class="form-control form-control-sm" name="address" value="<?= $v('address') ?>"></div>
    <div class="col-md-4"><label class="form-label">Catatan Alergi/Riwayat</label><input class="form-control form-control-sm" name="allergy_notes" value="<?= $v('allergy_notes') ?>"></div>
    <div class="col-12"><div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="ca" <?= old('is_active', $c['is_active'] ?? 1) ? 'checked' : '' ?>><label class="form-check-label" for="ca">Aktif</label></div></div>
</div></div>
<button class="btn btn-primary" data-testid="cust-save"><i class="bi bi-save me-1"></i>Simpan</button> <a class="btn btn-link" href="<?= site_url('sales/customers') ?>">Batal</a>
</form>
