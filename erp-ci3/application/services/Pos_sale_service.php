<?php
/** POS: checkout satu langkah — buat sale, alokasi FEFO, posting ISSUE ke ledger, catat pembayaran, hitung kembalian. Void = reversal. */
class Pos_sale_service
{
    private $sales;
    private $inventory;
    private $shifts;
    private $products;
    private $customers;
    private $workflow;
    private $numbering;
    private $validator;
    private $audit;
    private $ctx;
    private $db;

    public function __construct(Sales_repository $sales, Inventory_service $inventory, Cashier_shift_repository $shifts, Product_repository $products, Customer_repository $customers,
                                Workflow_service $workflow, Numbering_service $numbering, Sales_document_validator $validator, Audit_service $audit, Request_context $ctx, Db $db)
    {
        $this->sales = $sales;
        $this->inventory = $inventory;
        $this->shifts = $shifts;
        $this->products = $products;
        $this->customers = $customers;
        $this->workflow = $workflow;
        $this->numbering = $numbering;
        $this->validator = $validator;
        $this->audit = $audit;
        $this->ctx = $ctx;
        $this->db = $db;
    }

    public function list(array $input): Paginator
    {
        return $this->sales->paginate($input, $this->ctx->company_id);
    }

    public function get(int $id): array
    {
        $s = $this->sales->findOrFail($id);
        if ((int) $s['company_id'] !== $this->ctx->company_id) {
            throw new Not_found_exception();
        }
        $s['items'] = $this->sales->items($id);
        $s['payments'] = $this->sales->payments($id);
        $s['customer'] = $s['customer_id'] ? $this->customers->find((int) $s['customer_id']) : null;
        $s['history'] = $this->workflow->history('sale', $id);
        return $s;
    }

