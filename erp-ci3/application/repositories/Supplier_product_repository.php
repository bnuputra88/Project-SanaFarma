<?php
/** Katalog produk per supplier + riwayat harga beli (append-only). */
class Supplier_product_repository extends Base_repository
{
    protected $table = 'supplier_products';
    protected $columns = ['id', 'company_id', 'supplier_id', 'product_id', 'supplier_sku', 'supplier_product_name', 'last_price', 'currency',
        'min_order_qty', 'lead_time_days', 'is_preferred', 'is_active', 'last_purchased_at', 'created_at', 'updated_at'];

    public function forSupplier(int $supplierId): array
    {
        return $this->db->select('sp.id, sp.product_id, sp.supplier_sku, sp.last_price, sp.currency, sp.min_order_qty, sp.lead_time_days, sp.is_preferred, sp.is_active, sp.last_purchased_at, p.sku, p.name AS product_name')
            ->from('supplier_products sp')->join('products p', 'p.id = sp.product_id')->where('sp.supplier_id', $supplierId)->order_by('p.name')->get()->result_array();
    }

    public function preferredSupplier(int $productId): ?array
    {
        $row = $this->db->select('sp.supplier_id, s.name AS supplier_name, sp.last_price, sp.lead_time_days')->from('supplier_products sp')->join('suppliers s', 's.id = sp.supplier_id')
            ->where(['sp.product_id' => $productId, 'sp.is_active' => 1])->order_by('sp.is_preferred DESC, sp.last_price ASC')->limit(1)->get()->row_array();
        return $row ?: null;
    }

    /** Upsert katalog + rekam harga terbaru. */
    public function recordPrice(int $companyId, int $supplierId, int $productId, float $price, array $source): void
    {
        $existing = $this->findBy(['supplier_id' => $supplierId, 'product_id' => $productId]);
        if ($existing) {
            $this->db->where('id', $existing['id'])->update($this->table, ['last_price' => $price, 'last_purchased_at' => date('Y-m-d H:i:s')]);
        } else {
            $this->insert(['company_id' => $companyId, 'supplier_id' => $supplierId, 'product_id' => $productId, 'last_price' => $price, 'last_purchased_at' => date('Y-m-d H:i:s')]);
        }
        $this->db->insert('supplier_price_history', ['company_id' => $companyId, 'supplier_id' => $supplierId, 'product_id' => $productId, 'price' => $price,
            'source_ref_type' => $source['ref_type'] ?? null, 'source_ref_id' => $source['ref_id'] ?? null, 'source_ref_no' => $source['ref_no'] ?? null,
            'po_price' => $source['po_price'] ?? null, 'effective_date' => $source['date'] ?? date('Y-m-d'), 'created_by' => $source['actor'] ?? null]);
    }

    public function priceHistory(int $supplierId, ?int $productId = null, int $limit = 100): array
    {
        $qb = $this->db->select('h.id, h.product_id, h.price, h.po_price, h.currency, h.source_ref_type, h.source_ref_no, h.effective_date, h.created_at, p.sku, p.name AS product_name')
            ->from('supplier_price_history h')->join('products p', 'p.id = h.product_id')->where('h.supplier_id', $supplierId);
        if ($productId) {
            $qb->where('h.product_id', $productId);
        }
        return $qb->order_by('h.effective_date DESC, h.id DESC')->limit($limit)->get()->result_array();
    }
}
