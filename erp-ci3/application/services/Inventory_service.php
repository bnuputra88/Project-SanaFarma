<?php
/**
 * Inventory engine — the single entry point for every stock mutation.
 * Guarantees: transactional, row-locked, immutable ledger, negative-stock prevention, FIFO/Average costing, FEFO allocation, reversal only.
 */
class Inventory_service
{
    private $stock;
    private $batches;
    private $warehouses;
    private $products;
    private $numbering;
    private $settings;
    private $audit;
    private $ctx;
    private $db;
    private $types;

    public function __construct(Stock_repository $stock, Batch_repository $batches, Warehouse_repository $warehouses, Product_repository $products, Numbering_service $numbering,
                                Setting_service $settings, Audit_service $audit, Request_context $ctx, Db $db)
    {
        $this->stock = $stock;
        $this->batches = $batches;
        $this->warehouses = $warehouses;
        $this->products = $products;
        $this->numbering = $numbering;
        $this->settings = $settings;
        $this->audit = $audit;
        $this->ctx = $ctx;
        $this->db = $db;
        $this->types = get_instance()->config->item('erp_movement_types');
    }

    /**
     * Post a stock movement.
     * $header: movement_type, ref_type, ref_id, ref_no, reason_code_id, notes, branch_id
     * $lines : [ ['warehouse_id','location_id','product_id','batch_id'|null,'batch_no','expiry_date','manufacture_date','condition_code','qty' (signed), 'unit_cost'] ]
     * @return int movement id
     */
    public function post(array $header, array $lines): int
    {
        if (!isset($this->types[$header['movement_type']])) {
            throw new Domain_exception('Tipe mutasi tidak dikenal: ' . $header['movement_type']);
        }
        if (!$lines) {
            throw new Domain_exception('Mutasi stok tanpa baris item');
        }
        return $this->db->transaction(function () use ($header, $lines) {
            $now = date('Y-m-d H:i:s.v');
            $movementId = $this->stock->insertMovement([
                'company_id' => $this->ctx->company_id, 'branch_id' => $header['branch_id'] ?? $this->ctx->branch_id,
                'movement_no' => $this->numbering->next('STOCK_MOV'), 'movement_type' => $header['movement_type'],
                'ref_type' => $header['ref_type'] ?? null, 'ref_id' => $header['ref_id'] ?? null, 'ref_no' => $header['ref_no'] ?? null,
                'reversal_of_id' => $header['reversal_of_id'] ?? null, 'reason_code_id' => $header['reason_code_id'] ?? null, 'notes' => $header['notes'] ?? null,
                'posted_by' => $this->ctx->user_id, 'posted_at' => $now,
            ]);
            // Deterministic lock order prevents deadlocks between concurrent postings
            usort($lines, fn($a, $b) => [$a['warehouse_id'], $a['product_id'], $a['batch_id'] ?? 0] <=> [$b['warehouse_id'], $b['product_id'], $b['batch_id'] ?? 0]);
            $lineNo = 0;
            foreach ($lines as $line) {
                $this->postLine($movementId, ++$lineNo, $header, $line, $now);
            }
            $this->audit->log('inventory', 'post_movement', 'stock_movements', $movementId, null, ['type' => $header['movement_type'], 'lines' => count($lines), 'ref' => $header['ref_no'] ?? null], $header['ref_no'] ?? null, $header['notes'] ?? null);
            return $movementId;
        });
    }

