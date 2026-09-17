<?php $this->load->view('master/_inline_crud', ['rows' => $rows, 'save_url' => 'master/uoms/save', 'perm_prefix' => 'master.uom', 'testid' => 'uom',
    'fields' => ['code' => ['Kode', 'text'], 'name' => ['Nama', 'text'], 'is_active' => ['Aktif', 'check', null, 1]]]); ?>
