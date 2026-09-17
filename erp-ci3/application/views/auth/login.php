<!DOCTYPE html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= e($title) ?></title>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="<?= base_url('assets/css/erp.css') ?>" rel="stylesheet"></head>
<body class="erp-auth">
<div class="erp-auth-card" data-testid="login-card">
    <div class="erp-auth-brand">Pharma<strong>ERP</strong><small>ERP · Inventori · Farmasi</small></div>
    <?php if ($m = $this->session->flashdata('error')): ?><div class="alert alert-danger py-2 small" data-testid="login-error"><?= e($m) ?></div><?php endif; ?>
    <?php if ($m = $this->session->flashdata('success')): ?><div class="alert alert-success py-2 small" data-testid="login-success"><?= e($m) ?></div><?php endif; ?>
    <?= $this->load->view($form_view ?? 'auth/_login_form', [], true) ?>
</div>
</body></html>
