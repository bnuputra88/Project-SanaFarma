<?php
/** Retur penjualan (barang kembali dari pelanggan): DRAFT→SUBMITTED→APPROVED→POSTED (RECEIPT masuk stok, reason RETURN). */
class Sales_return_service
{
    private $repo;
    private $inventory;
    private $sales;
    private $workflow;
    private $numbering;
    private $validator;
    private $products;
    private $master;
    private $audit;
    private $ctx;
    private $db;

    public function __construct(Sales_return_repository $repo, Inventory_service $inventory, Sales_repository $sales, Workflow_service $workflow, Numbering_service $numbering,
                                Sales_document_validator $validator, Product_repository $products, Master_repository $master, Audit_service $audit, Request_context $ctx, Db $db)
    {
        $this->repo = $repo;
        $this->inventory = $inventory;
        $this->sales = $sales;
        $this->workflow = $workflow;
        $this->numbering = $numbering;
        $this->validator = $validator;
        $this->products = $products;
        $this->master = $master;
        $this->audit = $audit;
        $this->ctx = $ctx;
        $this->db = $db;
    }

    public function list(array $input): Paginator
    {
        return $this->repo->paginate($input, $this->ctx->company_id);
    }

    public function get(int $id): array
    {
        $doc = $this->repo->findOrFail($id);
        if ((int) $doc['company_id'] !== $this->ctx->company_id) {
            throw new Not_found_exception();
        }
        $doc['items'] = $this->repo->items($id);
        $doc['history'] = $this->workflow->history('sales_return', $id);
        $doc['actions'] = $this->workflow->availableActions('sales_return', $doc['status']);
        return $doc;
    }

    public function create(array $d): int
    {
        $this->validator->validateReturn($d);
        $this->checkReason($d);
        $wh = $this->warehouseForCompany((int) $d['warehouse_id']);
        return $this->db->transaction(function () use ($d, $wh) {
            $no = $this->numbering->next('SALES_RETURN', $wh['branch_code'], (int) $wh['branch_id'], $d['return_date']);
            $items = $this->mapItems($d['items']);
            $total = array_sum(array_map(fn($i) => $i['qty'] * $i['unit_price'], $items));
            $id = $this->repo->insert(['company_id' => $this->ctx->company_id, 'branch_id' => (int) $wh['branch_id'], 'warehouse_id' => (int) $wh['id'],
                'sale_id' => !empty($d['sale_id']) ? (int) $d['sale_id'] : null, 'customer_id' => !empty($d['customer_id']) ? (int) $d['customer_id'] : null,
                'return_no' => $no, 'return_date' => $d['return_date'], 'reason_code_id' => (int) $d['reason_code_id'], 'notes' => $d['notes'] ?: null, 'total_value' => round($total, 2), 'created_by' => $this->ctx->user_id]);
            $this->repo->replaceItems($id, $items);
            $this->workflow->start('sales_return', $id, $no);
            $this->audit->log('sales', 'create', 'sales_returns', $id, null, ['return_no' => $no, 'items' => count($items)], $no);
            return $id;
        });
    }

    public function action(int $id, string $action, ?string $notes = null): string
    {
        $permMap = ['submit' => 'sales.return.edit', 'approve' => 'sales.return.approve', 'reject' => 'sales.return.approve', 'cancel' => 'sales.return.cancel', 'post' => 'sales.return.post'];
        if (!isset($permMap[$action])) {
            throw new Invalid_transition_exception('Aksi tidak dikenal');
        }
        return $this->db->transaction(function () use ($id, $action, $notes, $permMap) {
            $doc = $this->repo->findOrFail($id, true);
            if ((int) $doc['company_id'] !== $this->ctx->company_id) {
                throw new Not_found_exception();
            }
            if ($action === 'reject' && trim((string) $notes) === '') {
                throw new Validation_exception(['notes' => 'Alasan penolakan wajib diisi']);
            }
            $next = $this->workflow->transition('sales_return', $id, $doc['return_no'], $doc['status'], $action, $permMap[$action], $notes);
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
            $this->repo->updateVersioned($id, (int) $doc['version'], $update);
            $this->audit->log('sales', $action, 'sales_returns', $id, ['status' => $doc['status']], ['status' => $next], $doc['return_no'], $notes);
            return $next;
        });
    }

    private function post(array $doc): int
    {
        $lines = [];
        foreach ($this->repo->items((int) $doc['id']) as $it) {
            $lines[] = ['warehouse_id' => (int) $doc['warehouse_id'], 'product_id' => (int) $it['product_id'], 'batch_id' => !empty($it['batch_id']) ? (int) $it['batch_id'] : null,
                'condition_code' => $it['condition_code'] ?? 'GOOD', 'qty' => (float) $it['qty'], 'unit_cost' => (float) $it['unit_price'] ?: null, 'allow_expired' => true];
        }
        return $this->inventory->post(['movement_type' => 'RECEIPT', 'ref_type' => 'sales_returns', 'ref_id' => (int) $doc['id'], 'ref_no' => $doc['return_no'],
            'reason_code_id' => (int) $doc['reason_code_id'], 'notes' => $doc['notes'], 'branch_id' => (int) $doc['branch_id']], $lines);
    }

    private function checkReason(array $d): void
    {
        $reason = $this->master->findReason((int) $d['reason_code_id']);
        if (!$reason || (int) $reason['company_id'] !== $this->ctx->company_id || $reason['reason_type'] !== 'RETURN' || !$reason['is_active']) {
            throw new Validation_exception(['reason_code_id' => 'Alasan retur tidak valid (harus tipe RETURN)']);
        }
    }

    private function mapItems(array $items): array
    {
        $rows = [];
        $n = 0;
        foreach ($this->validator->cleanItems($items) as $it) {
            $rows[] = ['line_no' => ++$n, 'sale_item_id' => !empty($it['sale_item_id']) ? (int) $it['sale_item_id'] : null, 'product_id' => (int) $it['product_id'],
                'batch_id' => !empty($it['batch_id']) ? (int) $it['batch_id'] : null, 'condition_code' => $it['condition_code'] ?? 'GOOD', 'qty' => (float) $it['qty'], 'unit_price' => (float) ($it['unit_price'] ?? 0), 'notes' => $it['notes'] ?? null];
        }
        return $rows;
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
