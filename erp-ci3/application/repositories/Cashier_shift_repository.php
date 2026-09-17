<?php
class Cashier_shift_repository extends Base_repository
{
    protected $table = 'cashier_shifts';
    protected $columns = ['id', 'company_id', 'branch_id', 'warehouse_id', 'shift_no', 'cashier_id', 'opened_at', 'closed_at', 'opening_cash', 'closing_cash',
        'expected_cash', 'cash_variance', 'total_sales', 'sale_count', 'status', 'notes', 'version', 'created_at', 'updated_at'];

    public function openForCashier(int $cashierId): ?array
    {
        $row = $this->db->select($this->columns)->from($this->table)->where(['cashier_id' => $cashierId, 'status' => 'OPEN'])->order_by('id DESC')->limit(1)->get()->row_array();
        return $row ?: null;
    }

    public function paginate(array $input, int $companyId): Paginator
    {
        $qb = $this->db->select('cs.id, cs.shift_no, cs.opened_at, cs.closed_at, cs.opening_cash, cs.closing_cash, cs.total_sales, cs.sale_count, cs.status, u.full_name AS cashier_name, w.name AS warehouse_name', false)
            ->from('cashier_shifts cs')->join('users u', 'u.id = cs.cashier_id', 'left')->join('warehouses w', 'w.id = cs.warehouse_id', 'left')->where('cs.company_id', $companyId);
        if (!empty($input['status'])) {
            $qb->where('cs.status', $input['status']);
        }
        return $this->paginateQuery($qb, $input, ['opened_at' => 'cs.opened_at', 'shift_no' => 'cs.shift_no'], 'cs.id DESC');
    }

    public function totals(int $shiftId): array
    {
        return $this->db->query('SELECT COUNT(*) AS cnt, COALESCE(SUM(grand_total),0) AS total FROM sales WHERE shift_id = ? AND status = "PAID"', [$shiftId])->row_array();
    }

    public function cashCollected(int $shiftId): float
    {
        $r = $this->db->query('SELECT COALESCE(SUM(sp.amount),0) AS c FROM sale_payments sp JOIN sales s ON s.id = sp.sale_id WHERE s.shift_id = ? AND s.status = "PAID" AND sp.method = "CASH"', [$shiftId])->row();
        return (float) $r->c;
    }
}
