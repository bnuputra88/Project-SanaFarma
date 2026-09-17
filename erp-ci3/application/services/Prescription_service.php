<?php
/** Resep & Dispensing farmasi: DRAFT -> VERIFIED (skrining apoteker) -> DISPENSED (buat sale PRESCRIPTION, keluar stok FEFO, catat pembayaran). */
class Prescription_service
{
    private $repo;
    private $inventory;
    private $sales;
    private $shifts;
    private $products;
    private $workflow;
    private $numbering;
    private $validator;
    private $settings;
    private $audit;
    private $ctx;
    private $db;

    public function __construct(Prescription_repository $repo, Inventory_service $inventory, Sales_repository $sales, Cashier_shift_repository $shifts, Product_repository $products,
                                Workflow_service $workflow, Numbering_service $numbering, Prescription_validator $validator, Setting_service $settings, Audit_service $audit, Request_context $ctx, Db $db)
    {
        $this->repo = $repo;
        $this->inventory = $inventory;
        $this->sales = $sales;
        $this->shifts = $shifts;
        $this->products = $products;
        $this->workflow = $workflow;
        $this->numbering = $numbering;
        $this->validator = $validator;
        $this->settings = $settings;
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
        $rx = $this->repo->findOrFail($id);
        if ((int) $rx['company_id'] !== $this->ctx->company_id) {
            throw new Not_found_exception();
        }
        $items = $this->repo->items($id);
        $byItem = [];
        foreach ($this->repo->compounds($id) as $c) {
            $byItem[(int) $c['prescription_item_id']][] = $c;
        }
        foreach ($items as &$it) {
            $it['ingredients'] = $byItem[(int) $it['id']] ?? [];
        }
        unset($it);
        $rx['items'] = $items;
        $rx['history'] = $this->workflow->history('prescription', $id);
        $rx['actions'] = $this->workflow->availableActions('prescription', $rx['status']);
        $rx['sale'] = $rx['sale_id'] ? $this->sales->find((int) $rx['sale_id']) : null;
        return $rx;
    }

    public function create(array $d): int
    {
        $this->validator->validate($d);
        $wh = $this->warehouse((int) $d['warehouse_id']);
        return $this->db->transaction(function () use ($d, $wh) {
            $no = $this->numbering->next('PRESCRIPTION', $wh['branch_code'], (int) $wh['branch_id'], $d['prescription_date']);
            $id = $this->repo->insert($this->header($d, $wh) + ['prescription_no' => $no, 'status' => 'DRAFT', 'subtotal' => 0, 'grand_total' => 0, 'created_by' => $this->ctx->user_id]);
            $subtotal = $this->saveItems($id, $d['items']);
            $this->repo->update($id, ['subtotal' => round($subtotal, 2), 'grand_total' => round($subtotal, 2)]);
            $this->workflow->start('prescription', $id, $no);
            $this->audit->log('pharmacy', 'create', 'prescriptions', $id, null, ['prescription_no' => $no], $no);
            return $id;
        });
    }

    public function update(int $id, array $d): int
    {
        $this->validator->validate($d);
        return $this->db->transaction(function () use ($id, $d) {
            $rx = $this->repo->findOrFail($id, true);
            if ((int) $rx['company_id'] !== $this->ctx->company_id) {
                throw new Not_found_exception();
            }
            if ($rx['status'] !== 'DRAFT') {
                throw new Conflict_exception('Resep hanya dapat diubah saat DRAFT');
            }
            $wh = $this->warehouse((int) $d['warehouse_id']);
            $this->repo->deleteItems($id);
            $subtotal = $this->saveItems($id, $d['items']);
            $this->repo->updateVersioned($id, (int) $rx['version'], $this->header($d, $wh) + ['subtotal' => round($subtotal, 2), 'grand_total' => round($subtotal, 2), 'updated_by' => $this->ctx->user_id]);
            $this->audit->log('pharmacy', 'update', 'prescriptions', $id, null, ['prescription_no' => $rx['prescription_no']], $rx['prescription_no']);
            return $id;
        });
    }

