<?php
/**
 * Demo seeder (idempotent). Reference data + realistic dummy transactions through the service layer.
 */
class Demo_seeder
{
    private $db;
    private $ctx;
    private $roles;
    private $inventory;
    private $auth;

    public function __construct(Db $db, Request_context $ctx, Role_repository $roles, Inventory_service $inventory, Auth_service $auth)
    {
        $this->db = $db;
        $this->ctx = $ctx;
        $this->roles = $roles;
        $this->inventory = $inventory;
        $this->auth = $auth;
    }

    public function run(callable $log): void
    {
        $ci = $this->db->ci;
        $companyId = $this->upsert('companies', ['code' => 'PT-SFI'], ['name' => 'PT Sehat Farma Indonesia', 'legal_name' => 'PT Sehat Farma Indonesia', 'npwp' => '00.000.000.0-000.000', 'address' => 'Jl. Contoh No. 1, Jakarta', 'email' => 'info@sehatfarma.test']);
        $hq = $this->upsert('branches', ['company_id' => $companyId, 'code' => 'HO'], ['name' => 'Kantor Pusat & PBF', 'branch_type' => 'DISTRIBUTION']);
        $apt = $this->upsert('branches', ['company_id' => $companyId, 'code' => 'APT01'], ['name' => 'Apotek Sehat Sudirman', 'branch_type' => 'RETAIL']);
        $log("Company/branches OK (company_id=$companyId)");

        $this->seedPermissions();
        $log('Permissions OK (' . count($this->roles->allPermissions()) . ')');
        $roleIds = $this->seedRoles($companyId);
        $log('Roles OK');

        $this->seedSettings();
        $log('Settings OK');

        $adminEmail = strtolower((string) Env::get('ADMIN_EMAIL', 'admin@example.com'));
        $adminPass = (string) Env::get('ADMIN_PASSWORD', 'Admin#2026!Secure');
        $adminId = $this->seedUser($companyId, $hq, 'admin', $adminEmail, 'Administrator Sistem', $adminPass, [$roleIds['ADMIN']], true);
        $users = [
            ['apoteker', 'apoteker@sehatfarma.test', 'Apt. Dewi Lestari, S.Farm', 'Apoteker#2026', ['PHARMACIST'], $apt],
            ['kasir', 'kasir@sehatfarma.test', 'Rina Kasir', 'Kasir#2026!', ['CASHIER'], $apt],
            ['gudang', 'gudang@sehatfarma.test', 'Budi Gudang', 'Gudang#2026!', ['WAREHOUSE'], $hq],
            ['purchasing', 'purchasing@sehatfarma.test', 'Sari Purchasing', 'Purchasing#2026', ['PURCHASING'], $hq],
            ['finance', 'finance@sehatfarma.test', 'Andi Finance', 'Finance#2026!', ['FINANCE'], $hq],
            ['manajer', 'manajer@sehatfarma.test', 'Hendra Manajer', 'Manajer#2026!', ['MANAGEMENT'], $hq],
            ['auditor', 'auditor@sehatfarma.test', 'Lina Auditor', 'Auditor#2026!', ['AUDITOR'], $hq],
            ['qa', 'qa@sehatfarma.test', 'Yoga Quality', 'Quality#2026!', ['COMPLIANCE'], $hq],
        ];
        foreach ($users as [$u, $e, $n, $p, $r, $b]) {
            $this->seedUser($companyId, $b, $u, $e, $n, $p, array_map(fn($c) => $roleIds[$c], $r), false);
        }
        $log("Users OK (admin: $adminEmail)");

        // context as admin for audited operations
        $this->ctx->user_id = $adminId;
        $this->ctx->username = 'admin';
        $this->ctx->company_id = $companyId;
        $this->ctx->branch_id = $hq;
        $this->ctx->is_superadmin = true;

        $whMain = $this->upsert('warehouses', ['company_id' => $companyId, 'code' => 'WH-HO'], ['branch_id' => $hq, 'name' => 'Gudang Pusat PBF', 'warehouse_type' => 'MAIN']);
        $whQ = $this->upsert('warehouses', ['company_id' => $companyId, 'code' => 'WH-QRT'], ['branch_id' => $hq, 'name' => 'Gudang Karantina', 'warehouse_type' => 'QUARANTINE']);
        $whCold = $this->upsert('warehouses', ['company_id' => $companyId, 'code' => 'WH-COLD'], ['branch_id' => $hq, 'name' => 'Cold Storage 2-8°C', 'warehouse_type' => 'COLD', 'is_cold_chain' => 1]);
        $whApt = $this->upsert('warehouses', ['company_id' => $companyId, 'code' => 'WH-APT01'], ['branch_id' => $apt, 'name' => 'Etalase & Gudang Apotek Sudirman', 'warehouse_type' => 'RETAIL']);
        foreach ([$whMain => ['A-01-01', 'A-01-02', 'A-02-01', 'B-01-01'], $whQ => ['Q-01'], $whCold => ['C-01'], $whApt => ['ETALASE', 'RAK-OBAT-KERAS', 'RAK-BEBAS']] as $wh => $codes) {
            foreach ($codes as $i => $c) {
                $this->upsert('locations', ['warehouse_id' => $wh, 'code' => $c], ['location_type' => 'BIN', 'is_default' => $i === 0 ? 1 : 0, 'is_quarantine' => $wh === $whQ ? 1 : 0]);
            }
        }
        $log('Warehouses/locations OK');

        $uom = [];
        foreach ([['TAB', 'Tablet'], ['KAP', 'Kapsul'], ['BTL', 'Botol'], ['STRIP', 'Strip'], ['BOX', 'Box'], ['TUBE', 'Tube'], ['VIAL', 'Vial'], ['AMP', 'Ampul'], ['SACH', 'Sachet'], ['PCS', 'Pcs']] as [$c, $n]) {
            $uom[$c] = $this->upsert('uoms', ['code' => $c], ['name' => $n]);
        }
        $cls = [];
        foreach ([['BEBAS', 'Obat Bebas', 0, 0], ['BEBAS_TERBATAS', 'Obat Bebas Terbatas', 0, 0], ['KERAS', 'Obat Keras', 1, 0], ['OOT', 'Obat-Obat Tertentu', 1, 1], ['PSIKOTROPIKA', 'Psikotropika', 1, 1], ['NARKOTIKA', 'Narkotika', 1, 1], ['ALKES', 'Alat Kesehatan', 0, 0]] as [$c, $n, $rx, $ctl]) {
            $cls[$c] = $this->upsert('drug_classifications', ['code' => $c], ['name' => $n, 'requires_prescription' => $rx, 'is_controlled' => $ctl, 'notes' => 'REGULATORY VERIFICATION REQUIRED: kewajiban pelaporan/penanganan per golongan dikonfigurasi di compliance module (Phase 7)']);
        }
        $cat = [];
        foreach ([['ANALGESIK', 'Analgesik & Antipiretik'], ['ANTIBIOTIK', 'Antibiotik'], ['VITAMIN', 'Vitamin & Suplemen'], ['SALURAN_CERNA', 'Saluran Cerna'], ['KARDIO', 'Kardiovaskular'], ['DIABETES', 'Antidiabetes'], ['VAKSIN', 'Vaksin & Biologis'], ['ALKES', 'Alat Kesehatan']] as [$c, $n]) {
            $cat[$c] = $this->upsert('product_categories', ['company_id' => $companyId, 'code' => $c], ['name' => $n]);
        }
        foreach ([['ADJUSTMENT', 'RUSAK', 'Barang rusak', 1], ['ADJUSTMENT', 'HILANG', 'Barang hilang/selisih', 1], ['ADJUSTMENT', 'ED', 'Kedaluwarsa', 0], ['ADJUSTMENT', 'KOREKSI', 'Koreksi saldo awal', 1], ['ADJUSTMENT', 'OPNAME', 'Hasil stock opname', 0],
            ['REVERSAL', 'SALAH_INPUT', 'Salah input', 1], ['QUARANTINE', 'RECALL', 'Penarikan produk (recall)', 1], ['QUARANTINE', 'SUHU', 'Penyimpangan suhu', 1], ['RETURN', 'RUSAK', 'Barang rusak', 0], ['CANCELLATION', 'BATAL_CUST', 'Dibatalkan pelanggan', 0]] as [$t, $c, $n, $rn]) {
            $this->upsert('reason_codes', ['company_id' => $companyId, 'reason_type' => $t, 'code' => $c], ['name' => $n, 'requires_note' => $rn]);
        }
        $log('Reference data OK');

        $products = [
            ['PCT-500-TAB', '8991234500011', 'Paracetamol 500 mg Tablet', 'Paracetamol', 'Generik', 'PT Kimia Farma', 'ANALGESIK', 'BEBAS', 'Tablet', '500 mg', 'TAB', 250, 500, 1, 1, 0, 0, 0, 2000],
            ['AMX-500-KAP', '8991234500028', 'Amoxicillin 500 mg Kapsul', 'Amoxicillin', 'Generik', 'PT Indofarma', 'ANTIBIOTIK', 'KERAS', 'Kapsul', '500 mg', 'KAP', 600, 1200, 1, 1, 1, 0, 0, 1000],
            ['OMZ-20-KAP', '8991234500035', 'Omeprazole 20 mg Kapsul', 'Omeprazole', 'Generik', 'PT Dexa Medica', 'SALURAN_CERNA', 'KERAS', 'Kapsul', '20 mg', 'KAP', 900, 1800, 1, 1, 1, 0, 0, 500],
            ['AML-10-TAB', '8991234500042', 'Amlodipine 10 mg Tablet', 'Amlodipine', 'Generik', 'PT Hexpharm Jaya', 'KARDIO', 'KERAS', 'Tablet', '10 mg', 'TAB', 400, 800, 1, 1, 1, 0, 0, 1000],
            ['MET-500-TAB', '8991234500059', 'Metformin 500 mg Tablet', 'Metformin HCl', 'Generik', 'PT Dexa Medica', 'DIABETES', 'KERAS', 'Tablet', '500 mg', 'TAB', 300, 600, 1, 1, 1, 0, 0, 1500],
            ['VITC-500-TAB', '8991234500066', 'Vitamin C 500 mg Tablet', 'Ascorbic Acid', 'Vitacimin', 'PT Takeda', 'VITAMIN', 'BEBAS', 'Tablet', '500 mg', 'TAB', 800, 1500, 1, 1, 0, 0, 0, 500],
            ['OBH-100-BTL', '8991234500073', 'OBH Sirup 100 mL', 'Succus Liquiritiae', 'OBH Combi', 'PT Combiphar', 'ANALGESIK', 'BEBAS_TERBATAS', 'Sirup', '100 mL', 'BTL', 12000, 18000, 1, 1, 0, 0, 0, 50],
            ['INS-GLA-PEN', '8991234500080', 'Insulin Glargine 100 IU/mL Pen 3 mL', 'Insulin Glargine', 'Lantus', 'Sanofi', 'DIABETES', 'KERAS', 'Injeksi', '100 IU/mL', 'PCS', 180000, 250000, 1, 1, 1, 1, 0, 20],
            ['ALP-05-TAB', '8991234500097', 'Alprazolam 0,5 mg Tablet', 'Alprazolam', 'Generik', 'PT Kimia Farma', 'ANALGESIK', 'PSIKOTROPIKA', 'Tablet', '0,5 mg', 'TAB', 1500, 3000, 1, 1, 1, 0, 1, 100],
            ['MASK-3PLY-BOX', '8991234500103', 'Masker Medis 3 Ply (Box 50)', null, 'Sensi', 'PT Arista Latindo', 'ALKES', 'ALKES', 'Alkes', null, 'BOX', 25000, 40000, 0, 0, 0, 0, 0, 30],
        ];
        $prodIds = [];
        foreach ($products as [$sku, $bc, $name, $gen, $brand, $mfr, $c, $k, $form, $str, $u, $buy, $sell, $bt, $et, $rx, $cold, $ctl, $reorder]) {
            $prodIds[$sku] = $this->upsert('products', ['company_id' => $companyId, 'sku' => $sku], ['barcode' => $bc, 'name' => $name, 'generic_name' => $gen, 'brand' => $brand, 'manufacturer_name' => $mfr,
                'category_id' => $cat[$c], 'classification_id' => $cls[$k], 'dosage_form' => $form, 'strength' => $str, 'base_uom_id' => $uom[$u], 'purchase_price' => $buy, 'selling_price' => $sell,
                'margin_pct' => $buy > 0 ? round(($sell - $buy) / $buy * 100, 3) : 0, 'is_batch_tracked' => $bt, 'is_expiry_tracked' => $et, 'requires_prescription' => $rx, 'is_cold_chain' => $cold, 'is_controlled' => $ctl,
                'storage_min_temp' => $cold ? 2 : null, 'storage_max_temp' => $cold ? 8 : null, 'reorder_point' => $reorder, 'min_stock' => $reorder, 'safety_stock' => round($reorder / 2), 'lead_time_days' => 7, 'created_by' => $adminId]);
        }
        foreach (['PCT-500-TAB' => [['STRIP', 10], ['BOX', 100]], 'AMX-500-KAP' => [['STRIP', 10], ['BOX', 100]], 'OMZ-20-KAP' => [['STRIP', 10]], 'AML-10-TAB' => [['STRIP', 10], ['BOX', 30]], 'MET-500-TAB' => [['STRIP', 10], ['BOX', 100]]] as $sku => $units) {
            foreach ($units as [$u, $f]) {
                $this->upsert('product_units', ['product_id' => $prodIds[$sku], 'uom_id' => $uom[$u]], ['conversion_factor' => $f, 'is_purchase_uom' => $u === 'BOX' ? 1 : 0, 'is_sales_uom' => 1]);
            }
        }
        $log('Products OK (' . count($prodIds) . ')');

        if ((int) $ci->from('stock_movements')->where('movement_type', 'OPENING_BALANCE')->count_all_results() === 0) {
            $y = (int) date('Y');
            $lines = [];
            $opening = [
                ['PCT-500-TAB', 'PCT2405A', ($y + 1) . '-05-31', 5000, 250], ['PCT-500-TAB', 'PCT2409B', ($y + 2) . '-09-30', 8000, 245],
                ['AMX-500-KAP', 'AMX2403X', date('Y-m-d', strtotime('+45 days')), 1200, 600], ['AMX-500-KAP', 'AMX2410Y', ($y + 1) . '-10-31', 3000, 590],
                ['OMZ-20-KAP', 'OMZ2401K', date('Y-m-d', strtotime('-10 days')), 300, 900], ['OMZ-20-KAP', 'OMZ2407L', ($y + 1) . '-07-31', 2000, 880],
                ['AML-10-TAB', 'AML2406P', ($y + 2) . '-06-30', 4000, 400], ['MET-500-TAB', 'MET2402Q', ($y + 1) . '-02-28', 6000, 300],
                ['VITC-500-TAB', 'VTC2408R', ($y + 1) . '-08-31', 2500, 800], ['OBH-100-BTL', 'OBH2405S', ($y + 1) . '-05-31', 150, 12000],
                ['ALP-05-TAB', 'ALP2404T', ($y + 1) . '-04-30', 400, 1500],
            ];
            foreach ($opening as [$sku, $batch, $exp, $qty, $cost]) {
                $lines[] = ['warehouse_id' => $whMain, 'product_id' => $prodIds[$sku], 'batch_no' => $batch, 'expiry_date' => $exp, 'manufacture_date' => date('Y-m-d', strtotime($exp . ' -2 years')), 'qty' => $qty, 'unit_cost' => $cost];
            }
            $lines[] = ['warehouse_id' => $whCold, 'product_id' => $prodIds['INS-GLA-PEN'], 'batch_no' => 'INS2406C', 'expiry_date' => ($y + 1) . '-06-30', 'qty' => 60, 'unit_cost' => 180000];
            $lines[] = ['warehouse_id' => $whMain, 'product_id' => $prodIds['MASK-3PLY-BOX'], 'qty' => 120, 'unit_cost' => 25000];
            $lines[] = ['warehouse_id' => $whApt, 'product_id' => $prodIds['PCT-500-TAB'], 'batch_no' => 'PCT2405A', 'expiry_date' => ($y + 1) . '-05-31', 'qty' => 500, 'unit_cost' => 250];
            $lines[] = ['warehouse_id' => $whApt, 'product_id' => $prodIds['AMX-500-KAP'], 'batch_no' => 'AMX2410Y', 'expiry_date' => ($y + 1) . '-10-31', 'qty' => 300, 'unit_cost' => 590];
            $mid = $this->inventory->post(['movement_type' => 'OPENING_BALANCE', 'ref_type' => 'seed', 'ref_no' => 'OPENING-' . date('Ymd'), 'notes' => 'Saldo awal demo'], $lines);
            $log("Opening balance movement #$mid OK (" . count($lines) . ' lines, incl. 1 expired + 1 near-expiry batch)');
        } else {
            $log('Opening balance already seeded, skipped');
        }
        $log('SEED COMPLETE');
    }