    /** Checkout satu langkah: header DRAFT → posting ISSUE → pembayaran → PAID. */
    public function checkout(array $d): int
    {
        $this->validator->validateSale($d);
        $wh = $this->db->ci->select('w.id, w.branch_id, b.code AS branch_code')->from('warehouses w')->join('branches b', 'b.id = w.branch_id')
            ->where(['w.id' => (int) $d['warehouse_id'], 'w.company_id' => $this->ctx->company_id, 'w.is_active' => 1])->get()->row_array();
        if (!$wh) {
            throw new Validation_exception(['warehouse_id' => 'Gudang/etalase tidak valid']);
        }
        $shift = $this->shifts->openForCashier($this->ctx->user_id);
        return $this->db->transaction(function () use ($d, $wh, $shift) {
            $now = date('Y-m-d H:i:s');
            $no = $this->numbering->next('SALE', $wh['branch_code'], (int) $wh['branch_id']);
            $saleId = $this->sales->insert(['company_id' => $this->ctx->company_id, 'branch_id' => (int) $wh['branch_id'], 'warehouse_id' => (int) $wh['id'],
                'shift_id' => $shift ? (int) $shift['id'] : null, 'sale_no' => $no, 'sale_datetime' => $now, 'customer_id' => !empty($d['customer_id']) ? (int) $d['customer_id'] : null,
                'sale_type' => 'POS', 'cashier_id' => $this->ctx->user_id, 'created_by' => $this->ctx->user_id]);

            $lineNo = 0;
            $issueLines = [];
            $subtotal = 0;
            $discount = 0;
            $tax = 0;
            foreach ($this->validator->cleanItems($d['items']) as $it) {
                $product = $this->products->findOrFail((int) $it['product_id']);
                $qty = (float) $it['qty'];
                $price = isset($it['unit_price']) && $it['unit_price'] !== '' ? (float) $it['unit_price'] : (float) $product['selling_price'];
                $discPct = (float) ($it['discount_pct'] ?? 0);
                $taxPct = (float) ($it['tax_pct'] ?? 0);
                $allocs = [];
                if (!empty($it['batch_id']) || !$product['is_batch_tracked']) {
                    $allocs[] = ['batch_id' => !empty($it['batch_id']) ? (int) $it['batch_id'] : null, 'qty' => $qty];
                } else {
                    $r = $this->inventory->allocateFefo((int) $product['id'], (int) $wh['id'], $qty);
                    if ($r['shortage'] > 0) {
                        throw new Insufficient_stock_exception(sprintf('Stok %s tidak cukup (kurang %s)', $product['name'], $r['shortage']));
                    }
                    $allocs = $r['allocations'];
                }
                foreach ($allocs as $a) {
                    $aQty = (float) $a['qty'];
                    $gross = $aQty * $price;
                    $disc = $gross * $discPct / 100;
                    $net = $gross - $disc;
                    $lineTax = $net * $taxPct / 100;
                    $subtotal += $gross;
                    $discount += $disc;
                    $tax += $lineTax;
                    $this->db->ci->insert('sale_items', ['sale_id' => $saleId, 'line_no' => ++$lineNo, 'product_id' => (int) $product['id'], 'batch_id' => $a['batch_id'],
                        'uom_id' => $product['base_uom_id'] ?? null, 'qty' => $aQty, 'unit_price' => $price, 'discount_pct' => $discPct, 'tax_pct' => $taxPct, 'line_total' => round($net + $lineTax, 2), 'notes' => $it['notes'] ?? null]);
                    $issueLines[] = ['warehouse_id' => (int) $wh['id'], 'product_id' => (int) $product['id'], 'batch_id' => $a['batch_id'], 'qty' => -$aQty, 'unit_cost' => null];
                }
            }
            $grand = round($subtotal - $discount + $tax, 2);

            $movementId = $this->inventory->post(['movement_type' => 'ISSUE', 'ref_type' => 'sales', 'ref_id' => $saleId, 'ref_no' => $no, 'notes' => $d['notes'] ?? null, 'branch_id' => (int) $wh['branch_id']], $issueLines);

            $payments = $this->validator->cleanPayments($d['payments'] ?? []);
            if (!$payments) {
                $payments = [['method' => 'CASH', 'amount' => $grand]];
            }
            $paid = 0;
            $pRows = [];
            $pn = 0;
            foreach ($payments as $p) {
                $paid += (float) $p['amount'];
                $pRows[] = ['line_no' => ++$pn, 'method' => $p['method'] ?? 'CASH', 'amount' => (float) $p['amount'], 'reference' => $p['reference'] ?? null, 'paid_at' => $now];
            }
            if ($paid + 0.000001 < $grand) {
                throw new Validation_exception(['payments' => sprintf('Pembayaran kurang: dibayar %s dari total %s', $paid, $grand)]);
            }
            $this->sales->replacePayments($saleId, $pRows);

            $this->workflow->start('sale', $saleId, $no);
            $this->workflow->transition('sale', $saleId, $no, 'DRAFT', 'checkout', 'sales.pos.create');
            $this->sales->update($saleId, ['status' => 'PAID', 'subtotal' => round($subtotal, 2), 'discount_total' => round($discount, 2), 'tax_total' => round($tax, 2),
                'grand_total' => $grand, 'paid_total' => round($paid, 2), 'change_amount' => round($paid - $grand, 2), 'movement_id' => $movementId, 'paid_at' => $now]);
            $this->audit->log('sales', 'checkout', 'sales', $saleId, null, ['sale_no' => $no, 'grand_total' => $grand, 'lines' => $lineNo], $no);
            return $saleId;
        });
    }

    public function void(int $id, string $reason): void
    {
        if (trim($reason) === '') {
            throw new Validation_exception(['reason' => 'Alasan void wajib diisi']);
        }
        $this->db->transaction(function () use ($id, $reason) {
            $s = $this->sales->findOrFail($id, true);
            if ((int) $s['company_id'] !== $this->ctx->company_id) {
                throw new Not_found_exception();
            }
            $this->workflow->transition('sale', $id, $s['sale_no'], $s['status'], 'void', 'sales.pos.void', $reason);
            $revId = $this->inventory->reverse((int) $s['movement_id'], 'Void penjualan: ' . $reason);
            $this->sales->update($id, ['status' => 'VOID', 'void_movement_id' => $revId, 'voided_at' => date('Y-m-d H:i:s'), 'void_reason' => $reason]);
            $this->audit->log('sales', 'void', 'sales', $id, ['status' => $s['status']], ['status' => 'VOID', 'reversal_id' => $revId], $s['sale_no'], $reason);
        });
    }
}
