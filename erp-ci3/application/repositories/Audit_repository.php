<?php
/** Append-only audit log store. No update/delete methods by design. */
class Audit_repository extends Base_repository
{
    protected $table = 'audit_logs';
    protected $columns = ['id', 'company_id', 'branch_id', 'user_id', 'username', 'module', 'action', 'entity', 'entity_id', 'reference_no',
        'old_values', 'new_values', 'reason', 'ip_address', 'user_agent', 'channel', 'request_id', 'created_at'];

    public function paginate(array $input, int $companyId): Paginator
    {
        $qb = $this->db->select($this->columns)->from($this->table)->where('company_id', $companyId);
        foreach (['module', 'action', 'entity', 'user_id'] as $f) {
            if (!empty($input[$f])) {
                $qb->where($f, $input[$f]);
            }
        }
        if (!empty($input['entity_id'])) {
            $qb->where('entity_id', $input['entity_id']);
        }
        if (!empty($input['reference_no'])) {
            $qb->like('reference_no', $input['reference_no']);
        }
        if (!empty($input['date_from'])) {
            $qb->where('created_at >=', $input['date_from'] . ' 00:00:00');
        }
        if (!empty($input['date_to'])) {
            $qb->where('created_at <=', $input['date_to'] . ' 23:59:59');
        }
        return $this->paginateQuery($qb, $input, ['created_at' => 'created_at', 'module' => 'module', 'username' => 'username'], 'id DESC');
    }

    public function forEntity(string $entity, string $entityId, int $limit = 50): array
    {
        return $this->db->select($this->columns)->from($this->table)->where(['entity' => $entity, 'entity_id' => $entityId])
            ->order_by('id', 'DESC')->limit($limit)->get()->result_array();
    }

    public function loginHistory(array $input, int $companyId): Paginator
    {
        $qb = $this->db->select('lh.id, lh.user_id, lh.username_attempted, lh.status, lh.channel, lh.ip_address, lh.user_agent, lh.created_at, u.full_name')
            ->from('login_history lh')->join('users u', 'u.id = lh.user_id', 'left');
        if (!empty($input['status'])) {
            $qb->where('lh.status', $input['status']);
        }
        if (!empty($input['q'])) {
            $qb->like('lh.username_attempted', $input['q']);
        }
        return $this->paginateQuery($qb, $input, ['created_at' => 'lh.created_at'], 'lh.id DESC');
    }
}