    private function postLine(int $movementId, int $lineNo, array $header, array $line, string $now): void
    {
        $product = $this->products->findOrFail((int) $line['product_id']);
        $warehouse = $this->warehouses->findOrFail((int) $line['warehouse_id']);
        $qty = (float) $line['qty'];
        if ($qty == 0) {
            throw new Domain_exception("Qty baris $lineNo tidak boleh nol");
        }
        $batchId = $this->resolveBatch($product, $line, $header);
        $locationId = isset($line['location_id']) && $line['location_id'] !== '' ? (int) $line['location_id'] : $this->warehouses->defaultLocation((int) $warehouse['id']);
        $condition = $line['condition_code'] ?? 'GOOD';

        $bal = $this->stock->lockBalance((int) $warehouse['id'], $locationId, (int) $product['id'], $batchId, $condition);
        $onHand = (float) $bal['qty_on_hand'];
        $newOnHand = $onHand + $qty;

        if ($qty < 0) {
            $available = $onHand - (float) $bal['qty_reserved'] + (float) ($line['consume_reserved'] ?? 0);
            $allowNegative = $warehouse['allow_negative_stock'] || $this->settings->get('inventory.allow_negative_stock', false);
            if (!$allowNegative && $available + $qty < -0.000001) {
                throw new Insufficient_stock_exception(sprintf('Stok tidak cukup untuk %s (%s) batch %s: tersedia %s, diminta %s', $product['name'], $product['sku'], $line['batch_no'] ?? '-', rtrim(rtrim(number_format($available, 4, '.', ''), '0'), '.'), abs($qty)));
            }
            if (!$allowNegative && $this->isExpired($batchId) && empty($line['allow_expired'])) {
                throw new Domain_exception('Batch kedaluwarsa tidak dapat dikeluarkan sebagai stok baik: ' . ($line['batch_no'] ?? $batchId));
            }
        }

        // Costing: FIFO = batch cost layer (batch-level tracking is a natural FIFO layer); AVERAGE = weighted moving average on the balance row
        $method = $this->settings->get('inventory.costing_method', 'FIFO');
        $inCost = isset($line['unit_cost']) && $line['unit_cost'] !== '' ? (float) $line['unit_cost'] : (float) $bal['avg_cost'];
        if ($qty > 0) {
            $newAvg = $method === 'AVERAGE' && $onHand > 0 ? (($onHand * (float) $bal['avg_cost']) + ($qty * $inCost)) / $newOnHand : $inCost;
            $unitCost = $inCost;
        } else {
            $unitCost = (float) $bal['avg_cost'] ?: $inCost;
            $newAvg = (float) $bal['avg_cost'];
        }
        $this->stock->applyBalance((int) $bal['id'], $newOnHand, $newAvg, -(float) ($line['consume_reserved'] ?? 0), (float) ($line['in_transit_delta'] ?? 0));

        $itemId = $this->stock->insertMovementItem(['movement_id' => $movementId, 'line_no' => $lineNo, 'warehouse_id' => (int) $warehouse['id'], 'location_id' => $locationId,
            'product_id' => (int) $product['id'], 'batch_id' => $batchId, 'condition_code' => $condition, 'qty' => $qty, 'unit_cost' => $unitCost]);
        $this->stock->insertLedger(['movement_id' => $movementId, 'movement_item_id' => $itemId, 'movement_type' => $header['movement_type'], 'warehouse_id' => (int) $warehouse['id'],
            'location_id' => $locationId, 'product_id' => (int) $product['id'], 'batch_id' => $batchId, 'condition_code' => $condition,
            'qty_in' => $qty > 0 ? $qty : 0, 'qty_out' => $qty < 0 ? -$qty : 0, 'balance_after' => $newOnHand, 'unit_cost' => $unitCost, 'total_cost' => round($qty * $unitCost, 4),
            'ref_type' => $header['ref_type'] ?? null, 'ref_id' => $header['ref_id'] ?? null, 'ref_no' => $header['ref_no'] ?? null, 'posted_by' => $this->ctx->user_id, 'posted_at' => $now]);
    }

    private function resolveBatch(array $product, array $line, array $header): ?int
    {
        if (!$product['is_batch_tracked']) {
            return null;
        }
        if (!empty($line['batch_id'])) {
            return (int) $line['batch_id'];
        }
        if (empty($line['batch_no'])) {
            throw new Validation_exception(['batch_no' => "Produk {$product['sku']} wajib memiliki nomor batch"]);
        }
        if ($product['is_expiry_tracked'] && empty($line['expiry_date'])) {
            throw new Validation_exception(['expiry_date' => "Batch {$line['batch_no']} ({$product['sku']}) wajib memiliki tanggal kedaluwarsa"]);
        }
        $batch = $this->batches->findOrCreate($this->ctx->company_id, (int) $product['id'], trim($line['batch_no']), [
            'expiry_date' => $line['expiry_date'] ?? null, 'manufacture_date' => $line['manufacture_date'] ?? null, 'supplier_id' => $line['supplier_id'] ?? null,
            'source_ref_type' => $header['ref_type'] ?? null, 'source_ref_id' => $header['ref_id'] ?? null, 'source_ref_no' => $header['ref_no'] ?? null, 'unit_cost' => $line['unit_cost'] ?? 0,
        ], $this->ctx->user_id);
        return (int) $batch['id'];
    }

    private function isExpired(?int $batchId): bool
    {
        if (!$batchId) {
            return false;
        }
        $b = $this->batches->find($batchId);
        return $b && $b['expiry_date'] && $b['expiry_date'] < date('Y-m-d');
    }

