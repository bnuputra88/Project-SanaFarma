<?php
class Product_repository extends Base_repository
{
    protected $table = 'products';
    protected $softDelete = true;
    protected $columns = ['id', 'company_id', 'sku', 'barcode', 'name', 'generic_name', 'brand', 'manufacturer_name', 'principal_name', 'category_id', 'subcategory_id',
        'classification_id', 'dosage_form', 'preparation_type', 'strength', 'packaging', 'base_uom_id', 'purchase_price', 'selling_price', 'margin_pct', 'tax_code', 'is_taxable',
        'min_stock', 'max_stock', 'reorder_point', 'safety_stock', 'lead_time_days', 'is_batch_tracked', 'is_expiry_tracked', 'is_serial_tracked', 'requires_prescription',
        'is_cold_chain', 'is_controlled', 'storage_min_temp', 'storage_max_temp', 'status', 'created_at', 'updated_at'];

    public function paginate(array $input, int $companyId): Paginator
    {
        $qb = $this->db->select('p.id, p.sku, p.barcode, p.name, p.generic_name, p.strength, p.dosage_form, p.selling_price, p.purchase_price, p.status,
                p.requires_prescription, p.is_controlled, p.is_cold_chain, p.min_stock, c.name AS category_name, u.code AS uom_code, dc.name AS classification_name,
                (SELECT COALESCE(SUM(sb.qty_on_hand),0) FROM stock_balances sb WHERE sb.product_id = p.id AND sb.condition_code = "GOOD") AS qty_on_hand', false)
            ->from('products p')->join('product_categories c', 'c.id = p.category_id', 'left')->join('uoms u', 'u.id = p.base_uom_id', 'left')
            ->join('drug_classifications dc', 'dc.id = p.classification_id', 'left')
            ->where('p.company_id', $companyId)->where('p.deleted_at IS NULL', null, false);
        if (!empty($input['q'])) {
            $qb->group_start()->like('p.name', $input['q'])->or_like('p.sku', $input['q'])->or_like('p.barcode', $input['q'])->or_like('p.generic_name', $input['q'])->group_end();
        }
        if (!empty($input['category_id'])) {
            $qb->where('p.category_id', (int) $input['category_id']);
        }
        if (!empty($input['status'])) {
            $qb->where('p.status', $input['status']);
        }
        foreach (['requires_prescription', 'is_controlled', 'is_cold_chain'] as $flag) {
            if (!empty($input[$flag])) {
                $qb->where("p.$flag", 1);
            }
        }
        return $this->paginateQuery($qb, $input, ['sku' => 'p.sku', 'name' => 'p.name', 'selling_price' => 'p.selling_price', 'qty_on_hand' => 'qty_on_hand'], 'p.name');
    }

    /** Fast lookup for POS/barcode/AJAX autocomplete (limited, indexed columns). */
    public function search(int $companyId, string $term, int $limit = 15): array
    {
        return $this->db->select('p.id, p.sku, p.barcode, p.name, p.generic_name, p.strength, p.selling_price, p.is_batch_tracked, p.is_expiry_tracked, u.code AS uom_code')
            ->from('products p')->join('uoms u', 'u.id = p.base_uom_id', 'left')
            ->where(['p.company_id' => $companyId, 'p.status' => 'ACTIVE'])->where('p.deleted_at IS NULL', null, false)
            ->group_start()->where('p.barcode', $term)->or_like('p.name', $term, 'after')->or_like('p.sku', $term, 'after')->or_like('p.generic_name', $term, 'after')->group_end()
            ->order_by('p.barcode = ' . $this->db->escape($term), 'DESC', false)->order_by('p.name')->limit($limit)->get()->result_array();
    }

    public function units(int $productId): array
    {
        return $this->db->select('pu.id, pu.uom_id, pu.conversion_factor, pu.barcode, pu.is_purchase_uom, pu.is_sales_uom, pu.selling_price, u.code AS uom_code, u.name AS uom_name')
            ->from('product_units pu')->join('uoms u', 'u.id = pu.uom_id')->where('pu.product_id', $productId)->order_by('pu.conversion_factor')->get()->result_array();
    }

    public function replaceUnits(int $productId, array $units): void
    {
        $this->db->where('product_id', $productId)->delete('product_units');
        $rows = [];
        foreach ($units as $u) {
            if (empty($u['uom_id']) || (float) ($u['conversion_factor'] ?? 0) <= 0) {
                continue;
            }
            $rows[] = ['product_id' => $productId, 'uom_id' => (int) $u['uom_id'], 'conversion_factor' => (float) $u['conversion_factor'],
                'barcode' => $u['barcode'] ?: null, 'is_purchase_uom' => !empty($u['is_purchase_uom']) ? 1 : 0, 'is_sales_uom' => !empty($u['is_sales_uom']) ? 1 : 0,
                'selling_price' => $u['selling_price'] !== '' ? (float) $u['selling_price'] : null];
        }
        if ($rows) {
            $this->db->insert_batch('product_units', $rows);
        }
    }

    public function categories(int $companyId): array
    {
        return $this->db->select('id, parent_id, code, name, is_active')->from('product_categories')->where('company_id', $companyId)->order_by('name')->get()->result_array();
    }

    public function uoms(): array
    {
        return $this->db->select('id, code, name, is_active')->from('uoms')->order_by('code')->get()->result_array();
    }

    public function classifications(): array
    {
        return $this->db->select('id, code, name, requires_prescription, is_controlled')->from('drug_classifications')->order_by('name')->get()->result_array();
    }

    public function lowStock(int $companyId, int $limit = 10): array
    {
        return $this->db->query('SELECT p.id, p.sku, p.name, p.min_stock, p.reorder_point, COALESCE(SUM(sb.qty_on_hand),0) AS qty_on_hand
            FROM products p LEFT JOIN stock_balances sb ON sb.product_id = p.id AND sb.condition_code = "GOOD"
            WHERE p.company_id = ? AND p.deleted_at IS NULL AND p.status = "ACTIVE" AND p.reorder_point > 0
            GROUP BY p.id HAVING qty_on_hand <= p.reorder_point ORDER BY (qty_on_hand / NULLIF(p.reorder_point,0)) ASC LIMIT ' . (int) $limit, [$companyId])->result_array();
    }
}
