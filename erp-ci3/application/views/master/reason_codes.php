<?php $types = ['ADJUSTMENT' => 'Penyesuaian', 'RETURN' => 'Retur', 'CANCELLATION' => 'Pembatalan', 'QUARANTINE' => 'Karantina', 'REVERSAL' => 'Pembalikan', 'OPNAME' => 'Opname'];
$this->load->view('master/_inline_crud', ['rows' => $rows, 'save_url' => 'master/reason-codes/save', 'perm_prefix' => 'master.reason_code', 'testid' => 'reason',
    'fields' => ['reason_type' => ['Tipe', 'select', $types], 'code' => ['Kode', 'text'], 'name' => ['Nama', 'text'], 'requires_note' => ['Wajib catatan', 'check', null, 0], 'is_active' => ['Aktif', 'check', null, 1]]]); ?>
