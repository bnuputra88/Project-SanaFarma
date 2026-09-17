<?php
/** Purchase Request: DRAFT → SUBMITTED → APPROVED → CLOSED (saat dikonversi ke PO). Tidak ada delete, hanya cancel/reject. */
class Purchase_request_service
{
    private $docs;
    private $workflow;
    private $numbering;
    private $validator;
    private $warehouses;
    private $products;
    private $audit;
    private $ctx;
    private $db;

    public function __construct(CI_DB_query_builder $cidb, Workflow_service $workflow, Numbering_service $numbering, Purchase_document_validator $validator,
                                Warehouse_repository $warehouses, Product_repository $products, Audit_service $audit, Request_context $ctx, Db $db)
    {
        $this->docs = Purchase_document_repository::requests($cidb);
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
        return $this->docs->paginate($input, $this->ctx->company_id, 'pr_no', 'request_date');
    }

    public function get(int $id): array
    {
        $doc = $this->docs->findOrFail($id);
        if ((int) $doc['company_id'] !== $this->ctx->company_id) {
            throw new Not_found_exception();
        }
        $doc['items'] = $this->docs->items($id);
        $doc['history'] = $this->workflow->history('purchase_request', $id);
        $doc['actions'] = $this->workflow->availableActions('purchase_request', $doc['status']);
        return $doc;
    }

    public function create(array $d): int
    {
        $this->validator->validateRequest($d);
        $branch = $this->resolveBranch($d);
        return $this->db->transaction(function () use ($d, $branch) {
            $no = $this->numbering->next('PURCHASE_REQ', $branch['code'], (int) $branch['id'], $d['request_date']);
            $items = $this->mapItems($d['items']);
            $total = array_sum(array_map(fn($i) => $i['qty'] * $i['estimated_price'], $items));
            $id = $this->docs->insert(['company_id' => $this->ctx->company_id, 'branch_id' => (int) $branch['id'], 'warehouse_id' => !empty($d['warehouse_id']) ? (int) $d['warehouse_id'] : null,
                'pr_no' => $no, 'request_date' => $d['request_date'], 'required_date' => $d['required_date'] ?: null, 'notes' => $d['notes'] ?: null,
                'total_estimated' => round($total, 2), 'created_by' => $this->ctx->user_id]);
            $this->docs->replaceItems($id, $items);
            $this->workflow->start('purchase_request', $id, $no);
            $this->audit->log('purchasing', 'create', 'purchase_requests', $id, null, ['pr_no' => $no, 'items' => count($items)], $no);
            return $id;
        });
    }

    public function update(int $id, array $d): void
    {
        $doc = $this->get($id);
        if ($doc['status'] !== 'DRAFT') {
            throw new Invalid_transition_exception('Hanya PR DRAFT yang dapat diubah');
        }
        $this->validator->validateRequest($d);
        $this->db->transaction(function () use ($id, $d, $doc) {
            $items = $this->mapItems($d['items']);
            $total = array_sum(array_map(fn($i) => $i['qty'] * $i['estimated_price'], $items));
            $this->docs->updateVersioned($id, (int) $doc['version'], ['request_date' => $d['request_date'], 'required_date' => $d['required_date'] ?: null,
                'warehouse_id' => !empty($d['warehouse_id']) ? (int) $d['warehouse_id'] : null, 'notes' => $d['notes'] ?: null, 'total_estimated' => round($total, 2), 'updated_by' => $this->ctx->user_id]);
            $this->docs->replaceItems($id, $items);
            $this->audit->log('purchasing', 'update', 'purchase_requests', $id, null, ['items' => count($items)], $doc['pr_no']);
        });
    }

    public function action(int $id, string $action, ?string $notes = null): string
    {
        $permMap = ['submit' => 'purchasing.pr.edit', 'approve' => 'purchasing.pr.approve', 'reject' => 'purchasing.pr.approve', 'cancel' => 'purchasing.pr.cancel', 'close' => 'purchasing.pr.approve'];
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
            $next = $this->workflow->transition('purchase_request', $id, $doc['pr_no'], $doc['status'], $action, $permMap[$action], $notes);
            $now = date('Y-m-d H:i:s');
            $update = ['status' => $next, 'updated_by' => $this->ctx->user_id];
            if ($action === 'submit') {
                $update += ['submitted_by' => $this->ctx->user_id, 'submitted_at' => $now];
            } elseif ($action === 'approve') {
                $update += ['approved_by' => $this->ctx->user_id, 'approved_at' => $now];
            } elseif ($action === 'reject') {
                $update['rejection_reason'] = $notes;
            } elseif ($action === 'close') {
                $update['closed_at'] = $now;
            }
            $this->docs->updateVersioned($id, (int) $doc['version'], $update);
            $this->audit->log('purchasing', $action, 'purchase_requests', $id, ['status' => $doc['status']], ['status' => $next], $doc['pr_no'], $notes);
            return $next;
        });
    }

    private function mapItems(array $items): array
    {
        $rows = [];
        $n = 0;
        foreach ($this->validator->cleanItems($items) as $it) {
            $rows[] = ['line_no' => ++$n, 'product_id' => (int) $it['product_id'], 'uom_id' => !empty($it['uom_id']) ? (int) $it['uom_id'] : null,
                'qty' => (float) $it['qty'], 'estimated_price' => (float) ($it['estimated_price'] ?? 0), 'notes' => $it['notes'] ?? null];
        }
        return $rows;
    }

    private function resolveBranch(array $d): array
    {
        if (!empty($d['warehouse_id'])) {
            $wh = $this->db->ci->select('b.id, b.code')->from('warehouses w')->join('branches b', 'b.id = w.branch_id')
                ->where(['w.id' => (int) $d['warehouse_id'], 'w.company_id' => $this->ctx->company_id])->get()->row_array();
            if ($wh) {
                return $wh;
            }
        }
        $b = $this->db->ci->select('id, code')->from('branches')->where('company_id', $this->ctx->company_id)->order_by('id')->limit(1)->get()->row_array();
        if (!$b) {
            throw new Validation_exception(['warehouse_id' => 'Cabang tidak ditemukan']);
        }
        return $b;
    }
}
