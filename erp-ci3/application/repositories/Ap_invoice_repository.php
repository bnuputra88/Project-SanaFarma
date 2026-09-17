<?php
class Ap_invoice_repository extends Base_repository
{
    protected $table = 'ap_invoices';
    protected $columns = ['id', 'company_id', 'branch_id', 'supplier_id', 'po_id', 'gr_id', 'ap_no', 'supplier_invoice_no', 'invoice_date', 'due_date',
        'subtotal', 'tax_total', 'grand_total', 'amount_paid', 'status', 'currency', 'notes', 'created_by', 'version', 'created_at', 'updated_at'];

    public function paginate(array $input, int $companyId): Paginator
    {
        $qb = $this->db->select('ap.id, ap.ap_no, ap.supplier_invoice_no, ap.invoice_date, ap.due_date, ap.grand_total, ap.amount_paid, ap.status, s.name AS supplier_name')
            ->from('ap_invoices ap')->join('suppliers s', 's.id = ap.supplier_id', 'left')->where('ap.company_id', $companyId);
        if (!empty($input['status'])) {
            $qb->where('ap.status', $input['status']);
        }
        if (!empty($input['supplier_id'])) {
            $qb->where('ap.supplier_id', (int) $input['supplier_id']);
        }
        if (!empty($input['q'])) {
            $qb->group_start()->like('ap.ap_no', $input['q'])->or_like('ap.supplier_invoice_no', $input['q'])->group_end();
        }
        return $this->paginateQuery($qb, $input, ['ap_no' => 'ap.ap_no', 'invoice_date' => 'ap.invoice_date', 'due_date' => 'ap.due_date', 'status' => 'ap.status'], 'ap.id DESC');
    }

    public function items(int $apId): array
    {
        return $this->db->select('i.*, p.sku, p.name AS product_name')->from('ap_invoice_items i')->join('products p', 'p.id = i.product_id', 'left')
            ->where('i.ap_id', $apId)->order_by('i.line_no')->get()->result_array();
    }

    public function replaceItems(int $apId, array $rows): void
    {
        $this->db->where('ap_id', $apId)->delete('ap_invoice_items');
        foreach ($rows as &$r) {
            $r['ap_id'] = $apId;
        }
        unset($r);
        if ($rows) {
            $this->db->insert_batch('ap_invoice_items', $rows);
        }
    }
}
