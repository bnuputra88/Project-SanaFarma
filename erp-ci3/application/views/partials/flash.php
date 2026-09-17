<?php $errors = $this->session->flashdata('errors') ?: []; ?>
<?php if ($m = $this->session->flashdata('success')): ?><div class="alert alert-success alert-dismissible py-2" role="alert" data-testid="flash-success"><i class="bi bi-check-circle me-2"></i><?= e($m) ?><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<?php if ($m = $this->session->flashdata('error')): ?><div class="alert alert-danger alert-dismissible py-2" role="alert" data-testid="flash-error"><i class="bi bi-exclamation-triangle me-2"></i><?= e($m) ?>
    <?php if ($errors): ?><ul class="mb-0 mt-1 small"><?php foreach ($errors as $f => $msg): ?><li><?= e($msg) ?></li><?php endforeach; ?></ul><?php endif; ?>
    <button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
