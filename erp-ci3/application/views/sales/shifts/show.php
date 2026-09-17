<?php $s = $shift; ?>
<div class="erp-page-head"><h5 class="mb-0 font-monospace"><?= e($s['shift_no']) ?></h5><a class="btn btn-sm btn-link" href="<?= site_url('sales/shifts') ?>">&larr; Daftar</a></div>
<div class="card erp-card"><div class="card-body"><dl class="row small mb-0">
<dt class="col-3">Status</dt><dd class="col-3"><?= status_badge($s['status']) ?></dd><dt class="col-3">Jumlah Transaksi</dt><dd class="col-3"><?= (int) $s['sale_count'] ?></dd>
<dt class="col-3">Dibuka</dt><dd class="col-3"><?= fmt_dt($s['opened_at']) ?></dd><dt class="col-3">Ditutup</dt><dd class="col-3"><?= $s['closed_at'] ? fmt_dt($s['closed_at']) : '-' ?></dd>
<dt class="col-3">Kas Awal</dt><dd class="col-3"><?= fmt_idr($s['opening_cash']) ?></dd><dt class="col-3">Total Penjualan</dt><dd class="col-3"><?= fmt_idr($s['total_sales']) ?></dd>
<dt class="col-3">Kas Diharapkan</dt><dd class="col-3"><?= $s['expected_cash'] !== null ? fmt_idr($s['expected_cash']) : '-' ?></dd><dt class="col-3">Kas Akhir</dt><dd class="col-3"><?= $s['closing_cash'] !== null ? fmt_idr($s['closing_cash']) : '-' ?></dd>
<dt class="col-3">Selisih</dt><dd class="col-9 <?= (float) $s['cash_variance'] < 0 ? 'text-danger' : '' ?>"><?= $s['cash_variance'] !== null ? fmt_idr($s['cash_variance']) : '-' ?></dd></dl></div></div>
