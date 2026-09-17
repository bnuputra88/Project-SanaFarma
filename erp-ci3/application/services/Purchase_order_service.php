<?php
/** Purchase Order: DRAFT → SUBMITTED → APPROVED → ORDERED → (PARTIAL/RECEIVED via GR) → CLOSED. Segregasi pembuat≠approver. */
class Purchase_order_service
{
    private $docs;
    private $requests;
    private $suppliers;
    private $workflow;
    private $numbering;
    private $validator;
    private $warehouses;
    private $products;
    private $settings;
    private $audit;
    private $ctx;
    private $db;

    public function __construct(CI_DB_query_builder $cidb, Supplier_repository $suppliers, Workflow_service $workflow, Numbering_service $numbering, Purchase_document_validator $validator,
                                Warehouse_repository $warehouses, Product_repository $products, Setting_service $settings, Audit_service $audit, Request_context $ctx, Db $db)
    {
        $this->docs = Purchase_document_repository::orders($cidb);
        $this->requests = Purchase_document_repository::requests($cidb);
        $this->suppliers = $suppliers;
        $this->workflow = $workflow;
        $this->numbering = $numbering;
        $this->validator = $validator;
        $this->warehouses = $warehouses;
        $this->products = $products;
        $this->settings = $settings;
        $this->audit = $audit;
        $this->ctx = $ctx;
        $this->db = $db;
    }

    /** Konversi PR (APPROVED) → PO draft. Harga awal diambil dari katalog supplier bila ada. */
    public function createFromRequest(int $prId, array $d): int
    {
        $pr = $this->requests->findOrFail($prId);
        if ((int) $pr['company_id'] !== $this->ctx->company_id) {
            throw new Not_found_exception();
        }
        if ($pr['status'] !== 'APPROVED') {
            throw new Invalid_transition_exception('Hanya PR berstatus APPROVED yang dapat dikonversi menjadi PO');
        }
        $supplierId = (int) ($d['supplier_id'] ?? 0);
        $catalog = $supplierId ? array_column($this->db->ci->select('product_id, last_price')->from('supplier_products')->where('supplier_id', $supplierId)->get()->result_array(), 'last_price', 'product_id') : [];
        $items = [];
        foreach ($this->requests->items($prId) as $it) {
            $items[] = ['product_id' => (int) $it['product_id'], 'uom_id' => $it['uom_id'] ?? null, 'qty_ordered' => (float) $it['qty'],
                'unit_price' => (float) ($catalog[(int) $it['product_id']] ?? $it['estimated_price'] ?? 0), 'discount_pct' => 0, 'tax_pct' => 0, 'notes' => $it['notes'] ?? null];
        }
        $payload = ['supplier_id' => $supplierId, 'warehouse_id' => $d['warehouse_id'] ?? $pr['warehouse_id'], 'order_date' => $d['order_date'] ?? date('Y-m-d'),
            'expected_date' => $d['expected_date'] ?? $pr['required_date'], 'notes' => $d['notes'] ?? ('Dari PR ' . $pr['pr_no']), 'pr_id' => $prId, 'items' => $items];
        return $this->create($payload);
    }

    public function list(array $input): Paginator
    {
        return $this->docs->paginate($input, $this->ctx->company_id, 'po_no', 'order_date');
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
        $doc['history'] = $this->workflow->history('purchase_order', $id);
        $doc['actions'] = $this->workflow->availableActions('purchase_order', $doc['status']);
        return $doc;
    }

    public function create(array $d): int
    {
        $this->validator->validateOrder($d);
        $supplier = $this->supplierForCompany((int) $d['supplier_id']);
        $wh = $this->warehouseForCompany((int) $d['warehouse_id']);
        return $this->db->transaction(function () use ($d, $supplier, $wh) {
            $no = $this->numbering->next('PURCHASE_ORDER', $wh['branch_code'], (int) $wh['branch_id'], $d['order_date']);
            [$items, $totals] = $this->mapItems($d['items']);
            $id = $this->docs->insert(['company_id' => $this->ctx->company_id, 'branch_id' => (int) $wh['branch_id'], 'supplier_id' => (int) $supplier['id'],
                'warehouse_id' => (int) $wh['id'], 'pr_id' => !empty($d['pr_id']) ? (int) $d['pr_id'] : null, 'po_no' => $no, 'order_date' => $d['order_date'],
                'expected_date' => $d['expected_date'] ?: null, 'payment_term_days' => (int) ($d['payment_term_days'] ?? $supplier['payment_term_days'] ?? 0),
                'currency' => strtoupper($d['currency'] ?? 'IDR'), 'notes' => $d['notes'] ?: null, 'subtotal' => $totals['subtotal'], 'discount_total' => $totals['discount'],
                'tax_total' => $totals['tax'], 'grand_total' => $totals['grand'], 'created_by' => $this->ctx->user_id]);
            $this->docs->replaceItems($id, $items);
            $this->workflow->start('purchase_order', $id, $no);
            // Konversi dari PR: tutup PR bila aktor berwenang menyetujui (jaga prinsip transisi eksplisit).
            if (!empty($d['pr_id']) && $this->ctx->can('purchasing.pr.approve')) {
                $pr = $this->requests->findOrFail((int) $d['pr_id'], true);
                if ((int) $pr['company_id'] === $this->ctx->company_id && $pr['status'] === 'APPROVED') {
                    $this->workflow->transition('purchase_request', (int) $d['pr_id'], $pr['pr_no'], 'APPROVED', 'close', 'purchasing.pr.approve', 'Dikonversi ke PO ' . $no);
                    $this->requests->update((int) $d['pr_id'], ['status' => 'CLOSED', 'closed_at' => date('Y-m-d H:i:s')]);
                }
            }
            $this->audit->log('purchasing', 'create', 'purchase_orders', $id, null, ['po_no' => $no, 'items' => count($items), 'grand_total' => $totals['grand']], $no);
            return $id;
        });
    }

