<div class="row"><div class="col-lg-5">
<div class="card erp-card"><div class="card-header">Ganti Password</div><div class="card-body">
<form method="post" action="<?= site_url('profile/password') ?>" data-testid="change-password-form"><?= csrf_field() ?>
    <div class="mb-3"><label class="form-label">Password saat ini</label><input class="form-control" type="password" name="current_password" required data-testid="cp-current"></div>
    <div class="mb-3"><label class="form-label">Password baru</label><input class="form-control" type="password" name="password" required data-testid="cp-new"><div class="form-text">Mengikuti kebijakan password di Parameter Sistem.</div></div>
    <div class="mb-3"><label class="form-label">Konfirmasi</label><input class="form-control" type="password" name="password_confirm" required data-testid="cp-confirm"></div>
    <button class="btn btn-primary" data-testid="cp-submit">Simpan</button>
</form></div></div></div></div>
