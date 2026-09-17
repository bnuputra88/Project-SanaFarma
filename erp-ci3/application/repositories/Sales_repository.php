<?php
class Sales_repository extends Base_repository
{
    protected $table = 'sales';

    public function paginate(array $input, int $companyId): Paginator
    {
        $qb = $this->db->select('s.id, s.sale_no, s.sale_datetime, s.grand_total, s.paid_total, s.change_amount, s.status, s.sale_type, c.name AS customer_name, u.full_name AS cashier_name', false)
            ->from('sales s')->join('customers c', 'c.id = s.customer_id', 'left')->join('users u', 'u.id = s.cashier_id', 'left')->where('s.company_id', $companyId);
        if (!empty($input['status'])) {
            $qb->where('s.status', $input['status']);
        }
        if (!empty($input['q'])) {
            $qb->like('s.sale_no', $input['q']);
        }
        if (!empty($input['shift_id'])) {
            $qb->where('s.shift_id', (int) $input['shift_id']);
        }
        return $this->paginateQuery($qb, $input, ['sale_datetime' => 's.sale_datetime', 'sale_no' => 's.sale_no', 'grand_total' => 's.grand_total'], 's.id DESC');
    }

    public function items(int $saleId): array
    {
        return $this->db->select('i.*, p.sku, p.name AS product_name, b.batch_no, b.expiry_date, u.code AS uom_code')->from('sale_items i')
            ->join('products p', 'p.id = i.product_id')->join('batches b', 'b.id = i.batch_id', 'left')->join('uoms u', 'u.id = i.uom_id', 'left')
            ->where('i.sale_id', $saleId)->order_by('i.line_no')->get()->result_array();
    }

    public function payments(int $saleId): array
    {
        return $this->db->select('id, line_no, method, amount, reference, paid_at')->from('sale_payments')->where('sale_id', $saleId)->order_by('line_no')->get()->result_array();
    }

    public function replaceItems(int $saleId, array $rows): void
    {
        $this->db->where('sale_id', $saleId)->delete('sale_items');
        foreach ($rows as &$r) {
            $r['sale_id'] = $saleId;
        }
        unset($r);
        if ($rows) {
            $this->db->insert_batch('sale_items', $rows);
        }
    }

    public function replacePayments(int $saleId, array $rows): void
    {
        $this->db->where('sale_id', $saleId)->delete('sale_payments');
        foreach ($rows as &$r) {
            $r['sale_id'] = $saleId;
        }
        unset($r);
        if ($rows) {
            $this->db->insert_batch('sale_payments', $rows);
        }
    }

    public function updateItem(int $itemId, array $data): void
    {
        $this->db->where('id', $itemId)->update('sale_items', $data);
    }
}
