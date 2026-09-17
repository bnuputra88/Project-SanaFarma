<?php
class Dashboard_service
{
    private $stock;
    private $batches;
    private $products;
    private $settings;
    private $ctx;
    private $db;

    public function __construct(Stock_repository $stock, Batch_repository $batches, Product_repository $products, Setting_service $settings, Request_context $ctx, Db $db)
    {
        $this->stock = $stock;
        $this->batches = $batches;
        $this->products = $products;
        $this->settings = $settings;
        $this->ctx = $ctx;
        $this->db = $db;
    }

    public function kpis(): array
    {
        $nearDays = (int) $this->settings->get('inventory.near_expiry_days', 90);
        $cid = $this->ctx->company_id;
        return [
            'valuation' => $this->stock->valuation($cid),
            'expiry' => $this->batches->expirySummary($cid, $nearDays),
            'near_expiry_days' => $nearDays,
            'low_stock' => $this->products->lowStock($cid, 8),
            'pending' => [
                'adjustments' => (int) $this->db->ci->from('stock_adjustments')->where(['company_id' => $cid, 'status' => 'SUBMITTED'])->count_all_results(),
                'transfers' => (int) $this->db->ci->from('stock_transfers')->where('company_id', $cid)->where_in('status', ['SUBMITTED', 'IN_TRANSIT'])->count_all_results(),
                'opnames' => (int) $this->db->ci->from('stock_opnames')->where('company_id', $cid)->where_in('status', ['COUNTING', 'SUBMITTED'])->count_all_results(),
            ],
            'movements_today' => (int) $this->db->ci->from('stock_movements')->where('company_id', $cid)->where('posted_at >=', date('Y-m-d 00:00:00'))->count_all_results(),
            'recent_movements' => $this->stock->movements(['per_page' => 8], $cid)->items,
        ];
    }
}
