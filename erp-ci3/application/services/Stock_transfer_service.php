<?php
/** Stock transfer: DRAFT → SUBMITTED → APPROVED → IN_TRANSIT (TRANSFER_OUT, FEFO auto-batch) → RECEIVED (TRANSFER_IN). */
class Stock_transfer_service
{
    private $docs;
    private $inventory;
    private $workflow;
    private $numbering;
    private $validator;
    private $warehouses;
    private $products;
    private $audit;
    private $ctx;
    private $db;

    public function __construct(CI_DB_query_builder $cidb, Inventory_service $inventory, Workflow_service $workflow, Numbering_service $numbering, Stock_document_validator $validator,
                                Warehouse_repository $warehouses, Product_repository $products, Audit_service $audit, Request_context $ctx, Db $db)
    {
        $this->docs = Stock_document_repository::transfers($cidb);
        $this->inventory = $inventory;
        $this->workflow = $workflow;
        $this->numbering = $numbering;
        $this->validator = $validator;
        $this->warehouses = $warehouses;
        $this->products = $products;
        $this->audit = $audit;
        $this->ctx = $ctx;
        $this->db = $db;
    }

    public function list(array $input): Paginator
    {
        return $this->docs->paginate($input, $this->ctx->company_id, 'transfer_no', 'transfer_date');
    }

    public function get(int $id): array
    {
        $doc = $this->docs->findOrFail($id);
        if ((int) $doc['company_id'] !== $this->ctx->company_id) {
            throw new Not_found_exception();
        }
        $doc['items'] = $this->docs->items($id);
        $doc['from_warehouse'] = $this->warehouses->find((int) $doc['from_warehouse_id']);
        $doc['to_warehouse'] = $this->warehouses->find((int) $doc['to_warehouse_id']);
        $doc['history'] = $this->workflow->history('stock_transfer', $id);
        $doc['actions'] = $this->workflow->availableActions('stock_transfer', $doc['status']);
        return $doc;
    }

    public function create(array $d): int
    {
        $this->validator->validateTransfer($d);
        $from = $this->warehouses->findOrFail((int) $d['from_warehouse_id']);
        $to = $this->warehouses->findOrFail((int) $d['to_warehouse_id']);
        if ((int) $from['company_id'] !== $this->ctx->company_id || (int) $to['company_id'] !== $this->ctx->company_id) {
            throw new Validation_exception(['from_warehouse_id' => 'Gudang tidak valid']);
        }
        return $this->db->transaction(function () use ($d, $from, $to) {
            $no = $this->numbering->next('STOCK_TRF', null, (int) $from['branch_id'], $d['transfer_date']);
            $id = $this->docs->insert(['company_id' => $this->ctx->company_id, 'transfer_no' => $no, 'transfer_date' => $d['transfer_date'], 'from_warehouse_id' => (int) $from['id'], 'to_warehouse_id' => (int) $to['id'],
                'from_branch_id' => (int) $from['branch_id'], 'to_branch_id' => (int) $to['branch_id'], 'notes' => $d['notes'] ?: null, 'created_by' => $this->ctx->user_id]);
            $rows = [];
            $n = 0;
            foreach ($this->validator->cleanItems($d['items']) as $it) {
                $rows[] = ['line_no' => ++$n, 'product_id' => (int) $it['product_id'], 'batch_id' => !empty($it['batch_id']) ? (int) $it['batch_id'] : null, 'qty_requested' => (float) $it['qty_requested'], 'notes' => $it['notes'] ?? null];
            }
            $this->docs->replaceItems($id, $rows);
            $this->workflow->start('stock_transfer', $id, $no);
            $this->audit->log('inventory', 'create', 'stock_transfers', $id, null, ['transfer_no' => $no, 'items' => $n], $no);
            return $id;
        });
    }

    public function action(int $id, string $action, ?string $notes = null, array $received = []): string
    {
        $permMap = ['submit' => 'inventory.transfer.edit', 'approve' => 'inventory.transfer.approve', 'reject' => 'inventory.transfer.approve', 'cancel' => 'inventory.transfer.cancel', 'ship' => 'inventory.transfer.post', 'receive' => 'inventory.transfer.post'];
        if (!isset($permMap[$action])) {
            throw new Invalid_transition_exception('Aksi tidak dikenal');
        }
        return $this->db->transaction(function () use ($id, $action, $notes, $received, $permMap) {
            $doc = $this->docs->findOrFail($id, true);
            if ((int) $doc['company_id'] !== $this->ctx->company_id) {
                throw new Not_found_exception();
            }
            if ($action === 'reject' && trim((string) $notes) === '') {
                throw new Validation_exception(['notes' => 'Alasan penolakan wajib diisi']);
            }
            $next = $this->workflow->transition('stock_transfer', $id, $doc['transfer_no'], $doc['status'], $action, $permMap[$action], $notes);
            $now = date('Y-m-d H:i:s');
            $update = ['status' => $next];
            if ($action === 'submit') {
                $update += ['submitted_by' => $this->ctx->user_id, 'submitted_at' => $now];
            } elseif ($action === 'approve') {
                $update += ['approved_by' => $this->ctx->user_id, 'approved_at' => $now];
            } elseif ($action === 'reject') {
                $update['rejection_reason'] = $notes;
            } elseif ($action === 'ship') {
                $update += ['out_movement_id' => $this->ship($doc), 'shipped_by' => $this->ctx->user_id, 'shipped_at' => $now];
            } elseif ($action === 'receive') {
                $update += ['in_movement_id' => $this->receive($doc, $received), 'received_by' => $this->ctx->user_id, 'received_at' => $now];
            }
            $this->docs->updateVersioned($id, (int) $doc['version'], $update);
            $this->audit->log('inventory', $action, 'stock_transfers', $id, ['status' => $doc['status']], ['status' => $next], $doc['transfer_no'], $notes);
            return $next;
        });
    }

