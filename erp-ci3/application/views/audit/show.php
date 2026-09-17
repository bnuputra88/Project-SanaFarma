<div class="card erp-card" data-testid="audit-detail"><div class="card-body">
<dl class="row small mb-3">
<?php foreach (['created_at' => 'Waktu', 'username' => 'Pengguna', 'module' => 'Modul', 'action' => 'Aksi', 'entity' => 'Entity', 'entity_id' => 'ID', 'reference_no' => 'Referensi', 'reason' => 'Alasan', 'ip_address' => 'IP', 'user_agent' => 'User Agent', 'channel' => 'Kanal', 'request_id' => 'Request ID'] as $k => $l): ?>
<dt class="col-sm-2"><?= $l ?></dt><dd class="col-sm-10 font-monospace"><?= e($row[$k] ?? '-') ?></dd><?php endforeach; ?>
</dl>
<div class="row g-3"><div class="col-md-6"><h6>Nilai Lama</h6><pre class="erp-pre" data-testid="audit-old"><?= e($row['old_values'] ? json_encode(json_decode($row['old_values']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '-') ?></pre></div>
<div class="col-md-6"><h6>Nilai Baru</h6><pre class="erp-pre" data-testid="audit-new"><?= e($row['new_values'] ? json_encode(json_decode($row['new_values']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '-') ?></pre></div></div>
<a class="btn btn-link btn-sm px-0" href="<?= site_url('audit') ?>">&larr; Kembali</a></div></div>
