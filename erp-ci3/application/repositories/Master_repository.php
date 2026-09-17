<?php
/** Simple master repositories sharing one file would break autoload; keep small classes separate. */
class Master_repository extends Base_repository
{
    protected $table = 'product_categories';

    public function saveCategory(int $companyId, array $d): int
    {
        $data = ['company_id' => $companyId, 'code' => $d['code'], 'name' => $d['name'], 'parent_id' => $d['parent_id'] ?: null, 'is_active' => !empty($d['is_active']) ? 1 : 0];
        if (!empty($d['id'])) {
            $this->db->where('id', (int) $d['id'])->update('product_categories', $data);
            return (int) $d['id'];
        }
        $this->db->insert('product_categories', $data);
        return (int) $this->db->insert_id();
    }

    public function saveUom(array $d): int
    {
        $data = ['code' => strtoupper($d['code']), 'name' => $d['name'], 'is_active' => !empty($d['is_active']) ? 1 : 0];
        if (!empty($d['id'])) {
            $this->db->where('id', (int) $d['id'])->update('uoms', $data);
            return (int) $d['id'];
        }
        $this->db->insert('uoms', $data);
        return (int) $this->db->insert_id();
    }

    public function reasonCodes(int $companyId, ?string $type = null, bool $activeOnly = false): array
    {
        $qb = $this->db->select('id, reason_type, code, name, requires_note, is_active')->from('reason_codes')->where('company_id', $companyId);
        if ($type) {
            $qb->where('reason_type', $type);
        }
        if ($activeOnly) {
            $qb->where('is_active', 1);
        }
        return $qb->order_by('reason_type, name')->get()->result_array();
    }

    public function findReason(int $id): ?array
    {
        $row = $this->db->select('id, company_id, reason_type, code, name, requires_note, is_active')->from('reason_codes')->where('id', $id)->get()->row_array();
        return $row ?: null;
    }

    public function saveReasonCode(int $companyId, array $d): int
    {
        $data = ['company_id' => $companyId, 'reason_type' => $d['reason_type'], 'code' => strtoupper($d['code']), 'name' => $d['name'],
            'requires_note' => !empty($d['requires_note']) ? 1 : 0, 'is_active' => !empty($d['is_active']) ? 1 : 0];
        if (!empty($d['id'])) {
            $this->db->where('id', (int) $d['id'])->update('reason_codes', $data);
            return (int) $d['id'];
        }
        $this->db->insert('reason_codes', $data);
        return (int) $this->db->insert_id();
    }

    public function codeExists(string $table, array $where, ?int $excludeId): bool
    {
        $qb = $this->db->from($table)->where($where);
        if ($excludeId) {
            $qb->where('id !=', $excludeId);
        }
        return $qb->count_all_results() > 0;
    }
}
