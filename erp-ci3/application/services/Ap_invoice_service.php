<?php
/** AP Invoice foundation: buat draft tagihan supplier dari GR yang sudah diposting. Pembayaran & posting GL → Phase 6. */
class Ap_invoice_service
{
    private $repo;
    private $receipts;
    private $suppliers;
    private $numbering;
    private $audit;
    private $ctx;
    private $db;

    public function __construct(Ap_invoice_repository $repo, CI_DB_query_builder $cidb, Supplier_repository $suppliers, Numbering_service $numbering, Audit_service $audit, Request_context $ctx, Db $db)
    {
        $this->repo = $repo;
        $this->receipts = Purchase_document_repository::receipts($cidb);
        $this->suppliers = $suppliers;
        $this->numbering = $numbering;
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
        $doc['supplier'] = $this->suppliers->find((int) $doc['supplier_id']);
        return $doc;
    }

    /** Buat AP invoice dari GR POSTED. */
    public function createFromReceipt(int $grId, array $d): int
    {
        $gr = $this->receipts->findOrFail($grId);
        if ((int) $gr['company_id'] !== $this->ctx->company_id) {
            throw new Not_found_exception();
        }
        if ($gr['status'] !== 'POSTED') {
            throw new Invalid_transition_exception('AP invoice hanya dapat dibuat dari GR yang sudah diposting');
        }
        if ($this->repo->exists(['gr_id' => $grId])) {
            throw new Conflict_exception('AP invoice untuk GR ini sudah dibuat');
        }
        return $this->db->transaction(function () use ($gr, $grId, $d) {
            $no = $this->numbering->next('AP_INVOICE', null, (int) $gr['branch_id'], $d['invoice_date'] ?? date('Y-m-d'));
            $items = [];
            $subtotal = 0;
            $tax = 0;
            $n = 0;
            foreach ($this->receipts->items($grId) as $it) {
                if ($it['inspection_result'] === 'REJECTED') {
                    continue;
                }
                $qty = (float) $it['qty_received'];
                $price = (float) $it['unit_cost'];
                $lineTotal = round($qty * $price, 2);
                $subtotal += $lineTotal;
                $items[] = ['line_no' => ++$n, 'product_id' => (int) $it['product_id'], 'description' => $it['product_name'] . ' (' . $it['sku'] . ')', 'qty' => $qty, 'unit_price' => $price, 'tax_pct' => 0, 'line_total' => $lineTotal];
            }
            if (!$items) {
                throw new Domain_exception('GR tidak memiliki baris yang dapat ditagihkan');
            }
            $invoiceDate = $d['invoice_date'] ?? date('Y-m-d');
            $term = (int) ($this->suppliers->find((int) $gr['supplier_id'])['payment_term_days'] ?? 0);
            $due = $d['due_date'] ?? date('Y-m-d', strtotime($invoiceDate . ' +' . $term . ' days'));
            $grand = round($subtotal + $tax, 2);
            $id = $this->repo->insert(['company_id' => $this->ctx->company_id, 'branch_id' => (int) $gr['branch_id'], 'supplier_id' => (int) $gr['supplier_id'],
                'po_id' => !empty($gr['po_id']) ? (int) $gr['po_id'] : null, 'gr_id' => $grId, 'ap_no' => $no, 'supplier_invoice_no' => $d['supplier_invoice_no'] ?? $gr['supplier_invoice_no'],
                'invoice_date' => $invoiceDate, 'due_date' => $due, 'subtotal' => round($subtotal, 2), 'tax_total' => $tax, 'grand_total' => $grand,
                'status' => 'OPEN', 'notes' => $d['notes'] ?? null, 'created_by' => $this->ctx->user_id]);
            $this->repo->replaceItems($id, $items);
            $this->audit->log('purchasing', 'create', 'ap_invoices', $id, null, ['ap_no' => $no, 'gr_no' => $gr['gr_no'], 'grand_total' => $grand], $no);
            return $id;
        });
    }

    public function cancel(int $id, string $reason): void
    {
        $doc = $this->get($id);
        if (in_array($doc['status'], ['PAID', 'CANCELLED'], true)) {
            throw new Invalid_transition_exception('Invoice tidak dapat dibatalkan pada status ini');
        }
        $this->db->transaction(function () use ($id, $doc, $reason) {
            $this->repo->updateVersioned($id, (int) $doc['version'], ['status' => 'CANCELLED', 'notes' => trim(($doc['notes'] ?? '') . ' | BATAL: ' . $reason)]);
            $this->audit->log('purchasing', 'cancel', 'ap_invoices', $id, ['status' => $doc['status']], ['status' => 'CANCELLED'], $doc['ap_no'], $reason);
        });
    }
}
