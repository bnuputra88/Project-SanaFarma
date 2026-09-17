<?php
class Customer_repository extends Base_repository
{
    protected $table = 'customers';
    protected $softDelete = true;
    protected $columns = ['id', 'company_id', 'code', 'name', 'customer_type', 'nik', 'phone', 'email', 'address', 'date_of_birth', 'allergy_notes', 'is_active', 'created_by', 'updated_by', 'created_at', 'updated_at'];

    public function paginate(array $input, int $companyId): Paginator
    {
        $qb = $this->db->select('id, code, name, customer_type, phone, email, is_active')->from($this->table)->where('company_id', $companyId)->where('deleted_at IS NULL', null, false);
        if (!empty($input['q'])) {
            $qb->group_start()->like('name', $input['q'])->or_like('code', $input['q'])->or_like('phone', $input['q'])->or_like('nik', $input['q'])->group_end();
        }
        if (!empty($input['customer_type'])) {
            $qb->where('customer_type', $input['customer_type']);
        }
        return $this->paginateQuery($qb, $input, ['code' => 'code', 'name' => 'name', 'customer_type' => 'customer_type'], 'name');
    }

    public function search(int $companyId, string $q): array
    {
        return $this->db->select('id, code, name, customer_type, phone')->from($this->table)->where(['company_id' => $companyId, 'is_active' => 1])->where('deleted_at IS NULL', null, false)
            ->group_start()->like('name', $q)->or_like('code', $q)->or_like('phone', $q)->group_end()->order_by('name')->limit(10)->get()->result_array();
    }
}