    /** Compensating (reversal) movement — original rows are never modified. */
    public function reverse(int $movementId, string $reason, ?int $reasonCodeId = null): int
    {
        if (trim($reason) === '') {
            throw new Validation_exception(['reason' => 'Alasan pembalikan wajib diisi']);
        }
        return $this->db->transaction(function () use ($movementId, $reason, $reasonCodeId) {
            $m = $this->stock->movement($movementId, true);
            if (!$m || (int) $m['company_id'] !== $this->ctx->company_id) {
                throw new Not_found_exception();
            }
            if ($m['status'] !== 'POSTED') {
                throw new Conflict_exception('Mutasi sudah dibalik');
            }
            $lines = array_map(fn($i) => ['warehouse_id' => $i['warehouse_id'], 'location_id' => $i['location_id'], 'product_id' => $i['product_id'], 'batch_id' => $i['batch_id'],
                'batch_no' => $i['batch_no'], 'condition_code' => $i['condition_code'], 'qty' => -(float) $i['qty'], 'unit_cost' => $i['unit_cost'], 'allow_expired' => true], $this->stock->movementItems($movementId));
            $revId = $this->post(['movement_type' => 'REVERSAL', 'ref_type' => 'stock_movements', 'ref_id' => $movementId, 'ref_no' => $m['movement_no'], 'reversal_of_id' => $movementId,
                'reason_code_id' => $reasonCodeId, 'notes' => $reason, 'branch_id' => $m['branch_id']], $lines);
            $this->stock->markReversed($movementId, $revId);
            $this->audit->log('inventory', 'reverse_movement', 'stock_movements', $movementId, ['status' => 'POSTED'], ['status' => 'REVERSED', 'reversal_id' => $revId], $m['movement_no'], $reason);
            return $revId;
        });
    }

    /** FEFO allocation preview (no side effects). Consumers (sales/dispensing/picking) call this then post ISSUE lines. */
    public function allocateFefo(int $productId, int $warehouseId, float $qty): array
    {
        $allowExpired = (bool) $this->settings->get('inventory.fefo_allow_expired', false);
        $candidates = $this->stock->fefoCandidates($productId, $warehouseId);
        $result = Fefo_allocator::allocate($candidates, $qty, date('Y-m-d'), $allowExpired);
        $byId = array_column($candidates, null, 'batch_id');
        foreach ($result['allocations'] as &$a) {
            $a['batch_no'] = $byId[$a['batch_id']]['batch_no'] ?? null;
            $a['balance_id'] = (int) ($byId[$a['batch_id']]['balance_id'] ?? 0);
        }
        return $result;
    }

    /** Move a batch between GOOD and QUARANTINE condition (same location) — used by quality/recall. */
    public function setBatchCondition(int $batchId, string $toCondition, string $reason): int
    {
        if (!in_array($toCondition, ['GOOD', 'QUARANTINE'], true)) {
            throw new Domain_exception('Kondisi tujuan tidak valid');
        }
        return $this->db->transaction(function () use ($batchId, $toCondition, $reason) {
            $batch = $this->batches->findOrFail($batchId, true);
            $from = $toCondition === 'GOOD' ? 'QUARANTINE' : 'GOOD';
            $rows = array_filter($this->stock->all(['batch_id' => $batchId, 'condition_code' => $from]), fn($r) => (float) $r['qty_on_hand'] > 0);
            if (!$rows) {
                throw new Domain_exception('Tidak ada stok batch pada kondisi ' . $from);
            }
            $lines = [];
            foreach ($rows as $r) {
                $base = ['warehouse_id' => $r['warehouse_id'], 'location_id' => $r['location_id'], 'product_id' => $r['product_id'], 'batch_id' => $batchId, 'unit_cost' => $r['avg_cost'], 'allow_expired' => true];
                $lines[] = $base + ['condition_code' => $from, 'qty' => -(float) $r['qty_on_hand']];
                $lines[] = $base + ['condition_code' => $toCondition, 'qty' => (float) $r['qty_on_hand']];
            }
            $mid = $this->post(['movement_type' => $toCondition === 'QUARANTINE' ? 'QUARANTINE_IN' : 'QUARANTINE_OUT', 'ref_type' => 'batches', 'ref_id' => $batchId, 'ref_no' => $batch['batch_no'], 'notes' => $reason], $lines);
            $this->stock->updateBatchStatus($batchId, $toCondition === 'QUARANTINE' ? 'QUARANTINE' : 'ACTIVE');
            $this->audit->log('inventory', 'batch_condition', 'batches', $batchId, ['status' => $batch['status']], ['status' => $toCondition], $batch['batch_no'], $reason);
            return $mid;
        });
    }
}
