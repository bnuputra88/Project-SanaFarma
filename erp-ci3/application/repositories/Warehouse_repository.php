<?php
class Warehouse_repository extends Base_repository
{
    protected $table = 'warehouses';
    protected $columns = ['id', 'company_id', 'branch_id', 'code', 'name', 'warehouse_type', 'is_cold_chain', 'allow_negative_stock', 'address', 'is_active', 'created_at', 'updated_at'];

    public function listWithBranch(int $companyId): array
    {
        return $this->db->select('w.id, w.code, w.name, w.warehouse_type, w.is_cold_chain, w.allow_negative_stock, w.is_active, w.branch_id, b.name AS branch_name,
            (SELECT COUNT(*) FROM locations l WHERE l.warehouse_id = w.id) AS location_count', false)
            ->from('warehouses w')->join('branches b', 'b.id = w.branch_id')->where('w.company_id', $companyId)->order_by('b.name, w.name')->get()->result_array();
    }

    public function active(int $companyId): array
    {
        return $this->db->select('id, branch_id, code, name, warehouse_type, allow_negative_stock')->from('warehouses')->where(['company_id' => $companyId, 'is_active' => 1])->order_by('name')->get()->result_array();
    }

    public function save(int $companyId, array $d): int
    {
        $data = ['company_id' => $companyId, 'branch_id' => (int) $d['branch_id'], 'code' => strtoupper($d['code']), 'name' => $d['name'], 'warehouse_type' => $d['warehouse_type'],
            'is_cold_chain' => !empty($d['is_cold_chain']) ? 1 : 0, 'allow_negative_stock' => !empty($d['allow_negative_stock']) ? 1 : 0, 'address' => $d['address'] ?: null, 'is_active' => !empty($d['is_active']) ? 1 : 0];
        if (!empty($d['id'])) {
            $this->update((int) $d['id'], $data);
            return (int) $d['id'];
        }
        return $this->insert($data);
    }

    public function locations(int $warehouseId, bool $activeOnly = false): array
    {
        $qb = $this->db->select('id, warehouse_id, code, name, location_type, parent_id, is_default, is_quarantine, is_active')->from('locations')->where('warehouse_id', $warehouseId);
        if ($activeOnly) {
            $qb->where('is_active', 1);
        }
        return $qb->order_by('code')->get()->result_array();
    }

    public function findLocation(int $id): ?array
    {
        $row = $this->db->select('id, warehouse_id, code, name, location_type, is_default, is_quarantine, is_active')->from('locations')->where('id', $id)->get()->row_array();
        return $row ?: null;
    }

    public function defaultLocation(int $warehouseId, bool $quarantine = false): ?int
    {
        $row = $this->db->select('id')->from('locations')->where(['warehouse_id' => $warehouseId, 'is_active' => 1, 'is_quarantine' => $quarantine ? 1 : 0])
            ->order_by('is_default', 'DESC')->order_by('id')->limit(1)->get()->row_array();
        return $row ? (int) $row['id'] : null;
    }

    public function saveLocation(int $warehouseId, array $d): int
    {
        $data = ['warehouse_id' => $warehouseId, 'code' => strtoupper($d['code']), 'name' => $d['name'] ?: null, 'location_type' => $d['location_type'], 'parent_id' => $d['parent_id'] ?: null,
            'is_default' => !empty($d['is_default']) ? 1 : 0, 'is_quarantine' => !empty($d['is_quarantine']) ? 1 : 0, 'is_active' => !empty($d['is_active']) ? 1 : 0];
        if ($data['is_default']) {
            $this->db->where('warehouse_id', $warehouseId)->update('locations', ['is_default' => 0]);
        }
        if (!empty($d['id'])) {
            $this->db->where('id', (int) $d['id'])->update('locations', $data);
            return (int) $d['id'];
        }
        $this->db->insert('locations', $data);
        return (int) $this->db->insert_id();
    }
}
