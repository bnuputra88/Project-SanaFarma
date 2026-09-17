<?php
/** Workflow action buttons + history. Expects $doc (status, actions, history), $base url, $labels map action=>permission. */
$labels = ['submit' => ['action_submit', 'primary', 'send'], 'approve' => ['action_approve', 'success', 'check2-circle'], 'reject' => ['action_reject', 'warning', 'x-circle'], 'cancel' => ['action_cancel', 'outline-danger', 'slash-circle'],
    'post' => ['action_post', 'dark', 'journal-check'], 'ship' => ['action_ship', 'primary', 'truck'], 'receive' => ['action_receive', 'success', 'box-arrow-in-down'], 'start' => ['action_start', 'primary', 'play-circle']];
?>
<div class="card erp-card mb-3" data-testid="workflow-panel">
    <div class="card-body py-2 d-flex flex-wrap gap-2 align-items-center">
        <span class="me-2">Status: <?= status_badge($doc['status']) ?></span>
        <?php foreach ($doc['actions'] as $a): if ($a === 'edit' || !isset($labels[$a]) || !can($perms[$a] ?? '')) continue; [$l, $cls, $ic] = $labels[$a]; ?>
            <?php if (in_array($a, ['reject', 'cancel'], true)): ?>
                <form method="post" action="<?= site_url("$base/{$doc['id']}/action/$a") ?>" class="d-inline-flex gap-1 needs-confirm" data-confirm="Yakin <?= lang($l) ?> dokumen ini?"><?= csrf_field() ?>
                    <input type="text" name="notes" class="form-control form-control-sm" placeholder="Alasan (wajib untuk tolak)" style="width:220px" data-testid="wf-notes-<?= $a ?>">
                    <button class="btn btn-sm btn-<?= $cls ?>" data-testid="wf-action-<?= $a ?>"><i class="bi bi-<?= $ic ?> me-1"></i><?= lang($l) ?></button></form>
            <?php elseif ($a === 'receive'): continue; // rendered inside items table
            else: ?>
                <form method="post" action="<?= site_url("$base/{$doc['id']}/action/$a") ?>" class="d-inline <?= $a === 'post' ? 'needs-confirm' : '' ?>" data-confirm="Posting akan memutasi stok dan tidak dapat diubah. Lanjutkan?"><?= csrf_field() ?>
                    <button class="btn btn-sm btn-<?= $cls ?>" data-testid="wf-action-<?= $a ?>"><i class="bi bi-<?= $ic ?> me-1"></i><?= lang($l) ?></button></form>
            <?php endif; ?>
        <?php endforeach; ?>
        <?php if (in_array('edit', $doc['actions'], true) && can($perms['edit'] ?? '') && !empty($editUrl)): ?><a class="btn btn-sm btn-outline-secondary" href="<?= site_url($editUrl) ?>" data-testid="wf-action-edit"><i class="bi bi-pencil me-1"></i>Ubah</a><?php endif; ?>
    </div>
</div>
<?php if ($doc['history']): ?>
<div class="card erp-card mb-3"><div class="card-header py-1 small fw-semibold">Riwayat Workflow</div>
    <table class="table table-sm table-borderless mb-0 small" data-testid="workflow-history"><tbody>
    <?php foreach ($doc['history'] as $h): ?><tr><td class="text-muted" style="width:140px"><?= fmt_dt($h['acted_at']) ?></td><td><?= e($h['actor_name'] ?? 'sistem') ?></td><td><code><?= e($h['action']) ?></code> <?= e($h['from_state']) ?> → <?= e($h['to_state']) ?></td><td class="text-muted"><?= e($h['notes']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
<?php endif; ?>
