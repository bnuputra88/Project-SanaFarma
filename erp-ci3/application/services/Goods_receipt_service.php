<?php
/**
 * Goods Receipt: DRAFT → SUBMITTED (inspeksi) → APPROVED → POSTED (RECEIPT ke ledger via Inventory_service, batch capture + put-away) → REVERSED.
 * Aturan farmasi: produk batch/expiry-tracked WAJIB batch_no + expiry di service layer (bukan hanya form).
 */
class Goods_receipt_service
{
    private $docs;
    private $inventory;
    private $orders;
    private $suppliers;
    private $catalog;
    private $batches;
    private $workflow;
    private $numbering;
    private $validator;
    private $warehouses;
    private $products;
    private $settings;
    private $audit;
    private $ctx;
    private $db;

    public function __construct(CI_DB_query_builder $cidb, Inventory_service $inventory, Purchase_order_service $orders, Supplier_repository $suppliers, Supplier_product_repository $catalog,
                                Batch_repository $batches, Workflow_service $workflow, Numbering_service $numbering, Purchase_document_validator $validator,
                                Warehouse_repository $warehouses, Product_repository $products, Setting_service $settings, Audit_service $audit, Request_context $ctx, Db $db)
    {
        $this->docs = Purchase_document_repository::receipts($cidb);
        $this->inventory = $inventory;
        $this->orders = $orders;
        $this->suppliers = $suppliers;
        $this->catalog = $catalog;
        $this->batches = $batches;
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

    public function list(array $input): Paginator
    {
        return $this->docs->paginate($input, $this->ctx->company_id, 'gr_no', 'receipt_date');
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
        $doc['history'] = $this->workflow->history('goods_receipt', $id);
        $doc['actions'] = $this->workflow->availableActions('goods_receipt', $doc['status']);
        return $doc;
    }

    public function create(array $d): int
    {
        $this->validator->validateReceipt($d);
        $supplier = $this->supplierForCompany((int) $d['supplier_id']);
        $wh = $this->warehouseForCompany((int) $d['warehouse_id']);
        $poId = !empty($d['po_id']) ? (int) $d['po_id'] : null;
        return $this->db->transaction(function () use ($d, $supplier, $wh, $poId) {
            $no = $this->numbering->next('GOODS_RECEIPT', $wh['branch_code'], (int) $wh['branch_id'], $d['receipt_date']);
            $items = $this->mapItems($d['items']);
            $total = array_sum(array_map(fn($i) => $i['qty_received'] * $i['unit_cost'], $items));
            $id = $this->docs->insert(['company_id' => $this->ctx->company_id, 'branch_id' => (int) $wh['branch_id'], 'supplier_id' => (int) $supplier['id'], 'po_id' => $poId,
                'warehouse_id' => (int) $wh['id'], 'gr_no' => $no, 'receipt_date' => $d['receipt_date'], 'supplier_do_no' => $d['supplier_do_no'] ?: null,
                'supplier_invoice_no' => $d['supplier_invoice_no'] ?: null, 'notes' => $d['notes'] ?: null, 'total_value' => round($total, 2), 'created_by' => $this->ctx->user_id]);
            $this->docs->replaceItems($id, $items);
            $this->workflow->start('goods_receipt', $id, $no);
            $this->audit->log('purchasing', 'create', 'goods_receipts', $id, null, ['gr_no' => $no, 'items' => count($items)], $no);
            return $id;
        });
    }

    public function update(int $id, array $d): void
    {
        $doc = $this->get($id);
        if ($doc['status'] !== 'DRAFT') {
            throw new Invalid_transition_exception('Hanya GR DRAFT yang dapat diubah');
        }
        $this->validator->validateReceipt($d);
        $wh = $this->warehouseForCompany((int) $d['warehouse_id']);
        $this->db->transaction(function () use ($id, $d, $doc, $wh) {
            $items = $this->mapItems($d['items']);
            $total = array_sum(array_map(fn($i) => $i['qty_received'] * $i['unit_cost'], $items));
            $this->docs->updateVersioned($id, (int) $doc['version'], ['supplier_id' => (int) $this->supplierForCompany((int) $d['supplier_id'])['id'], 'warehouse_id' => (int) $wh['id'],
                'po_id' => !empty($d['po_id']) ? (int) $d['po_id'] : null, 'receipt_date' => $d['receipt_date'], 'supplier_do_no' => $d['supplier_do_no'] ?: null,
                'supplier_invoice_no' => $d['supplier_invoice_no'] ?: null, 'notes' => $d['notes'] ?: null, 'total_value' => round($total, 2), 'updated_by' => $this->ctx->user_id]);
            $this->docs->replaceItems($id, $items);
            $this->audit->log('purchasing', 'update', 'goods_receipts', $id, null, ['items' => count($items)], $doc['gr_no']);
        });
    }

    public function action(int $id, string $action, ?string $notes = null): string
    {
        $permMap = ['submit' => 'purchasing.gr.edit', 'approve' => 'purchasing.gr.approve', 'reject' => 'purchasing.gr.approve', 'cancel' => 'purchasing.gr.cancel', 'post' => 'purchasing.gr.post', 'reverse' => 'purchasing.gr.post'];
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
            $next = $this->workflow->transition('goods_receipt', $id, $doc['gr_no'], $doc['status'], $action, $permMap[$action], $notes);
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
            } elseif ($action === 'reverse') {
                $update['reversal_movement_id'] = $this->reverse($doc, (string) ($notes ?: 'Pembalikan GR'));
            }
            $this->docs->updateVersioned($id, (int) $doc['version'], $update);
            $this->audit->log('purchasing', $action, 'goods_receipts', $id, ['status' => $doc['status']], ['status' => $next], $doc['gr_no'], $notes);
            return $next;
        });
    }

    /** Posting RECEIPT ke ledger. Line REJECTED tidak masuk stok; QUARANTINE masuk kondisi QUARANTINE; sisanya GOOD. */
    private function post(array $doc): int
    {
        $lines = [];
        $receivedByPoItem = [];
        foreach ($this->docs->items((int) $doc['id']) as $it) {
            if ($it['inspection_result'] === 'REJECTED') {
                continue;
            }
            $product = $this->products->findOrFail((int) $it['product_id']);
            if ($product['is_batch_tracked'] && trim((string) $it['batch_no']) === '') {
                throw new Validation_exception(['batch_no' => "Produk {$product['sku']} adalah produk farmasi/batch-tracked: nomor batch wajib diisi di GR"]);
            }
            if ($product['is_expiry_tracked'] && empty($it['expiry_date'])) {
                throw new Validation_exception(['expiry_date' => "Produk {$product['sku']} wajib memiliki tanggal kedaluwarsa (expiry) di GR"]);
            }
            $condition = $it['inspection_result'] === 'QUARANTINE' ? 'QUARANTINE' : 'GOOD';
            $lines[] = ['warehouse_id' => (int) $doc['warehouse_id'], 'location_id' => !empty($it['location_id']) ? (int) $it['location_id'] : null, 'product_id' => (int) $it['product_id'],
                'batch_no' => $it['batch_no'] ?: null, 'expiry_date' => $it['expiry_date'] ?: null, 'manufacture_date' => $it['manufacture_date'] ?: null,
                'condition_code' => $condition, 'qty' => (float) $it['qty_received'], 'unit_cost' => (float) $it['unit_cost'], 'supplier_id' => (int) $doc['supplier_id']];
            if (!empty($it['po_item_id'])) {
                $receivedByPoItem[(int) $it['po_item_id']] = ($receivedByPoItem[(int) $it['po_item_id']] ?? 0) + (float) $it['qty_received'];
            }
        }
        if (!$lines) {
            throw new Domain_exception('Tidak ada baris yang dapat diposting (semua REJECTED atau kosong)');
        }
        $movementId = $this->inventory->post(['movement_type' => 'RECEIPT', 'ref_type' => 'goods_receipts', 'ref_id' => (int) $doc['id'], 'ref_no' => $doc['gr_no'],
            'notes' => $doc['notes'], 'branch_id' => (int) $doc['branch_id']], $lines);

        // Kaitkan batch_id hasil posting ke item GR + rekam harga supplier
        foreach ($this->docs->items((int) $doc['id']) as $it) {
            if ($it['inspection_result'] === 'REJECTED') {
                continue;
            }
            $batchId = null;
            if (!empty($it['batch_no'])) {
                $b = $this->batches->findBy(['product_id' => (int) $it['product_id'], 'batch_no' => trim($it['batch_no'])]);
                $batchId = $b ? (int) $b['id'] : null;
                if ($batchId) {
                    $this->docs->updateItem((int) $it['id'], ['batch_id' => $batchId]);
                }
            }
            $poPrice = null;
            if (!empty($it['po_item_id'])) {
                $poi = $this->db->ci->select('unit_price')->from('purchase_order_items')->where('id', (int) $it['po_item_id'])->get()->row_array();
                $poPrice = $poi ? (float) $poi['unit_price'] : null;
            }
            $this->catalog->recordPrice($this->ctx->company_id, (int) $doc['supplier_id'], (int) $it['product_id'], (float) $it['unit_cost'],
                ['ref_type' => 'goods_receipts', 'ref_id' => (int) $doc['id'], 'ref_no' => $doc['gr_no'], 'po_price' => $poPrice, 'date' => $doc['receipt_date'], 'actor' => $this->ctx->user_id]);
        }

        if (!empty($doc['po_id']) && $receivedByPoItem) {
            $this->orders->applyReceipt((int) $doc['po_id'], $receivedByPoItem);
        }
        return $movementId;
    }

    /** Pembalikan GR: ditolak (Conflict) jika stok yang diterima sudah terpakai (FEFO sudah keluarkan). */
    private function reverse(array $doc, string $reason): int
    {
        if (empty($doc['movement_id'])) {
            throw new Conflict_exception('GR belum diposting, tidak ada mutasi untuk dibalik');
        }
        $mid = (int) $doc['movement_id'];
        $movItems = $this->db->ci->select('warehouse_id, location_id, product_id, batch_id, condition_code, qty')->from('stock_movement_items')->where('movement_id', $mid)->get()->result_array();
        foreach ($movItems as $mi) {
            if ((float) $mi['qty'] <= 0) {
                continue;
            }
            $qb = $this->db->ci->select_sum('qty_on_hand')->from('stock_balances')
                ->where(['warehouse_id' => (int) $mi['warehouse_id'], 'product_id' => (int) $mi['product_id'], 'condition_code' => $mi['condition_code']]);
            $mi['batch_id'] === null ? $qb->where('batch_id IS NULL', null, false) : $qb->where('batch_id', (int) $mi['batch_id']);
            $mi['location_id'] === null ? $qb->where('location_id IS NULL', null, false) : $qb->where('location_id', (int) $mi['location_id']);
            $available = (float) ($qb->get()->row()->qty_on_hand ?? 0);
            if ($available + 0.000001 < (float) $mi['qty']) {
                throw new Conflict_exception('Tidak dapat membalik GR: sebagian stok yang diterima sudah terpakai/keluar');
            }
        }
        $revId = $this->inventory->reverse($mid, $reason);
        $receivedByPoItem = [];
        foreach ($this->docs->items((int) $doc['id']) as $it) {
            if (!empty($it['po_item_id']) && $it['inspection_result'] !== 'REJECTED') {
                $receivedByPoItem[(int) $it['po_item_id']] = ($receivedByPoItem[(int) $it['po_item_id']] ?? 0) + (float) $it['qty_received'];
            }
        }
        if (!empty($doc['po_id']) && $receivedByPoItem) {
            $this->orders->revertReceipt((int) $doc['po_id'], $receivedByPoItem);
        }
        return $revId;
    }

    private function mapItems(array $items): array
    {
        $rows = [];
        $n = 0;
        foreach ($this->validator->cleanReceiptItems($items) as $it) {
            $inspection = $it['inspection_result'] ?? 'ACCEPTED';
            $condition = $inspection === 'QUARANTINE' ? 'QUARANTINE' : ($inspection === 'REJECTED' ? 'DAMAGED' : 'GOOD');
            $rows[] = ['line_no' => ++$n, 'po_item_id' => !empty($it['po_item_id']) ? (int) $it['po_item_id'] : null, 'product_id' => (int) $it['product_id'],
                'location_id' => !empty($it['location_id']) ? (int) $it['location_id'] : null, 'batch_no' => $it['batch_no'] ?: null, 'manufacture_date' => $it['manufacture_date'] ?: null,
                'expiry_date' => $it['expiry_date'] ?: null, 'qty_received' => (float) $it['qty_received'], 'unit_cost' => (float) ($it['unit_cost'] ?? 0),
                'condition_code' => $condition, 'inspection_result' => $inspection, 'notes' => $it['notes'] ?? null];
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
