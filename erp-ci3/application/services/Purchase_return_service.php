<?php
/** Purchase Return ke supplier: DRAFT → SUBMITTED → APPROVED → POSTED (ISSUE keluar via Inventory_service, reason RETURN). */
class Purchase_return_service
{
    private $docs;
    private $inventory;
    private $suppliers;
    private $workflow;
    private $numbering;
    private $validator;
    private $warehouses;
    private $products;
    private $master;
    private $audit;
    private $ctx;
    private $db;

    public function __construct(CI_DB_query_builder $cidb, Inventory_service $inventory, Supplier_repository $suppliers, Workflow_service $workflow, Numbering_service $numbering,
                                Purchase_document_validator $validator, Warehouse_repository $warehouses, Product_repository $products, Master_repository $master, Audit_service $audit, Request_context $ctx, Db $db)
    {
        $this->docs = Purchase_document_repository::returns($cidb);
        $this->inventory = $inventory;
        $this->suppliers = $suppliers;
        $this->workflow = $workflow;
        $this->numbering = $numbering;
        $this->validator = $validator;
        $this->warehouses = $warehouses;
        $this->products = $products;
        $this->master = $master;
        $this->audit = $audit;
        $this->ctx = $ctx;
        $this->db = $db;
    }

    public function list(array $input): Paginator
    {
        return $this->docs->paginate($input, $this->ctx->company_id, 'return_no', 'return_date');
    }

    public function get(int $id): array
    {
        $doc = $this->docs->findOrFail($id);
        if ((int) $doc['company_id'] !== $this->ctx->company_id) {
            throw new Not_found_exception();
        }
        $doc['items'] = $this->docs->items($id);
        $doc['supplier'] = $this->suppliers->find((int) $doc['supplier_id']);
        $doc['warehouse'] = $this->warehouses->find((int) $doc['warehouse_id']);
        $doc['history'] = $this->workflow->history('purchase_return', $id);
        $doc['actions'] = $this->workflow->availableActions('purchase_return', $doc['status']);
        return $doc;
    }

    public function create(array $d): int
    {
        $this->validator->validateReturn($d);
        $this->checkReason($d);
        $supplier = $this->supplierForCompany((int) $d['supplier_id']);
        $wh = $this->warehouseForCompany((int) $d['warehouse_id']);
        return $this->db->transaction(function () use ($d, $supplier, $wh) {
            $no = $this->numbering->next('PURCHASE_RETURN', $wh['branch_code'], (int) $wh['branch_id'], $d['return_date']);
            $items = $this->mapItems($d['items']);
            $total = array_sum(array_map(fn($i) => $i['qty'] * $i['unit_cost'], $items));
            $id = $this->docs->insert(['company_id' => $this->ctx->company_id, 'branch_id' => (int) $wh['branch_id'], 'supplier_id' => (int) $supplier['id'],
                'warehouse_id' => (int) $wh['id'], 'gr_id' => !empty($d['gr_id']) ? (int) $d['gr_id'] : null, 'return_no' => $no, 'return_date' => $d['return_date'],
                'reason_code_id' => (int) $d['reason_code_id'], 'notes' => $d['notes'] ?: null, 'total_value' => round($total, 2), 'created_by' => $this->ctx->user_id]);
            $this->docs->replaceItems($id, $items);
            $this->workflow->start('purchase_return', $id, $no);
            $this->audit->log('purchasing', 'create', 'purchase_returns', $id, null, ['return_no' => $no, 'items' => count($items)], $no);
            return $id;
        });
    }

    public function update(int $id, array $d): void
    {
        $doc = $this->get($id);
        if ($doc['status'] !== 'DRAFT') {
            throw new Invalid_transition_exception('Hanya retur DRAFT yang dapat diubah');
        }
        $this->validator->validateReturn($d);
        $this->checkReason($d);
        $wh = $this->warehouseForCompany((int) $d['warehouse_id']);
        $this->db->transaction(function () use ($id, $d, $doc, $wh) {
            $items = $this->mapItems($d['items']);
            $total = array_sum(array_map(fn($i) => $i['qty'] * $i['unit_cost'], $items));
            $this->docs->updateVersioned($id, (int) $doc['version'], ['supplier_id' => (int) $this->supplierForCompany((int) $d['supplier_id'])['id'], 'warehouse_id' => (int) $wh['id'],
                'gr_id' => !empty($d['gr_id']) ? (int) $d['gr_id'] : null, 'return_date' => $d['return_date'], 'reason_code_id' => (int) $d['reason_code_id'],
                'notes' => $d['notes'] ?: null, 'total_value' => round($total, 2), 'updated_by' => $this->ctx->user_id]);
            $this->docs->replaceItems($id, $items);
            $this->audit->log('purchasing', 'update', 'purchase_returns', $id, null, ['items' => count($items)], $doc['return_no']);
        });
    }