    public function update(int $id, array $d): void
    {
        $doc = $this->get($id);
        if ($doc['status'] !== 'DRAFT') {
            throw new Invalid_transition_exception('Hanya PO DRAFT yang dapat diubah');
        }
        $this->validator->validateOrder($d);
        $wh = $this->warehouseForCompany((int) $d['warehouse_id']);
        $this->db->transaction(function () use ($id, $d, $doc, $wh) {
            [$items, $totals] = $this->mapItems($d['items']);
            $this->docs->updateVersioned($id, (int) $doc['version'], ['supplier_id' => (int) $this->supplierForCompany((int) $d['supplier_id'])['id'], 'warehouse_id' => (int) $wh['id'],
                'order_date' => $d['order_date'], 'expected_date' => $d['expected_date'] ?: null, 'payment_term_days' => (int) ($d['payment_term_days'] ?? 0),
                'notes' => $d['notes'] ?: null, 'subtotal' => $totals['subtotal'], 'discount_total' => $totals['discount'], 'tax_total' => $totals['tax'], 'grand_total' => $totals['grand'], 'updated_by' => $this->ctx->user_id]);
            $this->docs->replaceItems($id, $items);
            $this->audit->log('purchasing', 'update', 'purchase_orders', $id, null, ['items' => count($items)], $doc['po_no']);
        });
    }

    public function action(int $id, string $action, ?string $notes = null): string
    {
        $permMap = ['submit' => 'purchasing.po.edit', 'approve' => 'purchasing.po.approve', 'reject' => 'purchasing.po.approve', 'cancel' => 'purchasing.po.cancel', 'order' => 'purchasing.po.post', 'close' => 'purchasing.po.edit'];
        if (!isset($permMap[$action])) {
            throw new Invalid_transition_exception('Aksi tidak dikenal');
        }
        return $this->db->transaction(function () use ($id, $action, $notes, $permMap) {
            $doc = $this->docs->findOrFail($id, true);
            if ((int) $doc['company_id'] !== $this->ctx->company_id) {
                throw new Not_found_exception();
            }
            if ($action === 'approve' && (int) $doc['created_by'] === $this->ctx->user_id && $this->settings->get('workflow.enforce_segregation', true) && !$this->ctx->is_superadmin) {
                throw new Authorization_exception('Pembuat PO tidak boleh menyetujui PO-nya sendiri');
            }
            if ($action === 'reject' && trim((string) $notes) === '') {
                throw new Validation_exception(['notes' => 'Alasan penolakan wajib diisi']);
            }
            $next = $this->workflow->transition('purchase_order', $id, $doc['po_no'], $doc['status'], $action, $permMap[$action], $notes);
            $now = date('Y-m-d H:i:s');
            $update = ['status' => $next, 'updated_by' => $this->ctx->user_id];
            if ($action === 'submit') {
                $update += ['submitted_by' => $this->ctx->user_id, 'submitted_at' => $now];
            } elseif ($action === 'approve') {
                $update += ['approved_by' => $this->ctx->user_id, 'approved_at' => $now];
            } elseif ($action === 'reject') {
                $update['rejection_reason'] = $notes;
            } elseif ($action === 'order') {
                $update += ['ordered_by' => $this->ctx->user_id, 'ordered_at' => $now];
            } elseif ($action === 'close') {
                $update['closed_at'] = $now;
            }
            $this->docs->updateVersioned($id, (int) $doc['version'], $update);
            $this->audit->log('purchasing', $action, 'purchase_orders', $id, ['status' => $doc['status']], ['status' => $next], $doc['po_no'], $notes);
            return $next;
        });
    }

