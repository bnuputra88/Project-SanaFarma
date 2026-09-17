<?php $r = $role ?? []; $sel = array_map('intval', old('permission_ids', $r['permission_ids'] ?? [])); ?>
<form method="post" data-testid="role-form"><?= csrf_field() ?>
<div class="card erp-card mb-3"><div class="card-body row g-3">
    <div class="col-md-3"><label class="form-label">Kode *</label><input class="form-control font-monospace" name="code" value="<?= e(old('code', $r['code'] ?? '')) ?>" placeholder="MIS. GUDANG_SENIOR" required <?= !empty($r['is_system']) ? 'readonly' : '' ?> data-testid="role-code"></div>
    <div class="col-md-4"><label class="form-label">Nama *</label><input class="form-control" name="name" value="<?= e(old('name', $r['name'] ?? '')) ?>" required data-testid="role-name"></div>
    <div class="col-md-4"><label class="form-label">Deskripsi</label><input class="form-control" name="description" value="<?= e(old('description', $r['description'] ?? '')) ?>"></div>
    <div class="col-md-1 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="ia" <?= old('is_active', $r['is_active'] ?? 1) ? 'checked' : '' ?>><label class="form-check-label" for="ia">Aktif</label></div></div>
</div></div>
<div class="card erp-card mb-3"><div class="card-header d-flex justify-content-between"><span>Matriks Hak Akses</span><small class="text-muted">klik header kolom/baris untuk toggle</small></div>
<div class="table-responsive"><table class="table table-sm table-bordered erp-table erp-perm-matrix mb-0" data-testid="permission-matrix">
<thead><tr><th>Modul / Resource</th><?php foreach ($actions as $a): ?><th class="text-center perm-col" data-action="<?= $a ?>"><?= e($a) ?></th><?php endforeach; ?></tr></thead><tbody>
<?php foreach ($matrix as $module => $resources): ?><tr class="table-light"><td colspan="<?= count($actions) + 1 ?>" class="fw-semibold"><?= e($modules[$module] ?? $module) ?></td></tr>
    <?php foreach ($resources as $res => $acts): ?><tr><td class="perm-row" style="cursor:pointer"><?= e($res) ?></td>
        <?php foreach ($actions as $a): ?><td class="text-center"><?php if (isset($acts[$a])): ?><input type="checkbox" class="form-check-input" name="permission_ids[]" value="<?= $acts[$a] ?>" <?= in_array($acts[$a], $sel, true) ? 'checked' : '' ?> data-action="<?= $a ?>" data-testid="perm-<?= $module ?>-<?= $res ?>-<?= $a ?>"><?php endif; ?></td><?php endforeach; ?>
    </tr><?php endforeach; ?>
<?php endforeach; ?></tbody></table></div></div>
<button class="btn btn-primary" data-testid="role-save"><i class="bi bi-save me-1"></i>Simpan</button> <a class="btn btn-link" href="<?= site_url('system/roles') ?>">Batal</a>
</form>