    public function verify(int $id, ?string $notes = null): string
    {
        return $this->db->transaction(function () use ($id, $notes) {
            $rx = $this->repo->findOrFail($id, true);
            if ((int) $rx['company_id'] !== $this->ctx->company_id) {
                throw new Not_found_exception();
            }
            $this->screen($id, $rx);
            $next = $this->workflow->transition('prescription', $id, $rx['prescription_no'], $rx['status'], 'verify', 'pharmacy.prescription.verify', $notes);
            $this->repo->updateVersioned($id, (int) $rx['version'], ['status' => $next, 'verified_by' => $this->ctx->user_id, 'verified_at' => date('Y-m-d H:i:s'), 'updated_by' => $this->ctx->user_id]);
            $this->audit->log('pharmacy', 'verify', 'prescriptions', $id, ['status' => $rx['status']], ['status' => $next], $rx['prescription_no'], $notes);
            return $next;
        });
    }

    public function cancel(int $id, ?string $notes = null): string
    {
        return $this->db->transaction(function () use ($id, $notes) {
            $rx = $this->repo->findOrFail($id, true);
            if ((int) $rx['company_id'] !== $this->ctx->company_id) {
                throw new Not_found_exception();
            }
            $next = $this->workflow->transition('prescription', $id, $rx['prescription_no'], $rx['status'], 'cancel', 'pharmacy.prescription.cancel', $notes);
            $this->repo->updateVersioned($id, (int) $rx['version'], ['status' => $next, 'cancel_reason' => $notes, 'updated_by' => $this->ctx->user_id]);
            $this->audit->log('pharmacy', 'cancel', 'prescriptions', $id, ['status' => $rx['status']], ['status' => $next], $rx['prescription_no'], $notes);
            return $next;
        });
    }

    /** Dispensing: keluar stok FEFO seluruh obat & bahan racikan, buat sale PRESCRIPTION + pembayaran. */
    public function dispense(int $id, array $d): int
    {
        return $this->db->transaction(function () use ($id, $d) {
            $rx = $this->repo->findOrFail($id, true);
            if ((int) $rx['company_id'] !== $this->ctx->company_id) {
                throw new Not_found_exception();
            }
            if ($rx['status'] !== 'VERIFIED') {
                throw new Conflict_exception('Resep harus diverifikasi apoteker sebelum diserahkan');
            }
            $wh = $this->warehouse((int) $rx['warehouse_id']);
            $shift = $this->shifts->openForCashier($this->ctx->user_id);
            $now = date('Y-m-d H:i:s');
            $no = $this->numbering->next('SALE', $wh['branch_code'], (int) $wh['branch_id']);
            $saleId = $this->sales->insert(['company_id' => $this->ctx->company_id, 'branch_id' => (int) $wh['branch_id'], 'warehouse_id' => (int) $wh['id'],
                'shift_id' => $shift ? (int) $shift['id'] : null, 'sale_no' => $no, 'sale_datetime' => $now, 'customer_id' => $rx['customer_id'] ? (int) $rx['customer_id'] : null,
                'sale_type' => 'PRESCRIPTION', 'status' => 'DRAFT', 'cashier_id' => $this->ctx->user_id, 'created_by' => $this->ctx->user_id, 'notes' => 'Resep ' . $rx['prescription_no']]);

            $lineNo = 0;
            $subtotal = 0;
            $issueLines = [];
            $saleRows = [];
            foreach ($this->allocationLines($id, (int) $wh['id']) as $al) {
                $gross = $al['qty'] * $al['price'];
                $subtotal += $gross;
                $saleRows[] = ['line_no' => ++$lineNo, 'product_id' => $al['product_id'], 'batch_id' => $al['batch_id'], 'uom_id' => $al['uom_id'],
                    'qty' => $al['qty'], 'unit_price' => $al['price'], 'discount_pct' => 0, 'tax_pct' => 0, 'line_total' => round($gross, 2), 'notes' => $al['notes']];
                $issueLines[] = ['warehouse_id' => (int) $wh['id'], 'product_id' => $al['product_id'], 'batch_id' => $al['batch_id'], 'qty' => -$al['qty'], 'unit_cost' => null];
            }
            if (!$issueLines) {
                throw new Validation_exception(['items' => 'Tidak ada item untuk diserahkan']);
            }
            $this->sales->replaceItems($saleId, $saleRows);
            $grand = round($subtotal, 2);
            $movementId = $this->inventory->post(['movement_type' => 'ISSUE', 'ref_type' => 'sales', 'ref_id' => $saleId, 'ref_no' => $no,
                'notes' => 'Dispensing resep ' . $rx['prescription_no'], 'branch_id' => (int) $wh['branch_id']], $issueLines);

            $payments = $this->cleanPayments($d['payments'] ?? []);
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
            $this->sales->update($saleId, ['status' => 'PAID', 'subtotal' => $grand, 'grand_total' => $grand, 'paid_total' => round($paid, 2),
                'change_amount' => round($paid - $grand, 2), 'movement_id' => $movementId, 'paid_at' => $now]);

            $this->workflow->transition('prescription', $id, $rx['prescription_no'], $rx['status'], 'dispense', 'pharmacy.prescription.dispense');
            $this->repo->updateVersioned($id, (int) $rx['version'], ['status' => 'DISPENSED', 'sale_id' => $saleId, 'grand_total' => $grand,
                'dispensed_by' => $this->ctx->user_id, 'dispensed_at' => $now, 'updated_by' => $this->ctx->user_id]);
            $this->audit->log('pharmacy', 'dispense', 'prescriptions', $id, ['status' => 'VERIFIED'], ['status' => 'DISPENSED', 'sale_no' => $no, 'grand_total' => $grand], $rx['prescription_no']);
            return $saleId;
        });
    }