    public function action(int $id, string $action, ?string $notes = null): string
    {
        $permMap = ['submit' => 'purchasing.return.edit', 'approve' => 'purchasing.return.approve', 'reject' => 'purchasing.return.approve', 'cancel' => 'purchasing.return.cancel', 'post' => 'purchasing.return.post'];
        if (!isset($permMap[$action])) {
            throw new Invalid_transition_exception('Aksi tidak dikenal');
        }
        return $this->db->transaction(function () use ($id, $action, $notes, $permMap) {
            $doc = $this->docs->findOrFail($id, true);
            if ((int) $doc['company_id'] !== $this->ctx->company_id) {
                throw new Not_found_exception();
            }
            if ($action === 'reject' && trim((string) $notes) === '') {
                throw new Validation_exception(['notes' => 'Alasan penolakan wajib diisi']);
            }
            $next = $this->workflow->transition('purchase_return', $id, $doc['return_no'], $doc['status'], $action, $permMap[$action], $notes);
            $now = date('Y-m-d H:i:s');
            $update = ['status' => $next, 'updated_by' => $this->ctx->user_id];
            if ($action === 'submit') {
                $update += ['submitted_by' => $this->ctx->user_id, 'submitted_at' => $now];
            } elseif ($action === 'approve') {
                $update += ['approved_by' => $this->ctx->user_id, 'approved_at' => $now];
            } elseif ($action === 'reject') {
                $update['rejection_reason'] = $notes;
            } elseif ($action === 'post') {
                $update += ['movement_id' => $this->post($doc), 'posted_by' => $this->ctx->user_id, 'posted_at' => $now];
            }
            $this->docs->updateVersioned($id, (int) $doc['version'], $update);
            $this->audit->log('purchasing', $action, 'purchase_returns', $id, ['status' => $doc['status']], ['status' => $next], $doc['return_no'], $notes);
            return $next;
        });
    }

    /** ISSUE keluar dari stok (negatif). Batch wajib untuk produk batch-tracked; negative-stock dicegah Inventory_service. */
    private function post(array $doc): int
    {
        $lines = [];
        foreach ($this->docs->items((int) $doc['id']) as $it) {
            $product = $this->products->findOrFail((int) $it['product_id']);
            if ($product['is_batch_tracked'] && empty($it['batch_id'])) {
                throw new Validation_exception(['batch_id' => "Produk {$product['sku']} wajib memilih batch untuk retur"]);
            }
            $lines[] = ['warehouse_id' => (int) $doc['warehouse_id'], 'location_id' => !empty($it['location_id']) ? (int) $it['location_id'] : null, 'product_id' => (int) $it['product_id'],
                'batch_id' => !empty($it['batch_id']) ? (int) $it['batch_id'] : null, 'condition_code' => $it['condition_code'] ?? 'GOOD', 'qty' => -(float) $it['qty'], 'unit_cost' => (float) $it['unit_cost'] ?: null, 'allow_expired' => true];
        }
        return $this->inventory->post(['movement_type' => 'ISSUE', 'ref_type' => 'purchase_returns', 'ref_id' => (int) $doc['id'], 'ref_no' => $doc['return_no'],
            'reason_code_id' => (int) $doc['reason_code_id'], 'notes' => $doc['notes'], 'branch_id' => (int) $doc['branch_id']], $lines);
    }

    private function checkReason(array $d): void
    {
        $reason = $this->master->findReason((int) $d['reason_code_id']);
        if (!$reason || (int) $reason['company_id'] !== $this->ctx->company_id || $reason['reason_type'] !== 'RETURN' || !$reason['is_active']) {
            throw new Validation_exception(['reason_code_id' => 'Alasan retur tidak valid (harus tipe RETURN)']);
        }
        if ($reason['requires_note'] && trim((string) ($d['notes'] ?? '')) === '') {
            throw new Validation_exception(['notes' => 'Alasan ini mewajibkan catatan']);
        }
    }

    private function mapItems(array $items): array
    {
        $rows = [];
        $n = 0;
        foreach ($this->validator->cleanReturnItems($items) as $it) {
            $rows[] = ['line_no' => ++$n, 'product_id' => (int) $it['product_id'], 'batch_id' => !empty($it['batch_id']) ? (int) $it['batch_id'] : null,
                'location_id' => !empty($it['location_id']) ? (int) $it['location_id'] : null, 'condition_code' => $it['condition_code'] ?? 'GOOD',
                'qty' => (float) $it['qty'], 'unit_cost' => (float) ($it['unit_cost'] ?? 0), 'notes' => $it['notes'] ?? null];
        }
        return $rows;
    }

    private function supplierForCompany(int $id): array
    {
        $s = $this->suppliers->find($id);
        if (!$s || (int) $s['company_id'] !== $this->ctx->company_id) {
            throw new Validation_exception(['supplier_id' => 'Supplier tidak valid']);
        }
        return $s;
    }

    private function warehouseForCompany(int $id): array
    {
        $wh = $this->db->ci->select('w.id, w.branch_id, b.code AS branch_code')->from('warehouses w')->join('branches b', 'b.id = w.branch_id')
            ->where(['w.id' => $id, 'w.company_id' => $this->ctx->company_id, 'w.is_active' => 1])->get()->row_array();
        if (!$wh) {
            throw new Validation_exception(['warehouse_id' => 'Gudang tidak valid']);
        }
        return $wh;
    }
}
