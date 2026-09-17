<?php $types = ['MAIN' => 'Utama', 'RETAIL' => 'Retail/Apotek', 'TRANSIT' => 'Transit', 'QUARANTINE' => 'Karantina', 'RETURN' => 'Retur', 'COLD' => 'Cold Storage'];
foreach ($rows as &$r) { $r['branch_link'] = $r['branch_name']; } unset($r);
$this->load->view('master/_inline_crud', ['rows' => $rows, 'save_url' => 'master/warehouses/save', 'perm_prefix' => 'master.warehouse', 'testid' => 'warehouse',
    'fields' => ['code' => ['Kode', 'text'], 'name' => ['Nama', 'text'], 'branch_id' => ['Cabang', 'select', array_column($branches, 'name', 'id')], 'warehouse_type' => ['Tipe', 'select', $types],
        'is_cold_chain' => ['Cold chain', 'check', null, 0], 'allow_negative_stock' => ['Izinkan stok negatif', 'check', null, 0], 'is_active' => ['Aktif', 'check', null, 1]]]); ?>
<div class="mt-2 small text-muted">Lokasi (rak/bin): <?php foreach ($rows as $r): ?><a class="me-2" href="<?= site_url("master/warehouses/{$r['id']}/locations") ?>" data-testid="warehouse-locations-<?= $r['id'] ?>"><?= e($r['code']) ?> (<?= $r['location_count'] ?>)</a><?php endforeach; ?></div>
