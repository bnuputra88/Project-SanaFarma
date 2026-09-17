<?php
/** Header+items retur penjualan (sama pola stock/purchase doc). */
class Sales_return_repository extends Base_repository
{
    protected $table = 'sales_returns';

    public function paginate(array $input, int $companyId): Paginator
    {
        $qb = $this->db->select('sr.id, sr.return_no AS doc_no, sr.return_date AS doc_date, sr.status, sr.notes, sr.total_value, sr.created_at, c.name AS customer_name, u.full_name AS created_by_name', false)
            ->from('sales_returns sr')->join('customers c', 'c.id = sr.customer_id', 'left')->join('users u', 'u.id = sr.created_by', 'left')->where('sr.company_id', $companyId);
        if (!empty($input['status'])) {
            $qb->where('sr.status', $input['status']);
        }
        if (!empty($input['q'])) {
            $qb->like('sr.return_no', $input['q']);
        }
        return $this->paginateQuery($qb, $input, ['doc_no' => 'sr.return_no', 'doc_date' => 'sr.return_date', 'status' => 'sr.status'], 'sr.id DESC');
    }

    public function items(int $returnId): array
    {
        return $this->db->select('i.*, p.sku, p.name AS product_name, b.batch_no, b.expiry_date')->from('sales_return_items i')
            ->join('products p', 'p.id = i.product_id')->join('batches b', 'b.id = i.batch_id', 'left')->where('i.return_id', $returnId)->order_by('i.line_no')->get()->result_array();
    }

    public function replaceItems(int $returnId, array $rows): void
    {
        $this->db->where('return_id', $returnId)->delete('sales_return_items');
        foreach ($rows as &$r) {
            $r['return_id'] = $returnId;
        }
        unset($r);
        if ($rows) {
            $this->db->insert_batch('sales_return_items', $rows);
        }
    }
}
