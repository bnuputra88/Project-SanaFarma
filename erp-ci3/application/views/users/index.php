<div class="erp-page-head">
    <form class="erp-filter" method="get" data-testid="users-filter"><input class="form-control form-control-sm" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="Cari username / nama / email">
        <select class="form-select form-select-sm" name="is_active"><option value="">Semua status</option><option value="1" <?= ($_GET['is_active'] ?? '') === '1' ? 'selected' : '' ?>>Aktif</option><option value="0" <?= ($_GET['is_active'] ?? '') === '0' ? 'selected' : '' ?>>Nonaktif</option></select>
        <button class="btn btn-sm btn-outline-secondary">Filter</button></form>
    <?php if (can('system.user.create')): ?><a class="btn btn-sm btn-primary" href="<?= site_url('system/users/create') ?>" data-testid="user-create-btn"><i class="bi bi-plus-lg me-1"></i>Pengguna Baru</a><?php endif; ?>
</div>
<div class="card erp-card"><div class="table-responsive"><table class="table table-sm table-hover erp-table mb-0" data-testid="users-table">
<thead><tr><th><?= sort_link('username', 'Username') ?></th><th><?= sort_link('full_name', 'Nama') ?></th><th>Email</th><th>Cabang</th><th>Role</th><th><?= sort_link('last_login_at', 'Login Terakhir') ?></th><th>Status</th><th></th></tr></thead>
<tbody><?php foreach ($page->items as $u): ?><tr data-testid="user-row-<?= $u['id'] ?>">
    <td class="font-monospace"><?= e($u['username']) ?><?= $u['is_superadmin'] ? ' <i class="bi bi-shield-fill-check text-primary" title="Superadmin"></i>' : '' ?></td><td><?= e($u['full_name']) ?></td><td><?= e($u['email']) ?></td><td><?= e($u['branch_name'] ?? '-') ?></td>
    <td class="small"><?= e($u['role_names'] ?? '-') ?></td><td class="small text-muted"><?= fmt_dt($u['last_login_at']) ?></td><td><?= status_badge($u['is_active'] ? 'ACTIVE' : 'INACTIVE') ?></td>
    <td class="text-end text-nowrap"><?php if (can('system.user.edit')): ?><a class="btn btn-xs btn-outline-secondary" href="<?= site_url("system/users/{$u['id']}/edit") ?>" data-testid="user-edit-<?= $u['id'] ?>"><i class="bi bi-pencil"></i></a>
        <form method="post" action="<?= site_url("system/users/{$u['id']}/toggle") ?>" class="d-inline needs-confirm" data-confirm="Ubah status aktif pengguna ini?"><?= csrf_field() ?><button class="btn btn-xs btn-outline-<?= $u['is_active'] ? 'danger' : 'success' ?>" data-testid="user-toggle-<?= $u['id'] ?>"><i class="bi bi-power"></i></button></form><?php endif; ?></td>
</tr><?php endforeach; ?></tbody></table></div><div class="card-footer py-1"><?php $this->load->view('partials/pagination', ['page' => $page]); ?></div></div>
