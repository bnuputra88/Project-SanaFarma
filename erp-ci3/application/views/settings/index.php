<?php $groups = []; foreach ($settings as $s) { $groups[$s['setting_group']][] = $s; } ?>
<form method="post" action="<?= site_url('system/settings/save') ?>" data-testid="settings-form"><?= csrf_field() ?>
<?php foreach ($groups as $g => $rows): ?>
<div class="card erp-card mb-3"><div class="card-header text-capitalize"><?= e($g) ?></div><table class="table table-sm erp-table mb-0"><tbody>
<?php foreach ($rows as $s): ?><tr><td style="width:35%"><code><?= e($s['setting_key']) ?></code><br><small class="text-muted"><?= e($s['description']) ?></small><?= $s['company_id'] ? ' <span class="badge text-bg-light border">override perusahaan</span>' : '' ?></td>
<td><?php if (!$s['is_editable'] || !can('system.setting.edit')): ?><span class="font-monospace"><?= e($s['setting_value']) ?></span>
    <?php elseif ($s['value_type'] === 'bool'): ?><select class="form-select form-select-sm w-auto" name="settings[<?= e($s['setting_key']) ?>]" data-testid="setting-<?= e($s['setting_key']) ?>"><option value="1" <?= $s['setting_value'] ? 'selected' : '' ?>>Ya</option><option value="0" <?= !$s['setting_value'] ? 'selected' : '' ?>>Tidak</option></select>
    <?php else: ?><input class="form-control form-control-sm" name="settings[<?= e($s['setting_key']) ?>]" value="<?= e($s['setting_value']) ?>" data-testid="setting-<?= e($s['setting_key']) ?>"><?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table></div><?php endforeach; ?>
<?php if (can('system.setting.edit')): ?><button class="btn btn-primary" data-testid="settings-save"><i class="bi bi-save me-1"></i>Simpan Parameter</button><?php endif; ?>
</form>
