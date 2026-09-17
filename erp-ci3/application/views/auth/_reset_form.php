<form method="post" action="<?= site_url('password/reset/' . e($token)) ?>" data-testid="reset-form"><?= csrf_field() ?>
    <div class="mb-3"><label class="form-label">Password baru</label><input class="form-control" type="password" name="password" required data-testid="reset-password"></div>
    <div class="mb-3"><label class="form-label">Konfirmasi</label><input class="form-control" type="password" name="password_confirm" required data-testid="reset-password-confirm"></div>
    <button class="btn btn-primary w-100" data-testid="reset-submit">Reset Password</button>
</form>
