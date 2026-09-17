<?php
class Workflow_repository extends Base_repository
{
    protected $table = 'workflow_instances';
    protected $columns = ['id', 'doc_type', 'doc_id', 'doc_no', 'current_state', 'is_final', 'created_at', 'updated_at'];

    public function ensureInstance(string $docType, int $docId, ?string $docNo, string $state): int
    {
        $row = $this->findBy(['doc_type' => $docType, 'doc_id' => $docId]);
        if ($row) {
            return (int) $row['id'];
        }
        return $this->insert(['doc_type' => $docType, 'doc_id' => $docId, 'doc_no' => $docNo, 'current_state' => $state]);
    }

    public function recordAction(int $instanceId, string $action, string $from, string $to, bool $isFinal, ?int $actorId, ?string $notes): void
    {
        $this->db->where('id', $instanceId)->update($this->table, ['current_state' => $to, 'is_final' => $isFinal ? 1 : 0]);
        $this->db->insert('workflow_actions', ['instance_id' => $instanceId, 'action' => $action, 'from_state' => $from, 'to_state' => $to,
            'actor_id' => $actorId, 'notes' => $notes ? mb_substr($notes, 0, 500) : null, 'acted_at' => date('Y-m-d H:i:s')]);
    }

    public function history(string $docType, int $docId): array
    {
        return $this->db->select('wa.action, wa.from_state, wa.to_state, wa.notes, wa.acted_at, u.full_name AS actor_name')
            ->from('workflow_actions wa')->join('workflow_instances wi', 'wi.id = wa.instance_id')->join('users u', 'u.id = wa.actor_id', 'left')
            ->where(['wi.doc_type' => $docType, 'wi.doc_id' => $docId])->order_by('wa.id', 'ASC')->get()->result_array();
    }
}
