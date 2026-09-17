<?php
/** Generic inline master CRUD: $rows, $fields [name => [label, type, options?]], $save_url, $perm_prefix, $testid */
$errs = $this->session->flashdata('errors') ?: []; $editing = old('id') ? array_merge(['id' => old('id')], $this->session->flashdata('_old_input') ?: []) : null; ?>
<div class="row g-3">
<div class="col-lg-8"><div class="card erp-card"><div class="table-responsive"><table class="table table-sm table-hover erp-table mb-0" data-testid="<?= $testid ?>-table">
<thead><tr><?php foreach ($fields as $f => $def): ?><th><?= e($def[0]) ?></th><?php endforeach; ?><th></th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr data-testid="<?= $testid ?>-row-<?= $r['id'] ?>"><?php foreach ($fields as $f => $def): ?><td><?php
    if ($def[1] === 'check') echo status_badge($r[$f] ? 'ACTIVE' : 'INACTIVE');
    elseif ($def[1] === 'select') echo e($def[2][$r[$f]] ?? $r[$f]);
    else echo e($r[$f]); ?></td><?php endforeach; ?>
    <td class="text-end"><?php if (can("$perm_prefix.edit")): ?><button type="button" class="btn btn-xs btn-outline-secondary js-edit-row" data-row='<?= e(json_encode($r)) ?>' data-testid="<?= $testid ?>-edit-<?= $r['id'] ?>"><i class="bi bi-pencil"></i></button><?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table></div></div></div>
<div class="col-lg-4"><?php if (can("$perm_prefix.create") || can("$perm_prefix.edit")): ?><div class="card erp-card"><div class="card-header"><span id="formTitle">Tambah</span></div><div class="card-body">
<form method="post" action="<?= site_url($save_url) ?>" id="masterForm" data-testid="<?= $testid ?>-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e($editing['id'] ?? '') ?>">
<?php foreach ($fields as $f => $def): $val = $editing[$f] ?? ($def[3] ?? ''); ?>
    <div class="mb-2"><?php if ($def[1] === 'check'): ?><div class="form-check"><input class="form-check-input" type="checkbox" name="<?= $f ?>" value="1" id="f_<?= $f ?>" <?= ($editing ? !empty($editing[$f]) : ($def[3] ?? 1)) ? 'checked' : '' ?>><label class="form-check-label" for="f_<?= $f ?>"><?= e($def[0]) ?></label></div>
    <?php elseif ($def[1] === 'select'): ?><label class="form-label"><?= e($def[0]) ?></label><select class="form-select form-select-sm" name="<?= $f ?>" data-testid="<?= $testid ?>-<?= $f ?>"><?php foreach ($def[2] as $k => $l): ?><option value="<?= e($k) ?>" <?= (string) $val === (string) $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
    <?php else: ?><label class="form-label"><?= e($def[0]) ?></label><input class="form-control form-control-sm <?= isset($errs[$f]) ? 'is-invalid' : '' ?>" name="<?= $f ?>" value="<?= e($val) ?>" data-testid="<?= $testid ?>-<?= $f ?>"><div class="invalid-feedback"><?= e($errs[$f] ?? '') ?></div><?php endif; ?></div>
<?php endforeach; ?>
<button class="btn btn-sm btn-primary" data-testid="<?= $testid ?>-save"><i class="bi bi-save me-1"></i>Simpan</button> <button type="reset" class="btn btn-sm btn-link" id="formReset">Baru</button></form></div></div><?php endif; ?></div>
</div>
