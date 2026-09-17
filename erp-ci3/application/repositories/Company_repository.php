<?php
class Company_repository extends Base_repository
{
    protected $table = 'companies';
    protected $columns = ['id', 'code', 'name', 'legal_name', 'npwp', 'address', 'phone', 'email', 'is_active'];

    public function branches(int $companyId, bool $activeOnly = true): array
    {
        $qb = $this->db->select('id, company_id, code, name, branch_type, address, phone, is_active')->from('branches')->where('company_id', $companyId);
        if ($activeOnly) {
            $qb->where('is_active', 1);
        }
        return $qb->order_by('name')->get()->result_array();
    }

    public function findBranch(int $id): ?array
    {
        $row = $this->db->select('id, company_id, code, name, branch_type, is_active')->from('branches')->where('id', $id)->get()->row_array();
        return $row ?: null;
    }
}
