<?php
class Setting_repository extends Base_repository
{
    protected $table = 'system_settings';
    protected $columns = ['id', 'company_id', 'setting_group', 'setting_key', 'setting_value', 'value_type', 'description', 'is_editable', 'updated_by', 'updated_at'];

    /** Company override wins over global (NULL company). */
    public function allResolved(?int $companyId): array
    {
        $rows = $this->db->select($this->columns)->from($this->table)
            ->group_start()->where('company_id IS NULL', null, false)->or_where('company_id', $companyId)->group_end()
            ->order_by('company_id', 'ASC')->get()->result_array();
        $out = [];
        foreach ($rows as $r) {
            $out[$r['setting_key']] = $r;
        }
        return $out;
    }

    public function upsert(?int $companyId, string $key, $value, ?int $userId): void
    {
        $existing = $this->db->select('id')->from($this->table)->where('setting_key', $key);
        $existing = $companyId === null ? $existing->where('company_id IS NULL', null, false) : $existing->where('company_id', $companyId);
        $row = $existing->get()->row_array();
        if ($row) {
            $this->db->where('id', $row['id'])->update($this->table, ['setting_value' => $value, 'updated_by' => $userId]);
        } else {
            $global = $this->db->select('setting_group, value_type, description')->from($this->table)->where('setting_key', $key)->where('company_id IS NULL', null, false)->get()->row_array() ?: [];
            $this->db->insert($this->table, array_merge($global, ['company_id' => $companyId, 'setting_key' => $key, 'setting_value' => $value, 'updated_by' => $userId]));
        }
    }
}
