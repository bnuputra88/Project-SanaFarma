<?php
/** Stock adjustment document: DRAFT → SUBMITTED → APPROVED → POSTED (movement). Segregation: creator ≠ approver (configurable). */
class Stock_adjustment_service
{
    private $docs;
    private $inventory;
    private $workflow;
    private $numbering;
    private $validator;
    private $master;
    private $settings;
    private $audit;
    private $ctx;
    private $db;

    public function __construct(CI_DB_query_builder $cidb, Inventory_service $inventory, Workflow_service $workflow, Numbering_service $numbering, Stock_document_validator $validator,
                                Master_repository $master, Setting_service $settings, Audit_service $audit, Request_context $ctx, Db $db)
    {
        $this->docs = Stock_document_repository::adjustments($cidb);
        $this->inventory = $inventory;
        $this->workflow = $workflow;
        $this->numbering = $numbering;
        $this->validator = $validator;
        $this->master = $master;
        $this->settings = $settings;
        $this->audit = $audit;
        $this->ctx = $ctx;
        $this->db = $db;
    }

    public function list(array $input): Paginator
    {
        return $this->docs->paginate($input, $this->ctx->company_id, 'adjustment_no', 'adjustment_date');
    }

    public function get(int $id): array
    {
        $doc = $this->docs->findOrFail($id);
        if ((int) $doc['company_id'] !== $this->ctx->company_id) {
            throw new Not_found_exception();
        }
        $doc['items'] = $this->docs->items($id);
        $doc['history'] = $this->workflow->history('stock_adjustment', $id);
        $doc['actions'] = $this->workflow->availableActions('stock_adjustment', $doc['status']);
        return $doc;
    }

    public function create(array $d): int
    {
        $this->validator->validateAdjustment($d);
        $this->checkReason($d);
        return $this->db->transaction(function () use ($d) {
            $branch = $this->branchOfWarehouse((int) $d['warehouse_id']);
            $no = $this->numbering->next('STOCK_ADJ', $branch['code'], (int) $branch['id'], $d['adjustment_date']);
            $id = $this->docs->insert(['company_id' => $this->ctx->company_id, 'branch_id' => (int) $branch['id'], 'warehouse_id' => (int) $d['warehouse_id'], 'adjustment_no' => $no,
                'adjustment_date' => $d['adjustment_date'], 'reason_code_id' => (int) $d['reason_code_id'], 'notes' => $d['notes'] ?: null, 'created_by' => $this->ctx->user_id]);
            $this->docs->replaceItems($id, $this->mapItems($d['items']));
            $this->workflow->start('stock_adjustment', $id, $no);
            $this->audit->log('inventory', 'create', 'stock_adjustments', $id, null, ['adjustment_no' => $no, 'items' => count($d['items'])], $no);
            return $id;
        });
    }

    public function update(int $id, array $d): void
    {
        $doc = $this->get($id);
        if ($doc['status'] !== 'DRAFT') {
            throw new Invalid_transition_exception('Hanya dokumen DRAFT yang dapat diubah');
        }
        $this->validator->validateAdjustment($d);
        $this->checkReason($d);
        $this->db->transaction(function () use ($id, $d, $doc) {
            $this->docs->updateVersioned($id, (int) $doc['version'], ['adjustment_date' => $d['adjustment_date'], 'reason_code_id' => (int) $d['reason_code_id'], 'notes' => $d['notes'] ?: null, 'updated_by' => $this->ctx->user_id]);
            $this->docs->replaceItems($id, $this->mapItems($d['items']));
            $this->audit->log('inventory', 'update', 'stock_adjustments', $id, null, ['items' => count($d['items'])], $doc['adjustment_no']);
        });
    }

