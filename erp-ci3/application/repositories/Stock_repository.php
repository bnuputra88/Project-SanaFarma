<?php
/**
 * Stock balances + immutable ledger + movements. Row locking via SELECT ... FOR UPDATE.
 */
class Stock_repository extends Base_repository
{
    protected $table = 'stock_balances';
    protected $columns = ['id', 'warehouse_id', 'location_id', 'product_id', 'batch_id', 'condition_code', 'qty_on_hand', 'qty_reserved', 'qty_in_transit', 'avg_cost', 'version', 'updated_at'];

    /** Lock (or create+lock) the balance row for a unique stock key. MUST be inside a transaction. */
    public function lockBalance(int $warehouseId, ?int $locationId, int $productId, ?int $batchId, string $condition): array
    {
        $where = ['warehouse_id' => $warehouseId, 'product_id' => $productId, 'condition_code' => $condition];
        $qb = $this->db->select($this->columns)->from($this->table)->where($where);
        $qb = $locationId === null ? $qb->where('location_id IS NULL', null, false) : $qb->where('location_id', $locationId);
        $qb = $batchId === null ? $qb->where('batch_id IS NULL', null, false) : $qb->where('batch_id', $batchId);
        $sql = $qb->get_compiled_select('', false) . ' FOR UPDATE';
        $this->db->reset_query();
        $row = $this->db->query($sql)->row_array();
        if (!$row) {
            $this->db->query('INSERT IGNORE INTO stock_balances (warehouse_id, location_id, product_id, batch_id, condition_code) VALUES (?,?,?,?,?)',
                [$warehouseId, $locationId, $productId, $batchId, $condition]);
            $row = $this->db->query($sql)->row_array();
        }
        return $row;
    }

    public function applyBalance(int $id, float $newOnHand, float $newAvgCost, float $reservedDelta = 0, float $inTransitDelta = 0): void
    {
        $this->db->set('qty_on_hand', $newOnHand)->set('avg_cost', $newAvgCost)->set('version', 'version + 1', false)
            ->set('qty_reserved', 'qty_reserved + (' . (float) $reservedDelta . ')', false)
            ->set('qty_in_transit', 'qty_in_transit + (' . (float) $inTransitDelta . ')', false)
            ->where('id', $id)->update($this->table);
    }

    public function insertMovement(array $header): int
    {
        $this->db->insert('stock_movements', $header);
        return (int) $this->db->insert_id();
    }

    public function insertMovementItem(array $item): int
    {
        $this->db->insert('stock_movement_items', $item);
        return (int) $this->db->insert_id();
    }

    public function insertLedger(array $row): void
    {
        $this->db->insert('stock_ledger', $row);
    }

    public function markReversed(int $movementId, int $reversalId): void
    {
        $this->db->where(['id' => $movementId, 'status' => 'POSTED'])->update('stock_movements', ['status' => 'REVERSED', 'reversed_by_id' => $reversalId]);
        if ($this->db->affected_rows() !== 1) {
            throw new Conflict_exception('Mutasi sudah dibalik sebelumnya');
        }
    }

