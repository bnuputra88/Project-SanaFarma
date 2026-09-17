<div class="erp-page-head"><div></div><?php if (can('system.role.create')): ?><a class="btn btn-sm btn-primary" href="<?= site_url('system/roles/create') ?>" data-testid="role-create-btn"><i class="bi bi-plus-lg me-1"></i>Role Baru</a><?php endif; ?></div>
<div class="card erp-card"><table class="table table-sm table-hover erp-table mb-0" data-testid="roles-table">
<thead><tr><th>Kode</th><th>Nama</th><th>Deskripsi</th><th class="text-end">Izin</th><th class="text-end">Pengguna</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($roles as $r): ?><tr data-testid="role-row-<?= $r['id'] ?>"><td class="font-monospace"><?= e($r['code']) ?><?= $r['is_system'] ? ' <span class="badge text-bg-light border">sistem</span>' : '' ?></td><td><?= e($r['name']) ?></td><td class="text-muted small"><?= e($r['description']) ?></td>
<td class="text-end"><?= $r['permission_count'] ?></td><td class="text-end"><?= $r['user_count'] ?></td><td><?= status_badge($r['is_active'] ? 'ACTIVE' : 'INACTIVE') ?></td>
<td class="text-end"><?php if (can('system.role.edit')): ?><a class="btn btn-xs btn-outline-secondary" href="<?= site_url("system/roles/{$r['id']}/edit") ?>" data-testid="role-edit-<?= $r['id'] ?>"><i class="bi bi-pencil"></i></a><?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table></div>