    public function apotekInfo(): array
    {
        return ['name' => $this->settings->get('company.name', 'Apotek'), 'address' => $this->settings->get('company.address', ''),
            'sia' => $this->settings->get('pharmacy.sia_no', ''), 'phone' => $this->settings->get('company.phone', '')];
    }

    // ---- helpers ----
    private function header(array $d, array $wh): array
    {
        return ['company_id' => $this->ctx->company_id, 'branch_id' => (int) $wh['branch_id'], 'warehouse_id' => (int) $wh['id'],
            'prescription_date' => $d['prescription_date'], 'customer_id' => !empty($d['customer_id']) ? (int) $d['customer_id'] : null,
            'doctor_name' => ($d['doctor_name'] ?? '') !== '' ? trim($d['doctor_name']) : null,
            'doctor_sip' => ($d['doctor_sip'] ?? '') !== '' ? trim($d['doctor_sip']) : null,
            'patient_name' => trim($d['patient_name']),
            'patient_age' => ($d['patient_age'] ?? '') !== '' ? $d['patient_age'] : null,
            'patient_weight' => ($d['patient_weight'] ?? '') !== '' ? (float) $d['patient_weight'] : null,
            'diagnosis' => ($d['diagnosis'] ?? '') !== '' ? $d['diagnosis'] : null,
            'notes' => ($d['notes'] ?? '') !== '' ? $d['notes'] : null];
    }

    private function saveItems(int $prescriptionId, array $items): float
    {
        $lineNo = 0;
        $subtotal = 0;
        foreach ($this->validator->cleanItems($items) as $it) {
            $type = ($it['item_type'] ?? 'PRODUCT') === 'COMPOUND' ? 'COMPOUND' : 'PRODUCT';
            $qty = (float) ($it['qty'] ?? 0);
            $labelColor = ($it['label_color'] ?? 'WHITE') === 'BLUE' ? 'BLUE' : 'WHITE';
            $shake = !empty($it['shake_well']) ? 1 : 0;
            if ($type === 'PRODUCT') {
                $product = $this->products->findOrFail((int) $it['product_id']);
                $price = isset($it['unit_price']) && $it['unit_price'] !== '' ? (float) $it['unit_price'] : (float) $product['selling_price'];
                $lineTotal = $qty * $price;
                $this->repo->insertItem(['prescription_id' => $prescriptionId, 'line_no' => ++$lineNo, 'item_type' => 'PRODUCT', 'product_id' => (int) $product['id'],
                    'compound_name' => null, 'qty' => $qty, 'signa' => $it['signa'] ?? null, 'label_color' => $labelColor, 'shake_well' => $shake,
                    'unit_price' => $price, 'line_total' => round($lineTotal, 2), 'notes' => $it['notes'] ?? null]);
                $subtotal += $lineTotal;
            } else {
                $itemId = $this->repo->insertItem(['prescription_id' => $prescriptionId, 'line_no' => ++$lineNo, 'item_type' => 'COMPOUND', 'product_id' => null,
                    'compound_name' => trim((string) $it['compound_name']), 'qty' => $qty, 'signa' => $it['signa'] ?? null, 'label_color' => $labelColor, 'shake_well' => $shake,
                    'unit_price' => 0, 'line_total' => 0, 'notes' => $it['notes'] ?? null]);
                $ingTotal = 0;
                $jn = 0;
                foreach ($this->validator->cleanIngredients($it['ingredients'] ?? []) as $g) {
                    $prod = $this->products->findOrFail((int) $g['product_id']);
                    $gqty = (float) $g['qty'];
                    $gprice = isset($g['unit_price']) && $g['unit_price'] !== '' ? (float) $g['unit_price'] : (float) $prod['selling_price'];
                    $this->repo->insertCompound(['prescription_item_id' => $itemId, 'line_no' => ++$jn, 'product_id' => (int) $prod['id'], 'qty' => $gqty, 'unit_price' => $gprice, 'notes' => $g['notes'] ?? null]);
                    $ingTotal += $gqty * $gprice;
                }
                $this->repo->updateItem($itemId, ['line_total' => round($ingTotal, 2)]);
                $subtotal += $ingTotal;
            }
        }
        return $subtotal;
    }

