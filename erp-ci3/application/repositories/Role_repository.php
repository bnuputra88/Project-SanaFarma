<?php
class Role_repository extends Base_repository
{
    protected $table = 'roles';
    protected $columns = ['id', 'company_id', 'code', 'name', 'description', 'is_system', 'is_active', 'created_at', 'updated_at'];

    public function allPermissions(): array
    {
        return $this->db->select('id, module, resource, action, code, description')->from('permissions')->order_by('module, resource, action')->get()->result_array();
    }

    public function permissionIds(int $roleId): array
    {
        return array_map('intval', array_column($this->db->select('permission_id')->from('role_permissions')->where('role_id', $roleId)->get()->result_array(), 'permission_id'));
    }

    public function syncPermissions(int $roleId, array $permissionIds): void
    {
        $this->db->where('role_id', $roleId)->delete('role_permissions');
        $rows = array_map(fn($p) => ['role_id' => $roleId, 'permission_id' => (int) $p], array_unique($permissionIds));
        if ($rows) {
            $this->db->insert_batch('role_permissions', $rows);
        }
    }

    public function permissionIdsByCodes(array $codes): array
    {
        if (!$codes) {
            return [];
        }
        return array_map('intval', array_column($this->db->select('id')->from('permissions')->where_in('code', $codes)->get()->result_array(), 'id'));
    }

    public function upsertPermission(array $perm): void
    {
        $existing = $this->db->select('id')->from('permissions')->where('code', $perm['code'])->get()->row_array();
        if (!$existing) {
            $this->db->insert('permissions', $perm);
        }
    }

    public function listWithCounts(int $companyId): array
    {
        return $this->db->select('r.id, r.code, r.name, r.description, r.is_system, r.is_active,
            (SELECT COUNT(*) FROM role_permissions rp WHERE rp.role_id = r.id) AS permission_count,
            (SELECT COUNT(*) FROM user_roles ur WHERE ur.role_id = r.id) AS user_count', false)
            ->from('roles r')->where('r.company_id', $companyId)->order_by('r.name')->get()->result_array();
    }
}
