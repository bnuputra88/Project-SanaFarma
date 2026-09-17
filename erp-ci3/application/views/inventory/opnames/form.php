<form method="post" class="col-lg-6" data-testid="opname-form"><?= csrf_field() ?>
<div class="card erp-card mb-3"><div class="card-body row g-2">
    <div class="col-md-6"><label class="form-label">Gudang *</label><select class="form-select form-select-sm" name="warehouse_id" required data-testid="opn-warehouse"><option value="">—</option><?php foreach ($warehouses as $w): ?><option value="<?= $w['id'] ?>"><?= e($w['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-3"><label class="form-label">Tanggal *</label><input class="form-control form-control-sm" type="date" name="opname_date" value="<?= date('Y-m-d') ?>" required data-testid="opn-date"></div>
    <div class="col-md-3"><label class="form-label">Tipe</label><select class="form-select form-select-sm" name="opname_type"><option value="FULL">Full count</option><option value="CYCLE">Cycle count</option></select></div>
    <div class="col-12"><label class="form-label">Catatan</label><input class="form-control form-control-sm" name="notes"></div>
</div></div>
<p class="small text-muted">Saat "Mulai Hitung", sistem membekukan snapshot saldo gudang sebagai qty sistem. Selisih diposting sebagai mutasi OPNAME setelah disetujui.</p>
<button class="btn btn-primary" data-testid="opn-save"><i class="bi bi-save me-1"></i>Buat Opname</button> <a class="btn btn-link" href="<?= site_url('inventory/opnames') ?>">Batal</a>
</form>