    /** TRANSFER_OUT: explicit batch or FEFO allocation per line; qty_shipped recorded per resulting batch line. */
    private function ship(array $doc): int
    {
        $lines = [];
        foreach ($this->docs->items((int) $doc['id']) as $it) {
            $product = $this->products->findOrFail((int) $it['product_id']);
            $allocs = [];
            if ($it['batch_id'] || !$product['is_batch_tracked']) {
                $allocs[] = ['batch_id' => $it['batch_id'], 'qty' => (float) $it['qty_requested'], 'location_id' => $it['from_location_id'], 'unit_cost' => null];
            } else {
                $r = $this->inventory->allocateFefo((int) $it['product_id'], (int) $doc['from_warehouse_id'], (float) $it['qty_requested']);
                if ($r['shortage'] > 0) {
                    throw new Insufficient_stock_exception(sprintf('Stok %s tidak cukup di gudang asal (kurang %s)', $product['name'], $r['shortage']));
                }
                $allocs = $r['allocations'];
            }
            $first = true;
            foreach ($allocs as $a) {
                $lines[] = ['warehouse_id' => $doc['from_warehouse_id'], 'location_id' => $a['location_id'], 'product_id' => $it['product_id'], 'batch_id' => $a['batch_id'], 'batch_no' => $a['batch_no'] ?? $it['batch_no'], 'qty' => -$a['qty'], 'unit_cost' => $a['unit_cost']];
                if ($first) {
                    $this->docs->updateItem((int) $it['id'], ['batch_id' => $a['batch_id'], 'qty_shipped' => (float) $it['qty_requested'], 'from_location_id' => $a['location_id'], 'unit_cost' => $a['unit_cost'] ?? 0]);
                    $first = false;
                }
            }
        }
        return $this->inventory->post(['movement_type' => 'TRANSFER_OUT', 'ref_type' => 'stock_transfers', 'ref_id' => $doc['id'], 'ref_no' => $doc['transfer_no'], 'notes' => $doc['notes'], 'branch_id' => $doc['from_branch_id']], $lines);
    }

    /** TRANSFER_IN at destination: exact shipped quantities of the OUT movement (batch identity preserved). Discrepancy → qty_received noted, difference stays as deviation for Phase 7. */
    private function receive(array $doc, array $received): int
    {
        $outItems = $this->inventory_items((int) $doc['out_movement_id']);
        $lines = [];
        foreach ($outItems as $i) {
            $lines[] = ['warehouse_id' => $doc['to_warehouse_id'], 'location_id' => null, 'product_id' => $i['product_id'], 'batch_id' => $i['batch_id'], 'batch_no' => $i['batch_no'], 'condition_code' => $i['condition_code'], 'qty' => -(float) $i['qty'], 'unit_cost' => $i['unit_cost'], 'allow_expired' => true];
        }
        foreach ($this->docs->items((int) $doc['id']) as $it) {
            $qty = isset($received[$it['id']]) && $received[$it['id']] !== '' ? (float) $received[$it['id']] : (float) $it['qty_shipped'];
            if ($qty < 0 || $qty > (float) $it['qty_shipped']) {
                throw new Validation_exception(['received' => 'Qty diterima harus antara 0 dan qty dikirim']);
            }
            $this->docs->updateItem((int) $it['id'], ['qty_received' => $qty]);
        }
        return $this->inventory->post(['movement_type' => 'TRANSFER_IN', 'ref_type' => 'stock_transfers', 'ref_id' => $doc['id'], 'ref_no' => $doc['transfer_no'], 'notes' => $doc['notes'], 'branch_id' => $doc['to_branch_id']], $lines);
    }

    private function inventory_items(int $movementId): array
    {
        return $this->db->ci->select('i.product_id, i.batch_id, i.condition_code, i.qty, i.unit_cost, b.batch_no')->from('stock_movement_items i')->join('batches b', 'b.id = i.batch_id', 'left')->where('i.movement_id', $movementId)->get()->result_array();
    }
}
