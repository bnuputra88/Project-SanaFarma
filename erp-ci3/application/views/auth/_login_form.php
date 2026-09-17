<form method="post" action="<?= site_url('login') ?>" autocomplete="off" data-testid="login-form"><?= csrf_field() ?>
    <div class="mb-3"><label class="form-label">Username / Email</label><input class="form-control" name="identifier" required autofocus data-testid="login-identifier"></div>
    <div class="mb-3"><label class="form-label">Password</label><input class="form-control" type="password" name="password" required data-testid="login-password"></div>
    <button class="btn btn-primary w-100" data-testid="login-submit">Masuk</button>
    <div class="mt-3 text-center small"><a href="<?= site_url('password/forgot') ?>" data-testid="login-forgot">Lupa password?</a></div>
</form>
