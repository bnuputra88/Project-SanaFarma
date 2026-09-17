<?php
class Batch_repository extends Base_repository
{
    protected $table = 'batches';
    protected $columns = ['id', 'company_id', 'product_id', 'batch_no', 'manufacture_date', 'expiry_date', 'supplier_id', 'source_ref_type', 'source_ref_id', 'source_ref_no',
        'received_at', 'unit_cost', 'status', 'notes', 'created_by', 'created_at', 'updated_at'];

    public function findOrCreate(int $companyId, int $productId, string $batchNo, array $attrs, ?int $userId): array
    {
        $row = $this->findBy(['product_id' => $productId, 'batch_no' => $batchNo]);
        if ($row) {
            return $row;
        }
        $id = $this->insert(['company_id' => $companyId, 'product_id' => $productId, 'batch_no' => $batchNo, 'manufacture_date' => $attrs['manufacture_date'] ?? null,
            'expiry_date' => $attrs['expiry_date'] ?? null, 'supplier_id' => $attrs['supplier_id'] ?? null, 'source_ref_type' => $attrs['source_ref_type'] ?? null,
            'source_ref_id' => $attrs['source_ref_id'] ?? null, 'source_ref_no' => $attrs['source_ref_no'] ?? null, 'received_at' => $attrs['received_at'] ?? date('Y-m-d H:i:s'),
            'unit_cost' => $attrs['unit_cost'] ?? 0, 'notes' => $attrs['notes'] ?? null, 'created_by' => $userId]);
        return $this->findOrFail($id);
    }

    public function paginate(array $input, int $companyId): Paginator
    {
        $qb = $this->db->select('b.id, b.batch_no, b.manufacture_date, b.expiry_date, b.unit_cost, b.status, b.received_at, b.source_ref_no, p.sku, p.name AS product_name,
                COALESCE(SUM(sb.qty_on_hand),0) AS qty_on_hand, DATEDIFF(b.expiry_date, CURDATE()) AS days_to_expiry', false)
            ->from('batches b')->join('products p', 'p.id = b.product_id')->join('stock_balances sb', 'sb.batch_id = b.id', 'left')
            ->where('b.company_id', $companyId)->group_by('b.id');
        if (!empty($input['q'])) {
            $qb->group_start()->like('b.batch_no', $input['q'])->or_like('p.name', $input['q'])->or_like('p.sku', $input['q'])->group_end();
        }
        if (!empty($input['status'])) {
            $qb->where('b.status', $input['status']);
        }
        if (!empty($input['expiring_days'])) {
            $qb->where('b.expiry_date <=', date('Y-m-d', strtotime('+' . (int) $input['expiring_days'] . ' days')));
        }
        if (!empty($input['with_stock'])) {
            $qb->having('qty_on_hand > 0');
        }
        return $this->paginateQuery($qb, $input, ['batch_no' => 'b.batch_no', 'expiry_date' => 'b.expiry_date', 'product_name' => 'p.name'], 'b.expiry_date');
    }

    public function forProduct(int $productId): array
    {
        return $this->db->select('id, batch_no, expiry_date, unit_cost, status')->from($this->table)->where('product_id', $productId)->order_by('expiry_date')->get()->result_array();
    }

    public function expirySummary(int $companyId, int $nearDays): array
    {
        return $this->db->query('SELECT
            SUM(CASE WHEN b.expiry_date < CURDATE() THEN sb.qty_on_hand ELSE 0 END) AS expired_qty,
            SUM(CASE WHEN b.expiry_date < CURDATE() THEN sb.qty_on_hand * sb.avg_cost ELSE 0 END) AS expired_value,
            SUM(CASE WHEN b.expiry_date >= CURDATE() AND b.expiry_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY) THEN sb.qty_on_hand ELSE 0 END) AS near_qty,
            SUM(CASE WHEN b.expiry_date >= CURDATE() AND b.expiry_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY) THEN sb.qty_on_hand * sb.avg_cost ELSE 0 END) AS near_value
            FROM stock_balances sb JOIN batches b ON b.id = sb.batch_id WHERE b.company_id = ? AND sb.qty_on_hand > 0', [$nearDays, $nearDays, $companyId])->row_array();
    }
}
