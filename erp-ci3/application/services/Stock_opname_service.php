<?php
/** Stock opname: snapshot system qty → count → approve → post variance as OPNAME_IN/OUT. */
class Stock_opname_service
{
    private $docs;
    private $stock;
    private $inventory;
    private $workflow;
    private $numbering;
    private $validator;
    private $warehouses;
    private $audit;
    private $ctx;
    private $db;

    public function __construct(CI_DB_query_builder $cidb, Stock_repository $stock, Inventory_service $inventory, Workflow_service $workflow, Numbering_service $numbering,
                                Stock_document_validator $validator, Warehouse_repository $warehouses, Audit_service $audit, Request_context $ctx, Db $db)
    {
        $this->docs = Stock_document_repository::opnames($cidb);
        $this->stock = $stock;
        $this->inventory = $inventory;
        $this->workflow = $workflow;
        $this->numbering = $numbering;
        $this->validator = $validator;
        $this->warehouses = $warehouses;
        $this->audit = $audit;
        $this->ctx = $ctx;
        $this->db = $db;
    }

    public function list(array $input): Paginator
    {
        return $this->docs->paginate($input, $this->ctx->company_id, 'opname_no', 'opname_date');
    }

    public function get(int $id): array
    {
        $doc = $this->docs->findOrFail($id);
        if ((int) $doc['company_id'] !== $this->ctx->company_id) {
            throw new Not_found_exception();
        }
        $doc['items'] = $this->docs->items($id);
        $doc['warehouse'] = $this->warehouses->find((int) $doc['warehouse_id']);
        $doc['history'] = $this->workflow->history('stock_opname', $id);
        $doc['actions'] = $this->workflow->availableActions('stock_opname', $doc['status']);
        return $doc;
    }

    public function create(array $d): int
    {
        $this->validator->validateOpname($d);
        $wh = $this->warehouses->findOrFail((int) $d['warehouse_id']);
        if ((int) $wh['company_id'] !== $this->ctx->company_id) {
            throw new Validation_exception(['warehouse_id' => 'Gudang tidak valid']);
        }
        return $this->db->transaction(function () use ($d, $wh) {
            $no = $this->numbering->next('STOCK_OPN', null, (int) $wh['branch_id'], $d['opname_date']);
            $id = $this->docs->insert(['company_id' => $this->ctx->company_id, 'branch_id' => (int) $wh['branch_id'], 'warehouse_id' => (int) $wh['id'], 'opname_no' => $no, 'opname_date' => $d['opname_date'],
                'opname_type' => $d['opname_type'] ?? 'FULL', 'notes' => $d['notes'] ?: null, 'created_by' => $this->ctx->user_id]);
            $this->workflow->start('stock_opname', $id, $no);
            $this->audit->log('inventory', 'create', 'stock_opnames', $id, null, ['opname_no' => $no], $no);
            return $id;
        });
    }

    /** Save counted quantities: [$itemId => qty] */
    public function saveCounts(int $id, array $counts): void
    {
        $doc = $this->get($id);
        if ($doc['status'] !== 'COUNTING') {
            throw new Invalid_transition_exception('Penghitungan hanya dapat diisi pada status COUNTING');
        }
        $this->db->transaction(function () use ($doc, $counts) {
            foreach ($doc['items'] as $it) {
                if (!array_key_exists($it['id'], $counts) || $counts[$it['id']] === '') {
                    continue;
                }
                $c = (float) $counts[$it['id']];
                if ($c < 0) {
                    throw new Validation_exception(['counts' => 'Qty hitung tidak boleh negatif']);
                }
                $this->docs->updateItem((int) $it['id'], ['qty_counted' => $c, 'qty_variance' => $c - (float) $it['qty_system'], 'counted_by' => $this->ctx->user_id, 'counted_at' => date('Y-m-d H:i:s')]);
            }
            $this->audit->log('inventory', 'count', 'stock_opnames', $doc['id'], null, ['counted_items' => count($counts)], $doc['opname_no']);
        });
    }

