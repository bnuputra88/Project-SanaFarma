<?php
class Supplier_repository extends Base_repository
{
    protected $table = 'suppliers';
    protected $softDelete = true;
    protected $columns = ['id', 'company_id', 'code', 'name', 'legal_name', 'supplier_type', 'npwp', 'license_no', 'contact_person', 'phone', 'email', 'address',
        'payment_term_days', 'currency', 'is_active', 'notes', 'created_by', 'updated_by', 'created_at', 'updated_at'];

    public function paginate(array $input, int $companyId): Paginator
    {
        $qb = $this->db->select('id, code, name, supplier_type, contact_person, phone, email, payment_term_days, is_active')
            ->from($this->table)->where('company_id', $companyId)->where('deleted_at IS NULL', null, false);
        if (!empty($input['q'])) {
            $qb->group_start()->like('name', $input['q'])->or_like('code', $input['q'])->or_like('contact_person', $input['q'])->group_end();
        }
        if (isset($input['is_active']) && $input['is_active'] !== '') {
            $qb->where('is_active', (int) $input['is_active']);
        }
        if (!empty($input['supplier_type'])) {
            $qb->where('supplier_type', $input['supplier_type']);
        }
        return $this->paginateQuery($qb, $input, ['code' => 'code', 'name' => 'name', 'supplier_type' => 'supplier_type'], 'name');
    }

    public function active(int $companyId): array
    {
        return $this->db->select('id, code, name, supplier_type, payment_term_days, currency')->from($this->table)
            ->where(['company_id' => $companyId, 'is_active' => 1])->where('deleted_at IS NULL', null, false)->order_by('name')->get()->result_array();
    }

    public function save(int $companyId, array $d): int
    {
        $data = ['code' => strtoupper(trim($d['code'])), 'name' => trim($d['name']), 'legal_name' => $d['legal_name'] ?: null, 'supplier_type' => $d['supplier_type'] ?? 'DISTRIBUTOR',
            'npwp' => $d['npwp'] ?: null, 'license_no' => $d['license_no'] ?: null, 'contact_person' => $d['contact_person'] ?: null, 'phone' => $d['phone'] ?: null,
            'email' => $d['email'] ?: null, 'address' => $d['address'] ?: null, 'payment_term_days' => (int) ($d['payment_term_days'] ?? 0),
            'currency' => strtoupper($d['currency'] ?? 'IDR'), 'is_active' => isset($d['is_active']) ? (int) (bool) $d['is_active'] : 1, 'notes' => $d['notes'] ?: null];
        if (!empty($d['id'])) {
            $data['updated_by'] = $d['_actor'] ?? null;
            $this->update((int) $d['id'], $data);
            return (int) $d['id'];
        }
        return $this->insert($data + ['company_id' => $companyId, 'created_by' => $d['_actor'] ?? null]);
    }
}
