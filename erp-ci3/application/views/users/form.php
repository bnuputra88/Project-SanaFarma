<?php $u = $user ?? []; $v = fn($k, $d = '') => e(old($k, $u[$k] ?? $d)); $errs = $this->session->flashdata('errors') ?: []; ?>
<form method="post" class="row g-3" data-testid="user-form"><?= csrf_field() ?>
<div class="col-lg-7"><div class="card erp-card"><div class="card-header">Data Pengguna</div><div class="card-body row g-3">
    <div class="col-md-6"><label class="form-label">Username *</label><input class="form-control <?= isset($errs['username']) ? 'is-invalid' : '' ?>" name="username" value="<?= $v('username') ?>" required data-testid="user-username"><div class="invalid-feedback"><?= e($errs['username'] ?? '') ?></div></div>
    <div class="col-md-6"><label class="form-label">Email *</label><input class="form-control <?= isset($errs['email']) ? 'is-invalid' : '' ?>" type="email" name="email" value="<?= $v('email') ?>" required data-testid="user-email"><div class="invalid-feedback"><?= e($errs['email'] ?? '') ?></div></div>
    <div class="col-md-8"><label class="form-label">Nama Lengkap *</label><input class="form-control" name="full_name" value="<?= $v('full_name') ?>" required data-testid="user-fullname"></div>
    <div class="col-md-4"><label class="form-label">Telepon</label><input class="form-control" name="phone" value="<?= $v('phone') ?>"></div>
    <div class="col-md-6"><label class="form-label">Cabang</label><select class="form-select" name="branch_id" data-testid="user-branch"><option value="">— Semua / HO —</option><?php foreach ($branches as $b): ?><option value="<?= $b['id'] ?>" <?= (string) old('branch_id', $u['branch_id'] ?? '') === (string) $b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-6"><label class="form-label"><?= $u ? 'Password baru (kosongkan jika tidak diubah)' : 'Password *' ?></label><input class="form-control <?= isset($errs['password']) ? 'is-invalid' : '' ?>" type="password" name="password" <?= $u ? '' : 'required' ?> data-testid="user-password"><div class="invalid-feedback"><?= e($errs['password'] ?? '') ?></div></div>
    <div class="col-md-6"><label class="form-label">Konfirmasi Password</label><input class="form-control" type="password" name="password_confirm" data-testid="user-password-confirm"></div>
    <div class="col-md-6 d-flex align-items-end gap-3">
        <div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="ia" <?= old('is_active', $u['is_active'] ?? 1) ? 'checked' : '' ?>><label class="form-check-label" for="ia">Aktif</label></div>
        <div class="form-check"><input class="form-check-input" type="checkbox" name="must_change_password" value="1" id="mcp" <?= old('must_change_password', $u ? 0 : 1) ? 'checked' : '' ?>><label class="form-check-label" for="mcp">Wajib ganti password saat login</label></div>
    </div>
</div></div></div>
<div class="col-lg-5"><div class="card erp-card"><div class="card-header">Role *</div><div class="card-body" data-testid="user-roles">
    <?php $sel = old('role_ids', $u['role_ids'] ?? []); foreach ($roles as $r): ?><div class="form-check"><input class="form-check-input" type="checkbox" name="role_ids[]" value="<?= $r['id'] ?>" id="r<?= $r['id'] ?>" <?= in_array($r['id'], $sel) ? 'checked' : '' ?> data-testid="user-role-<?= e($r['code']) ?>"><label class="form-check-label" for="r<?= $r['id'] ?>"><?= e($r['name']) ?> <small class="text-muted">(<?= $r['permission_count'] ?> izin)</small></label></div><?php endforeach; ?>
    <?php if (isset($errs['role_ids'])): ?><div class="text-danger small mt-1"><?= e($errs['role_ids']) ?></div><?php endif; ?>
</div></div></div>
<div class="col-12"><button class="btn btn-primary" data-testid="user-save"><i class="bi bi-save me-1"></i>Simpan</button> <a class="btn btn-link" href="<?= site_url('system/users') ?>">Batal</a></div>
</form>