    public function action(int $id, string $action, ?string $notes = null): string
    {
        $permMap = ['start' => 'inventory.opname.edit', 'submit' => 'inventory.opname.edit', 'approve' => 'inventory.opname.approve', 'reject' => 'inventory.opname.approve', 'cancel' => 'inventory.opname.cancel', 'post' => 'inventory.opname.post'];
        if (!isset($permMap[$action])) {
            throw new Invalid_transition_exception('Aksi tidak dikenal');
        }
        return $this->db->transaction(function () use ($id, $action, $notes, $permMap) {
            $doc = $this->docs->findOrFail($id, true);
            if ((int) $doc['company_id'] !== $this->ctx->company_id) {
                throw new Not_found_exception();
            }
            $next = $this->workflow->transition('stock_opname', $id, $doc['opname_no'], $doc['status'], $action, $permMap[$action], $notes);
            $update = ['status' => $next];
            $now = date('Y-m-d H:i:s');
            if ($action === 'start') {
                $this->snapshot($doc);
            } elseif ($action === 'submit') {
                $items = $this->docs->items($id);
                if (array_filter($items, fn($i) => $i['qty_counted'] === null)) {
                    throw new Domain_exception('Semua baris harus dihitung sebelum diajukan');
                }
            } elseif ($action === 'approve') {
                $update += ['approved_by' => $this->ctx->user_id, 'approved_at' => $now];
            } elseif ($action === 'post') {
                $update += ['movement_id' => $this->postVariance($doc), 'posted_by' => $this->ctx->user_id, 'posted_at' => $now];
            }
            $this->docs->updateVersioned($id, (int) $doc['version'], $update);
            $this->audit->log('inventory', $action, 'stock_opnames', $id, ['status' => $doc['status']], ['status' => $next], $doc['opname_no'], $notes);
            return $next;
        });
    }

    private function snapshot(array $doc): void
    {
        $rows = [];
        foreach ($this->stock->balancesForWarehouse((int) $doc['warehouse_id']) as $b) {
            $rows[] = ['balance_id' => $b['id'], 'product_id' => $b['product_id'], 'batch_id' => $b['batch_id'], 'location_id' => $b['location_id'], 'condition_code' => $b['condition_code'], 'qty_system' => $b['qty_on_hand'], 'unit_cost' => $b['avg_cost']];
        }
        if (!$rows) {
            throw new Domain_exception('Gudang tidak memiliki saldo stok untuk dihitung');
        }
        $this->docs->replaceItems((int) $doc['id'], $rows);
    }

    private function postVariance(array $doc): ?int
    {
        $items = array_filter($this->docs->items((int) $doc['id']), fn($i) => abs((float) $i['qty_variance']) > 0.000001);
        if (!$items) {
            return null;
        }
        // Re-snapshot guard: system qty must not have moved since count (otherwise variance is stale)
        foreach ($items as $i) {
            $bal = $i['balance_id'] ? $this->stock->find((int) $i['balance_id']) : null;
            if ($bal && abs((float) $bal['qty_on_hand'] - (float) $i['qty_system']) > 0.000001) {
                throw new Conflict_exception('Stok sistem berubah sejak penghitungan untuk ' . $i['product_name'] . '. Lakukan opname ulang.');
            }
        }
        $lines = array_map(fn($i) => ['warehouse_id' => $doc['warehouse_id'], 'location_id' => $i['location_id'], 'product_id' => $i['product_id'], 'batch_id' => $i['batch_id'], 'batch_no' => $i['batch_no'],
            'condition_code' => $i['condition_code'], 'qty' => (float) $i['qty_variance'], 'unit_cost' => $i['unit_cost'], 'allow_expired' => true], array_values($items));
        $type = array_sum(array_column($lines, 'qty')) >= 0 ? 'OPNAME_IN' : 'OPNAME_OUT';
        return $this->inventory->post(['movement_type' => $type, 'ref_type' => 'stock_opnames', 'ref_id' => $doc['id'], 'ref_no' => $doc['opname_no'], 'notes' => $doc['notes'], 'branch_id' => $doc['branch_id']], $lines);
    }
}
