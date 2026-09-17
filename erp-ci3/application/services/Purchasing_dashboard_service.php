<?php
/** Agregasi ringkasan modul pembelian untuk dashboard. Read-only. */
class Purchasing_dashboard_service
{
    private $ctx;
    private $db;

    public function __construct(Request_context $ctx, Db $db)
    {
        $this->ctx = $ctx;
        $this->db = $db;
    }

    public function summary(): array
    {
        $c = $this->ctx->company_id;
        $ci = $this->db->ci;
        $prPending = (int) $ci->from('purchase_requests')->where('company_id', $c)->where_in('status', ['SUBMITTED'])->count_all_results();
        $poOpen = (int) $ci->from('purchase_orders')->where('company_id', $c)->where_in('status', ['ORDERED', 'PARTIAL'])->count_all_results();
        $grPending = (int) $ci->from('goods_receipts')->where('company_id', $c)->where_in('status', ['DRAFT', 'SUBMITTED', 'APPROVED'])->count_all_results();
        $apOpen = $ci->select('COUNT(*) AS cnt, COALESCE(SUM(grand_total - amount_paid),0) AS outstanding', false)->from('ap_invoices')->where('company_id', $c)->where_in('status', ['OPEN', 'PARTIAL'])->get()->row_array();

        $openPoLines = $ci->select('po.po_no, po.id, s.name AS supplier_name, p.sku, p.name AS product_name, (i.qty_ordered - i.qty_received) AS outstanding', false)
            ->from('purchase_order_items i')->join('purchase_orders po', 'po.id = i.po_id')->join('suppliers s', 's.id = po.supplier_id', 'left')->join('products p', 'p.id = i.product_id')
            ->where('po.company_id', $c)->where_in('po.status', ['ORDERED', 'PARTIAL'])->where('(i.qty_ordered - i.qty_received) >', 0, false)->order_by('po.order_date')->limit(20)->get()->result_array();

        $variances = $ci->select('h.effective_date, h.price, h.po_price, h.source_ref_no, s.name AS supplier_name, p.sku, p.name AS product_name', false)
            ->from('supplier_price_history h')->join('suppliers s', 's.id = h.supplier_id')->join('products p', 'p.id = h.product_id')
            ->where('h.company_id', $c)->where('h.po_price IS NOT NULL', null, false)->where('h.po_price <> h.price', null, false)->order_by('h.id DESC')->limit(15)->get()->result_array();

        $grInspect = $ci->select('gr.id, gr.gr_no, gr.receipt_date, gr.status, s.name AS supplier_name', false)
            ->from('goods_receipts gr')->join('suppliers s', 's.id = gr.supplier_id', 'left')->where('gr.company_id', $c)->where_in('gr.status', ['SUBMITTED', 'APPROVED'])->order_by('gr.receipt_date')->limit(15)->get()->result_array();

        return ['pr_pending' => $prPending, 'po_open' => $poOpen, 'gr_pending' => $grPending, 'ap_open_count' => (int) $apOpen['cnt'], 'ap_outstanding' => (float) $apOpen['outstanding'],
            'open_po_lines' => $openPoLines, 'variances' => $variances, 'gr_inspect' => $grInspect];
    }
}