    private function seedPermissions(): void
    {
        $map = [
            'system' => ['dashboard' => ['view'], 'user' => ['view', 'create', 'edit'], 'role' => ['view', 'create', 'edit'], 'setting' => ['view', 'edit']],
            'master' => ['product' => ['view', 'create', 'edit', 'export'], 'category' => ['view', 'create', 'edit'], 'uom' => ['view', 'create', 'edit'], 'warehouse' => ['view', 'create', 'edit'], 'reason_code' => ['view', 'create', 'edit']],
            'inventory' => ['stock' => ['view', 'export'], 'movement' => ['view', 'reverse'], 'batch' => ['view', 'quarantine'],
                'adjustment' => ['view', 'create', 'edit', 'approve', 'cancel', 'post'], 'transfer' => ['view', 'create', 'edit', 'approve', 'cancel', 'post'], 'opname' => ['view', 'create', 'edit', 'approve', 'cancel', 'post']],
            'audit' => ['log' => ['view', 'export'], 'login_history' => ['view']],
        ];
        foreach ($map as $module => $resources) {
            foreach ($resources as $res => $actions) {
                foreach ($actions as $a) {
                    $this->roles->upsertPermission(['module' => $module, 'resource' => $res, 'action' => $a, 'code' => "$module.$res.$a"]);
                }
            }
        }
    }

