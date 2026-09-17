<?php $parents = ['' => '— (kategori utama)'] + array_column(array_filter($rows, fn($r) => !$r['parent_id']), 'name', 'id');
$this->load->view('master/_inline_crud', ['rows' => $rows, 'save_url' => 'master/categories/save', 'perm_prefix' => 'master.category', 'testid' => 'category',
    'fields' => ['code' => ['Kode', 'text'], 'name' => ['Nama', 'text'], 'parent_id' => ['Induk', 'select', $parents], 'is_active' => ['Aktif', 'check', null, 1]]]); ?>