    /** Dipanggil Goods_receipt_service setelah GR posting: sinkronkan qty_received & status penerimaan PO. MUST di dalam transaksi. */
    public function applyReceipt(int $poId, array $receivedByPoItem): void
    {
        $po = $this->docs->findOrFail($poId, true);
        foreach ($this->docs->items($poId) as $it) {
            if (isset($receivedByPoItem[(int) $it['id']])) {
                $newRecv = (float) $it['qty_received'] + (float) $receivedByPoItem[(int) $it['id']];
                $this->docs->updateItem((int) $it['id'], ['qty_received' => $newRecv]);
            }
        }
        $items = $this->docs->items($poId);
        $allDone = true;
        $anyRecv = false;
        foreach ($items as $it) {
            if ((float) $it['qty_received'] > 0) {
                $anyRecv = true;
            }
            if ((float) $it['qty_received'] + 0.000001 < (float) $it['qty_ordered']) {
                $allDone = false;
            }
        }
        if (in_array($po['status'], ['ORDERED', 'PARTIAL', 'APPROVED'], true)) {
            $newStatus = $allDone ? 'RECEIVED' : ($anyRecv ? 'PARTIAL' : $po['status']);
            if ($newStatus !== $po['status']) {
                $this->docs->update($poId, ['status' => $newStatus]);
                $this->audit->log('purchasing', 'receipt_sync', 'purchase_orders', $poId, ['status' => $po['status']], ['status' => $newStatus], $po['po_no']);
            }
        }
    }

    /** Kebalikan applyReceipt saat GR dibalik. */
    public function revertReceipt(int $poId, array $receivedByPoItem): void
    {
        $po = $this->docs->findOrFail($poId, true);
        foreach ($this->docs->items($poId) as $it) {
            if (isset($receivedByPoItem[(int) $it['id']])) {
                $newRecv = max(0, (float) $it['qty_received'] - (float) $receivedByPoItem[(int) $it['id']]);
                $this->docs->updateItem((int) $it['id'], ['qty_received' => $newRecv]);
            }
        }
        $items = $this->docs->items($poId);
        $anyRecv = (bool) array_filter($items, fn($it) => (float) $it['qty_received'] > 0);
        if (in_array($po['status'], ['RECEIVED', 'PARTIAL'], true)) {
            $newStatus = $anyRecv ? 'PARTIAL' : 'ORDERED';
            $this->docs->update($poId, ['status' => $newStatus]);
            $this->audit->log('purchasing', 'receipt_revert', 'purchase_orders', $poId, ['status' => $po['status']], ['status' => $newStatus], $po['po_no']);
        }
    }

    public function openOrders(): array
    {
        return $this->db->ci->select('id, po_no, supplier_id, order_date, status')->from('purchase_orders')
            ->where('company_id', $this->ctx->company_id)->where_in('status', ['ORDERED', 'PARTIAL', 'APPROVED'])->order_by('order_date DESC')->get()->result_array();
    }

    private function mapItems(array $items): array
    {
        $rows = [];
        $n = 0;
        $subtotal = 0;
        $discount = 0;
        $tax = 0;
        foreach ($this->validator->cleanItems($items) as $it) {
            $qty = (float) $it['qty_ordered'];
            $price = (float) ($it['unit_price'] ?? 0);
            $discPct = (float) ($it['discount_pct'] ?? 0);
            $taxPct = (float) ($it['tax_pct'] ?? 0);
            $gross = $qty * $price;
            $disc = $gross * $discPct / 100;
            $net = $gross - $disc;
            $lineTax = $net * $taxPct / 100;
            $subtotal += $gross;
            $discount += $disc;
            $tax += $lineTax;
            $rows[] = ['line_no' => ++$n, 'product_id' => (int) $it['product_id'], 'uom_id' => !empty($it['uom_id']) ? (int) $it['uom_id'] : null,
                'qty_ordered' => $qty, 'unit_price' => $price, 'discount_pct' => $discPct, 'tax_pct' => $taxPct, 'line_total' => round($net + $lineTax, 2), 'notes' => $it['notes'] ?? null];
        }
        $totals = ['subtotal' => round($subtotal, 2), 'discount' => round($discount, 2), 'tax' => round($tax, 2), 'grand' => round($subtotal - $discount + $tax, 2)];
        return [$rows, $totals];
    }

    private function supplierForCompany(int $id): array
    {
        $s = $this->suppliers->find($id);
        if (!$s || (int) $s['company_id'] !== $this->ctx->company_id || !$s['is_active']) {
            throw new Validation_exception(['supplier_id' => 'Supplier tidak valid / tidak aktif']);
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