    private function seedRoles(int $companyId): array
    {
        $all = array_column($this->roles->allPermissions(), 'code');
        $view = array_filter($all, fn($c) => substr($c, -5) === '.view');
        $defs = [
            'ADMIN' => ['Administrator', $all],
            'PHARMACIST' => ['Apoteker', array_merge($view, ['inventory.batch.quarantine', 'inventory.adjustment.create', 'inventory.adjustment.edit', 'inventory.opname.create', 'inventory.opname.edit'])],
            'CASHIER' => ['Kasir', ['system.dashboard.view', 'master.product.view', 'inventory.stock.view']],
            'PURCHASING' => ['Purchasing', array_merge($view, ['master.product.create', 'master.product.edit'])],
            'WAREHOUSE' => ['Gudang', array_merge($view, ['inventory.adjustment.create', 'inventory.adjustment.edit', 'inventory.transfer.create', 'inventory.transfer.edit', 'inventory.transfer.post', 'inventory.opname.create', 'inventory.opname.edit', 'inventory.batch.quarantine'])],
            'SALES' => ['Sales', ['system.dashboard.view', 'master.product.view', 'inventory.stock.view']],
            'DISTRIBUTION' => ['Distribusi', ['system.dashboard.view', 'master.product.view', 'inventory.stock.view', 'inventory.transfer.view', 'inventory.transfer.post']],
            'FINANCE' => ['Keuangan', array_merge($view, ['inventory.stock.export'])],
            'ACCOUNTING' => ['Akuntansi', array_merge($view, ['inventory.stock.export'])],
            'MANAGEMENT' => ['Manajemen', array_merge($view, ['inventory.adjustment.approve', 'inventory.adjustment.post', 'inventory.adjustment.cancel', 'inventory.transfer.approve', 'inventory.transfer.cancel', 'inventory.opname.approve', 'inventory.opname.post', 'inventory.opname.cancel', 'inventory.movement.reverse', 'master.product.export', 'audit.log.export'])],
            'AUDITOR' => ['Auditor', array_merge($view, ['audit.log.export', 'inventory.stock.export'])],
            'COMPLIANCE' => ['Compliance / Quality', array_merge($view, ['inventory.batch.quarantine', 'inventory.movement.reverse'])],
        ];
        $ids = [];
        foreach ($defs as $code => [$name, $perms]) {
            $ids[$code] = $this->upsert('roles', ['company_id' => $companyId, 'code' => $code], ['name' => $name, 'is_system' => 1]);
            $this->roles->syncPermissions($ids[$code], $this->roles->permissionIdsByCodes(array_values(array_unique($perms))));
        }
        return $ids;
    }