    public function movement(int $id, bool $forUpdate = false): ?array
    {
        $sql = $this->db->select('m.id, m.company_id, m.branch_id, m.movement_no, m.movement_type, m.ref_type, m.ref_id, m.ref_no, m.status, m.reversal_of_id, m.reversed_by_id,
            m.reason_code_id, m.notes, m.posted_by, m.posted_at, u.full_name AS posted_by_name')->from('stock_movements m')->join('users u', 'u.id = m.posted_by', 'left')
            ->where('m.id', $id)->get_compiled_select() . ($forUpdate ? ' FOR UPDATE' : '');
        $row = $this->db->query($sql)->row_array();
        return $row ?: null;
    }

    public function movementItems(int $movementId): array
    {
        return $this->db->select('i.id, i.line_no, i.warehouse_id, i.location_id, i.product_id, i.batch_id, i.condition_code, i.qty, i.unit_cost,
            p.sku, p.name AS product_name, b.batch_no, b.expiry_date, w.name AS warehouse_name, l.code AS location_code')
            ->from('stock_movement_items i')->join('products p', 'p.id = i.product_id')->join('batches b', 'b.id = i.batch_id', 'left')
            ->join('warehouses w', 'w.id = i.warehouse_id')->join('locations l', 'l.id = i.location_id', 'left')
            ->where('i.movement_id', $movementId)->order_by('i.line_no')->get()->result_array();
    }

    public function movements(array $input, int $companyId): Paginator
    {
        $qb = $this->db->select('m.id, m.movement_no, m.movement_type, m.ref_type, m.ref_no, m.status, m.posted_at, u.full_name AS posted_by_name,
            (SELECT COUNT(*) FROM stock_movement_items i WHERE i.movement_id = m.id) AS line_count', false)
            ->from('stock_movements m')->join('users u', 'u.id = m.posted_by', 'left')->where('m.company_id', $companyId);
        if (!empty($input['movement_type'])) {
            $qb->where('m.movement_type', $input['movement_type']);
        }
        if (!empty($input['q'])) {
            $qb->group_start()->like('m.movement_no', $input['q'])->or_like('m.ref_no', $input['q'])->group_end();
        }
        if (!empty($input['date_from'])) {
            $qb->where('m.posted_at >=', $input['date_from'] . ' 00:00:00');
        }
        if (!empty($input['date_to'])) {
            $qb->where('m.posted_at <=', $input['date_to'] . ' 23:59:59');
        }
        return $this->paginateQuery($qb, $input, ['posted_at' => 'm.posted_at', 'movement_no' => 'm.movement_no'], 'm.id DESC');
    }

    /** FEFO candidates: GOOD, non-quarantined batches with available qty in a warehouse. */
    public function fefoCandidates(int $productId, int $warehouseId): array
    {
        return $this->db->select('sb.id AS balance_id, sb.batch_id, sb.location_id, b.expiry_date, b.batch_no, sb.avg_cost AS unit_cost, (sb.qty_on_hand - sb.qty_reserved) AS available', false)
            ->from('stock_balances sb')->join('batches b', 'b.id = sb.batch_id', 'left')
            ->where(['sb.product_id' => $productId, 'sb.warehouse_id' => $warehouseId, 'sb.condition_code' => 'GOOD'])
            ->where('(sb.qty_on_hand - sb.qty_reserved) >', 0, false)
            ->group_start()->where('b.id IS NULL', null, false)->or_where('b.status', 'ACTIVE')->group_end()
            ->get()->result_array();
    }

    public function balances(array $input, int $companyId): Paginator
    {
        $qb = $this->db->select('sb.id, sb.qty_on_hand, sb.qty_reserved, sb.qty_in_transit, sb.avg_cost, sb.condition_code, (sb.qty_on_hand - sb.qty_reserved) AS available,
                p.id AS product_id, p.sku, p.name AS product_name, p.min_stock, w.name AS warehouse_name, l.code AS location_code, b.batch_no, b.expiry_date, DATEDIFF(b.expiry_date, CURDATE()) AS days_to_expiry', false)
            ->from('stock_balances sb')->join('products p', 'p.id = sb.product_id')->join('warehouses w', 'w.id = sb.warehouse_id')
            ->join('locations l', 'l.id = sb.location_id', 'left')->join('batches b', 'b.id = sb.batch_id', 'left')->where('w.company_id', $companyId);
        if (empty($input['include_zero'])) {
            $qb->where('sb.qty_on_hand <>', 0);
        }
        if (!empty($input['q'])) {
            $qb->group_start()->like('p.name', $input['q'])->or_like('p.sku', $input['q'])->or_like('b.batch_no', $input['q'])->group_end();
        }
        if (!empty($input['warehouse_id'])) {
            $qb->where('sb.warehouse_id', (int) $input['warehouse_id']);
        }
        if (!empty($input['product_id'])) {
            $qb->where('sb.product_id', (int) $input['product_id']);
        }
        if (!empty($input['condition_code'])) {
            $qb->where('sb.condition_code', $input['condition_code']);
        }
        if (!empty($input['expiring_days'])) {
            $qb->where('b.expiry_date <=', date('Y-m-d', strtotime('+' . (int) $input['expiring_days'] . ' days')));
        }
        return $this->paginateQuery($qb, $input, ['product_name' => 'p.name', 'expiry_date' => 'b.expiry_date', 'qty_on_hand' => 'sb.qty_on_hand', 'warehouse_name' => 'w.name'], 'p.name, b.expiry_date');
    }

    public function balancesForWarehouse(int $warehouseId): array
    {
        return $this->db->select('id, location_id, product_id, batch_id, condition_code, qty_on_hand, avg_cost')->from($this->table)->where('warehouse_id', $warehouseId)->where('qty_on_hand <>', 0)->get()->result_array();
    }

    public function ledger(array $input, int $companyId): Paginator
    {
        $qb = $this->db->select('sl.id, sl.movement_type, sl.qty_in, sl.qty_out, sl.balance_after, sl.unit_cost, sl.total_cost, sl.ref_type, sl.ref_no, sl.posted_at, sl.condition_code,
                p.sku, p.name AS product_name, w.name AS warehouse_name, b.batch_no, b.expiry_date, m.movement_no, m.id AS movement_id, u.full_name AS posted_by_name')
            ->from('stock_ledger sl')->join('products p', 'p.id = sl.product_id')->join('warehouses w', 'w.id = sl.warehouse_id')->join('batches b', 'b.id = sl.batch_id', 'left')
            ->join('stock_movements m', 'm.id = sl.movement_id')->join('users u', 'u.id = sl.posted_by', 'left')->where('w.company_id', $companyId);
        if (!empty($input['product_id'])) {
            $qb->where('sl.product_id', (int) $input['product_id']);
        }
        if (!empty($input['warehouse_id'])) {
            $qb->where('sl.warehouse_id', (int) $input['warehouse_id']);
        }
        if (!empty($input['batch_id'])) {
            $qb->where('sl.batch_id', (int) $input['batch_id']);
        }
        if (!empty($input['date_from'])) {
            $qb->where('sl.posted_at >=', $input['date_from'] . ' 00:00:00');
        }
        if (!empty($input['date_to'])) {
            $qb->where('sl.posted_at <=', $input['date_to'] . ' 23:59:59');
        }
        return $this->paginateQuery($qb, $input, ['posted_at' => 'sl.posted_at'], 'sl.id DESC');
    }

    public function valuation(int $companyId): array
    {
        return $this->db->query('SELECT COALESCE(SUM(sb.qty_on_hand),0) AS total_qty, COALESCE(SUM(sb.qty_on_hand * sb.avg_cost),0) AS total_value,
            COUNT(DISTINCT sb.product_id) AS sku_count FROM stock_balances sb JOIN warehouses w ON w.id = sb.warehouse_id
            WHERE w.company_id = ? AND sb.condition_code = "GOOD" AND sb.qty_on_hand > 0', [$companyId])->row_array();
    }

    public function productAvgCost(int $productId, int $warehouseId): float
    {
        $r = $this->db->query('SELECT COALESCE(SUM(qty_on_hand * avg_cost) / NULLIF(SUM(qty_on_hand),0), 0) AS c FROM stock_balances WHERE product_id = ? AND warehouse_id = ? AND qty_on_hand > 0', [$productId, $warehouseId])->row();
        return (float) $r->c;
    }

    public function updateBatchStatus(int $batchId, string $status): void
    {
        $this->db->where('id', $batchId)->update('batches', ['status' => $status]);
    }
}
