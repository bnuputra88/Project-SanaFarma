<?php
$menu = [
    ['Dashboard', 'dashboard', 'bi-speedometer2', 'system.dashboard.view'],
    ['Master Data', null, 'bi-database', null, [
        ['Produk / Obat', 'master/products', 'master.product.view'], ['Kategori', 'master/categories', 'master.category.view'], ['Satuan (UOM)', 'master/uoms', 'master.uom.view'],
        ['Gudang & Lokasi', 'master/warehouses', 'master.warehouse.view'], ['Kode Alasan', 'master/reason-codes', 'master.reason_code.view']]],
    ['Inventori', null, 'bi-boxes', null, [
        ['Saldo Stok', 'inventory/stock', 'inventory.stock.view'], ['Batch & Kedaluwarsa', 'inventory/batches', 'inventory.batch.view'], ['Stok Mendekati ED', 'inventory/stock/expiry', 'inventory.stock.view'],
        ['Buku Besar Stok', 'inventory/stock/ledger', 'inventory.stock.view'], ['Mutasi Stok', 'inventory/movements', 'inventory.movement.view'],
        ['Penyesuaian', 'inventory/adjustments', 'inventory.adjustment.view'], ['Transfer', 'inventory/transfers', 'inventory.transfer.view'], ['Stock Opname', 'inventory/opnames', 'inventory.opname.view']]],
    ['Sistem', null, 'bi-gear', null, [
        ['Pengguna', 'system/users', 'system.user.view'], ['Role & Hak Akses', 'system/roles', 'system.role.view'], ['Parameter', 'system/settings', 'system.setting.view'],
        ['Audit Trail', 'audit', 'audit.log.view'], ['Riwayat Login', 'system/login-history', 'audit.login_history.view']]],
];
$current = uri_string();
?>
<aside class="erp-sidebar" id="sidebar" data-testid="sidebar">
    <div class="erp-brand"><i class="bi bi-capsule-pill"></i><span>Pharma<strong>ERP</strong></span></div>
    <nav class="erp-nav">
        <?php foreach ($menu as $i => $m): ?>
            <?php if (empty($m[4])): if (!can($m[3])) continue; ?>
                <a class="erp-nav-link <?= $current === $m[1] ? 'active' : '' ?>" href="<?= site_url($m[1]) ?>" data-testid="nav-<?= e($m[1]) ?>"><i class="bi <?= $m[2] ?>"></i><span><?= e($m[0]) ?></span></a>
            <?php else:
                $children = array_filter($m[4], fn($c) => can($c[2]));
                if (!$children) continue;
                $open = (bool) array_filter($children, fn($c) => strpos($current, $c[1]) === 0); ?>
                <div class="erp-nav-group <?= $open ? 'open' : '' ?>">
                    <button class="erp-nav-link erp-nav-toggle" type="button" data-testid="nav-group-<?= $i ?>"><i class="bi <?= $m[2] ?>"></i><span><?= e($m[0]) ?></span><i class="bi bi-chevron-down ms-auto small"></i></button>
                    <div class="erp-nav-children">
                        <?php foreach ($children as $c): ?><a class="erp-nav-child <?= $current === $c[1] ? 'active' : '' ?>" href="<?= site_url($c[1]) ?>" data-testid="nav-<?= e(str_replace('/', '-', $c[1])) ?>"><?= e($c[0]) ?></a><?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>
</aside>