    private function seedSettings(): void
    {
        $rows = [
            ['inventory', 'inventory.costing_method', 'FIFO', 'string', 'Metode costing: FIFO | AVERAGE'],
            ['inventory', 'inventory.allow_negative_stock', '0', 'bool', 'Izinkan stok negatif (global). Default: tidak'],
            ['inventory', 'inventory.fefo_allow_expired', '0', 'bool', 'FEFO boleh mengalokasikan batch kedaluwarsa (harus 0 untuk obat)'],
            ['inventory', 'inventory.near_expiry_days', '90', 'int', 'Ambang hari untuk peringatan mendekati kedaluwarsa'],
            ['workflow', 'workflow.enforce_segregation', '1', 'bool', 'Pembuat dokumen tidak boleh menyetujui dokumen sendiri'],
            ['security', 'security.password_min_length', '10', 'int', 'Panjang minimum password'],
            ['security', 'security.password_require_upper', '1', 'bool', 'Wajib huruf besar'],
            ['security', 'security.password_require_lower', '1', 'bool', 'Wajib huruf kecil'],
            ['security', 'security.password_require_digit', '1', 'bool', 'Wajib angka'],
            ['security', 'security.password_require_symbol', '1', 'bool', 'Wajib simbol'],
            ['security', 'security.password_max_age_days', '90', 'int', 'Umur maksimum password (0 = tidak kedaluwarsa)'],
            ['company', 'company.timezone', 'Asia/Jakarta', 'string', 'Zona waktu operasional'],
            ['company', 'company.currency', 'IDR', 'string', 'Mata uang'],
        ];
        foreach ($rows as [$g, $k, $v, $t, $d]) {
            $exists = $this->db->ci->from('system_settings')->where('setting_key', $k)->where('company_id IS NULL', null, false)->count_all_results();
            if (!$exists) {
                $this->db->ci->insert('system_settings', ['company_id' => null, 'setting_group' => $g, 'setting_key' => $k, 'setting_value' => $v, 'value_type' => $t, 'description' => $d]);
            }
        }
    }