    public function action(int $id, string $action, ?string $notes = null): string
    {
        $permMap = ['submit' => 'inventory.adjustment.edit', 'approve' => 'inventory.adjustment.approve', 'reject' => 'inventory.adjustment.approve', 'cancel' => 'inventory.adjustment.cancel', 'post' => 'inventory.adjustment.post'];
        if (!isset($permMap[$action])) {
            throw new Invalid_transition_exception('Aksi tidak dikenal');
        }
        return $this->db->transaction(function () use ($id, $action, $notes, $permMap) {
            $doc = $this->docs->findOrFail($id, true);
            if ((int) $doc['company_id'] !== $this->ctx->company_id) {
                throw new Not_found_exception();
            }
            if ($action === 'approve' && (int) $doc['created_by'] === $this->ctx->user_id && $this->settings->get('workflow.enforce_segregation', true) && !$this->ctx->is_superadmin) {
                throw new Authorization_exception('Pembuat dokumen tidak boleh menyetujui dokumennya sendiri');
            }
            if ($action === 'reject' && trim((string) $notes) === '') {
                throw new Validation_exception(['notes' => 'Alasan penolakan wajib diisi']);
            }
            $next = $this->workflow->transition('stock_adjustment', $id, $doc['adjustment_no'], $doc['status'], $action, $permMap[$action], $notes);
            $update = ['status' => $next, 'updated_by' => $this->ctx->user_id];
            $now = date('Y-m-d H:i:s');
            if ($action === 'submit') {
                $update += ['submitted_by' => $this->ctx->user_id, 'submitted_at' => $now];
            } elseif ($action === 'approve') {
                $update += ['approved_by' => $this->ctx->user_id, 'approved_at' => $now];
            } elseif ($action === 'reject') {
                $update['rejection_reason'] = $notes;
            } elseif ($action === 'post') {
                $items = $this->docs->items($id);
                $lines = array_map(fn($i) => ['warehouse_id' => $doc['warehouse_id'], 'location_id' => $i['location_id'], 'product_id' => $i['product_id'], 'batch_id' => $i['batch_id'],
                    'batch_no' => $i['batch_no'], 'condition_code' => $i['condition_code'], 'qty' => (float) $i['qty_change'], 'unit_cost' => $i['unit_cost'] ?: null, 'allow_expired' => true], $items);
                $type = array_sum(array_column($items, 'qty_change')) >= 0 ? 'ADJUSTMENT_IN' : 'ADJUSTMENT_OUT';
                $mid = $this->inventory->post(['movement_type' => $type, 'ref_type' => 'stock_adjustments', 'ref_id' => $id, 'ref_no' => $doc['adjustment_no'], 'reason_code_id' => $doc['reason_code_id'], 'notes' => $doc['notes'], 'branch_id' => $doc['branch_id']], $lines);
                $update += ['movement_id' => $mid, 'posted_by' => $this->ctx->user_id, 'posted_at' => $now, 'total_value' => round(array_sum(array_map(fn($i) => $i['qty_change'] * $i['unit_cost'], $items)), 2)];
            }
            $this->docs->updateVersioned($id, (int) $doc['version'], $update);
            $this->audit->log('inventory', $action, 'stock_adjustments', $id, ['status' => $doc['status']], ['status' => $next], $doc['adjustment_no'], $notes);
            return $next;
        });
    }

    private function checkReason(array $d): void
    {
        $reason = $this->master->findReason((int) $d['reason_code_id']);
        if (!$reason || (int) $reason['company_id'] !== $this->ctx->company_id || $reason['reason_type'] !== 'ADJUSTMENT' || !$reason['is_active']) {
            throw new Validation_exception(['reason_code_id' => 'Alasan penyesuaian tidak valid']);
        }
        if ($reason['requires_note'] && trim((string) ($d['notes'] ?? '')) === '') {
            throw new Validation_exception(['notes' => 'Alasan ini mewajibkan catatan']);
        }
    }

    private function mapItems(array $items): array
    {
        $rows = [];
        $n = 0;
        foreach ($this->validator->cleanItems($items) as $it) {
            $rows[] = ['line_no' => ++$n, 'product_id' => (int) $it['product_id'], 'batch_id' => !empty($it['batch_id']) ? (int) $it['batch_id'] : null, 'location_id' => !empty($it['location_id']) ? (int) $it['location_id'] : null,
                'condition_code' => $it['condition_code'] ?? 'GOOD', 'qty_change' => (float) $it['qty_change'], 'unit_cost' => (float) ($it['unit_cost'] ?? 0), 'notes' => $it['notes'] ?? null];
        }
        return $rows;
    }

    private function branchOfWarehouse(int $warehouseId): array
    {
        $wh = $this->db->ci->select('w.id, b.id AS branch_id, b.code')->from('warehouses w')->join('branches b', 'b.id = w.branch_id')->where(['w.id' => $warehouseId, 'w.company_id' => $this->ctx->company_id, 'w.is_active' => 1])->get()->row_array();
        if (!$wh) {
            throw new Validation_exception(['warehouse_id' => 'Gudang tidak valid']);
        }
        return ['id' => $wh['branch_id'], 'code' => $wh['code']];
    }
}