    /** Expand semua item (obat jadi + bahan racikan) menjadi alokasi stok FEFO siap-ISSUE. */
    private function allocationLines(int $prescriptionId, int $warehouseId): array
    {
        $out = [];
        $byItem = [];
        foreach ($this->repo->compounds($prescriptionId) as $c) {
            $byItem[(int) $c['prescription_item_id']][] = $c;
        }
        foreach ($this->repo->items($prescriptionId) as $it) {
            if ($it['item_type'] === 'COMPOUND') {
                foreach ($byItem[(int) $it['id']] ?? [] as $g) {
                    $out = array_merge($out, $this->allocate((int) $g['product_id'], (float) $g['qty'], (float) $g['unit_price'], $warehouseId, 'Racikan: ' . $it['compound_name']));
                }
            } else {
                $out = array_merge($out, $this->allocate((int) $it['product_id'], (float) $it['qty'], (float) $it['unit_price'], $warehouseId, $it['signa']));
            }
        }
        return $out;
    }

    private function allocate(int $productId, float $qty, float $price, int $warehouseId, ?string $notes): array
    {
        $product = $this->products->findOrFail($productId);
        $uom = $product['base_uom_id'] ?? null;
        if (!$product['is_batch_tracked']) {
            return [['product_id' => $productId, 'batch_id' => null, 'uom_id' => $uom, 'qty' => $qty, 'price' => $price, 'notes' => $notes]];
        }
        $r = $this->inventory->allocateFefo($productId, $warehouseId, $qty);
        if ($r['shortage'] > 0) {
            throw new Insufficient_stock_exception(sprintf('Stok %s tidak cukup (kurang %s)', $product['name'], $r['shortage']));
        }
        $lines = [];
        foreach ($r['allocations'] as $a) {
            $lines[] = ['product_id' => $productId, 'batch_id' => (int) $a['batch_id'], 'uom_id' => $uom, 'qty' => (float) $a['qty'], 'price' => $price, 'notes' => $notes];
        }
        return $lines;
    }

    /** Skrining apoteker: obat wajib resep menuntut nama dokter penulis. */
    private function screen(int $id, array $rx): void
    {
        if (trim((string) $rx['doctor_name']) !== '') {
            return;
        }
        $ids = [];
        foreach ($this->repo->items($id) as $it) {
            if ($it['product_id']) {
                $ids[(int) $it['product_id']] = true;
            }
        }
        foreach ($this->repo->compounds($id) as $c) {
            $ids[(int) $c['product_id']] = true;
        }
        foreach (array_keys($ids) as $pid) {
            $p = $this->products->find($pid);
            if ($p && $p['requires_prescription']) {
                throw new Validation_exception(['doctor_name' => 'Resep memuat obat keras/wajib resep — nama dokter penulis wajib diisi sebelum verifikasi']);
            }
        }
    }

    private function cleanPayments(array $payments): array
    {
        return array_values(array_filter($payments, fn($p) => is_array($p) && isset($p['amount']) && (float) $p['amount'] > 0));
    }

    private function warehouse(int $id): array
    {
        $wh = $this->db->ci->select('w.id, w.branch_id, b.code AS branch_code')->from('warehouses w')->join('branches b', 'b.id = w.branch_id')
            ->where(['w.id' => $id, 'w.company_id' => $this->ctx->company_id, 'w.is_active' => 1])->get()->row_array();
        if (!$wh) {
            throw new Validation_exception(['warehouse_id' => 'Gudang/Etalase tidak valid']);
        }
        return $wh;
    }
}