    private function seedUser(int $companyId, int $branchId, string $username, string $email, string $name, string $password, array $roleIds, bool $super): int
    {
        $ci = $this->db->ci;
        $row = $ci->select('id, password_hash')->from('users')->where('username', $username)->get()->row_array();
        $data = ['company_id' => $companyId, 'branch_id' => $branchId, 'email' => $email, 'full_name' => $name, 'is_superadmin' => $super ? 1 : 0, 'is_active' => 1];
        if ($row) {
            if (!Password_policy::verify($password, $row['password_hash'])) {
                $data['password_hash'] = Password_policy::hash($password);
                $data['password_changed_at'] = date('Y-m-d H:i:s');
            }
            $ci->where('id', $row['id'])->update('users', $data);
            $id = (int) $row['id'];
        } else {
            $ci->insert('users', $data + ['username' => $username, 'password_hash' => Password_policy::hash($password), 'password_changed_at' => date('Y-m-d H:i:s')]);
            $id = (int) $ci->insert_id();
        }
        $ci->where('user_id', $id)->delete('user_roles');
        $ci->insert_batch('user_roles', array_map(fn($r) => ['user_id' => $id, 'role_id' => $r], $roleIds));
        return $id;
    }

    private function upsert(string $table, array $key, array $data): int
    {
        $ci = $this->db->ci;
        $row = $ci->select('id')->from($table)->where($key)->get()->row_array();
        if ($row) {
            $ci->where('id', $row['id'])->update($table, $data);
            return (int) $row['id'];
        }
        $ci->insert($table, $key + $data);
        return (int) $ci->insert_id();
    }
}
