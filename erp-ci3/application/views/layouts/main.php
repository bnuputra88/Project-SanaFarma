<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> · PharmaERP</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?= base_url('assets/css/erp.css') ?>" rel="stylesheet">
<meta name="csrf-name" content="<?= $this->security->get_csrf_token_name() ?>">
<meta name="csrf-token" content="<?= $this->security->get_csrf_hash() ?>">
<meta name="base-url" content="<?= site_url() ?>">
</head>
<body class="erp-body">
<div class="erp-shell">
    <?php $this->load->view('partials/sidebar'); ?>
    <div class="erp-main">
        <header class="erp-topbar">
            <button class="btn btn-sm btn-outline-light d-lg-none" id="sidebarToggle" aria-label="Menu" data-testid="sidebar-toggle"><i class="bi bi-list"></i></button>
            <nav aria-label="breadcrumb" class="flex-grow-1">
                <ol class="breadcrumb mb-0" data-testid="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?= site_url('dashboard') ?>">Beranda</a></li>
                    <?php foreach (array_slice($this->uri->segment_array(), 0, -1) as $seg): ?><li class="breadcrumb-item text-capitalize"><?= e(str_replace('-', ' ', $seg)) ?></li><?php endforeach; ?>
                    <li class="breadcrumb-item active" aria-current="page"><?= e($title) ?></li>
                </ol>
            </nav>
            <div class="erp-user dropdown">
                <button class="btn btn-sm dropdown-toggle text-light" data-bs-toggle="dropdown" data-testid="user-menu"><i class="bi bi-person-circle me-1"></i><?= e($context->username) ?></button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="<?= site_url('profile/password') ?>" data-testid="menu-change-password"><i class="bi bi-key me-2"></i>Ganti Password</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="<?= site_url('logout') ?>" data-testid="menu-logout"><i class="bi bi-box-arrow-right me-2"></i>Keluar</a></li>
                </ul>
            </div>
        </header>
        <main class="erp-content">
            <?php $this->load->view('partials/flash'); ?>
            <?= $content ?>
        </main>
        <footer class="erp-footer">PharmaERP · <?= ENVIRONMENT ?> · <?= date('Y') ?></footer>
    </div>
</div>
<div class="modal fade" id="confirmModal" tabindex="-1"><div class="modal-dialog modal-sm"><div class="modal-content">
    <div class="modal-header py-2"><h6 class="modal-title">Konfirmasi</h6></div>
    <div class="modal-body" id="confirmModalBody"><?= lang('confirm_destructive') ?></div>
    <div class="modal-footer py-1"><button class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Batal</button><button class="btn btn-sm btn-danger" id="confirmModalOk" data-testid="confirm-ok">Lanjutkan</button></div>
</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= base_url('assets/js/erp.js') ?>"></script>
</body>
</html>
