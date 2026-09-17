<?php
/** Document numbering with row-level lock (SELECT ... FOR UPDATE) — must be called inside a transaction. */
class Sequence_repository extends Base_repository
{
    protected $table = 'document_sequences';

    public function nextNumber(int $companyId, ?int $branchId, string $docType, string $periodKey, string $defaultPattern): array
    {
        $qb = $this->db->select('id, pattern, last_number')->from($this->table)
            ->where(['company_id' => $companyId, 'doc_type' => $docType, 'period_key' => $periodKey]);
        $qb = $branchId === null ? $qb->where('branch_id IS NULL', null, false) : $qb->where('branch_id', $branchId);
        $sql = $qb->get_compiled_select('', false) . ' FOR UPDATE';
        $this->db->reset_query();
        $row = $this->db->query($sql)->row_array();

        if (!$row) {
            // Pattern may be customized per company on an existing row of any period
            $tpl = $this->db->select('pattern')->from($this->table)->where(['company_id' => $companyId, 'doc_type' => $docType])->limit(1)->get()->row_array();
            $pattern = $tpl['pattern'] ?? $defaultPattern;
            $this->db->query("INSERT IGNORE INTO document_sequences (company_id, branch_id, doc_type, period_key, pattern, last_number) VALUES (?,?,?,?,?,0)",
                [$companyId, $branchId, $docType, $periodKey, $pattern]);
            $row = $this->db->query($sql)->row_array();
        }
        $next = (int) $row['last_number'] + 1;
        $this->db->where('id', $row['id'])->update($this->table, ['last_number' => $next]);
        return ['number' => $next, 'pattern' => $row['pattern']];
    }
}
