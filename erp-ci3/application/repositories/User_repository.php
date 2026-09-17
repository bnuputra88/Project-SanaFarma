<?php
class User_repository extends Base_repository
{
    protected $table = 'users';
    protected $softDelete = true;
    protected $columns = ['id', 'company_id', 'branch_id', 'username', 'email', 'full_name', 'phone', 'is_superadmin', 'is_active',
        'must_change_password', 'password_changed_at', 'mfa_enabled', 'last_login_at', 'last_login_ip', 'current_session_id', 'created_at', 'updated_at'];

    public function findForLogin(string $identifier): ?array
    {
        $row = $this->db->select(array_merge($this->columns, ['password_hash']))->from($this->table)
            ->group_start()->where('username', $identifier)->or_where('email', strtolower($identifier))->group_end()
            ->where('deleted_at IS NULL', null, false)->limit(1)->get()->row_array();
        return $row ?: null;
    }

    public function findWithHash(int $id): ?array
    {
        $row = $this->db->select(array_merge($this->columns, ['password_hash']))->from($this->table)->where('id', $id)->get()->row_array();
        return $row ?: null;
    }

    public function permissions(int $userId): array
    {
        return array_column($this->db->select('p.code')->from('permissions p')
            ->join('role_permissions rp', 'rp.permission_id = p.id')
            ->join('user_roles ur', 'ur.role_id = rp.role_id')
            ->join('roles r', 'r.id = ur.role_id AND r.is_active = 1')
            ->where('ur.user_id', $userId)->group_by('p.code')->get()->result_array(), 'code');
    }

    public function roleIds(int $userId): array
    {
        return array_map('intval', array_column($this->db->select('role_id')->from('user_roles')->where('user_id', $userId)->get()->result_array(), 'role_id'));
    }

    public function syncRoles(int $userId, array $roleIds): void
    {
        $this->db->where('user_id', $userId)->delete('user_roles');
        $rows = array_map(fn($r) => ['user_id' => $userId, 'role_id' => (int) $r], array_unique($roleIds));
        if ($rows) {
            $this->db->insert_batch('user_roles', $rows);
        }
    }

    public function paginate(array $input, int $companyId): Paginator
    {
        $qb = $this->db->select('u.id, u.username, u.email, u.full_name, u.is_active, u.is_superadmin, u.last_login_at, b.name AS branch_name,
            (SELECT GROUP_CONCAT(r.name SEPARATOR ", ") FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = u.id) AS role_names', false)
            ->from('users u')->join('branches b', 'b.id = u.branch_id', 'left')
            ->where('u.company_id', $companyId)->where('u.deleted_at IS NULL', null, false);
        if (!empty($input['q'])) {
            $qb->group_start()->like('u.username', $input['q'])->or_like('u.full_name', $input['q'])->or_like('u.email', $input['q'])->group_end();
        }
        if (isset($input['is_active']) && $input['is_active'] !== '') {
            $qb->where('u.is_active', (int) $input['is_active']);
        }
        return $this->paginateQuery($qb, $input, ['username' => 'u.username', 'full_name' => 'u.full_name', 'last_login_at' => 'u.last_login_at'], 'u.full_name');
    }
}
