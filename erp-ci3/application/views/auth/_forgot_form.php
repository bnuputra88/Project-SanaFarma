<form method="post" action="<?= site_url('password/forgot') ?>" data-testid="forgot-form"><?= csrf_field() ?>
    <p class="small text-muted">Masukkan email terdaftar. Instruksi reset akan dikirim (pada Phase 1 link dicatat di log aplikasi untuk reset dibantu admin).</p>
    <div class="mb-3"><label class="form-label">Email</label><input class="form-control" type="email" name="email" required data-testid="forgot-email"></div>
    <button class="btn btn-primary w-100" data-testid="forgot-submit">Kirim Instruksi</button>
    <div class="mt-3 text-center small"><a href="<?= site_url('login') ?>">Kembali ke login</a></div>
</form>
