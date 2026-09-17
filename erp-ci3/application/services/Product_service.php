<?php
class Product_service
{
    private $products;
    private $validator;
    private $audit;
    private $ctx;
    private $db;

    public function __construct(Product_repository $products, Product_validator $validator, Audit_service $audit, Request_context $ctx, Db $db)
    {
        $this->products = $products;
        $this->validator = $validator;
        $this->audit = $audit;
        $this->ctx = $ctx;
        $this->db = $db;
    }

    public function list(array $input): Paginator
    {
        return $this->products->paginate($input, $this->ctx->company_id);
    }

    public function search(string $term): array
    {
        return mb_strlen($term) < 2 ? [] : $this->products->search($this->ctx->company_id, $term);
    }

    public function get(int $id): array
    {
        $p = $this->products->findOrFail($id);
        if ((int) $p['company_id'] !== $this->ctx->company_id) {
            throw new Not_found_exception();
        }
        $p['units'] = $this->products->units($id);
        return $p;
    }

    public function references(): array
    {
        return ['categories' => $this->products->categories($this->ctx->company_id), 'uoms' => $this->products->uoms(), 'classifications' => $this->products->classifications()];
    }

    public function save(array $d, ?int $id = null): int
    {
        $this->validator->validate($d);
        if ($this->products->exists(['company_id' => $this->ctx->company_id, 'sku' => $d['sku']], $id)) {
            throw new Validation_exception(['sku' => 'SKU sudah digunakan']);
        }
        if (!empty($d['barcode']) && $this->products->exists(['company_id' => $this->ctx->company_id, 'barcode' => $d['barcode']], $id)) {
            throw new Validation_exception(['barcode' => 'Barcode sudah digunakan produk lain']);
        }
        $row = $this->mapRow($d);
        return $this->db->transaction(function () use ($row, $d, $id) {
            if ($id) {
                $old = $this->get($id);
                $this->products->update($id, $row + ['updated_by' => $this->ctx->user_id]);
                [$o, $n] = $this->audit->diff($old, $row);
                $this->audit->log('master', 'update', 'products', $id, $o, $n, $row['sku']);
            } else {
                $id = $this->products->insert($row + ['company_id' => $this->ctx->company_id, 'created_by' => $this->ctx->user_id]);
                $this->audit->log('master', 'create', 'products', $id, null, $row, $row['sku']);
            }
            $this->products->replaceUnits($id, $d['units'] ?? []);
            return $id;
        });
    }

    public function toggleStatus(int $id): string
    {
        $p = $this->get($id);
        $new = $p['status'] === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE';
        $this->products->update($id, ['status' => $new, 'updated_by' => $this->ctx->user_id]);
        $this->audit->log('master', 'status_change', 'products', $id, ['status' => $p['status']], ['status' => $new], $p['sku']);
        return $new;
    }

    private function mapRow(array $d): array
    {
        $purchase = (float) ($d['purchase_price'] ?? 0);
        $selling = (float) ($d['selling_price'] ?? 0);
        $flags = ['is_taxable', 'is_batch_tracked', 'is_expiry_tracked', 'is_serial_tracked', 'requires_prescription', 'is_cold_chain', 'is_controlled'];
        $row = ['sku' => strtoupper(trim($d['sku'])), 'barcode' => $d['barcode'] ?: null, 'name' => trim($d['name']), 'generic_name' => $d['generic_name'] ?: null, 'brand' => $d['brand'] ?: null,
            'manufacturer_name' => $d['manufacturer_name'] ?: null, 'principal_name' => $d['principal_name'] ?: null, 'category_id' => $d['category_id'] ?: null, 'subcategory_id' => $d['subcategory_id'] ?: null,
            'classification_id' => $d['classification_id'] ?: null, 'dosage_form' => $d['dosage_form'] ?: null, 'preparation_type' => $d['preparation_type'] ?: null, 'strength' => $d['strength'] ?: null,
            'packaging' => $d['packaging'] ?: null, 'base_uom_id' => (int) $d['base_uom_id'], 'purchase_price' => $purchase, 'selling_price' => $selling,
            'margin_pct' => $purchase > 0 ? round(($selling - $purchase) / $purchase * 100, 3) : 0, 'tax_code' => $d['tax_code'] ?: null,
            'min_stock' => (float) ($d['min_stock'] ?? 0), 'max_stock' => (float) ($d['max_stock'] ?? 0), 'reorder_point' => (float) ($d['reorder_point'] ?? 0), 'safety_stock' => (float) ($d['safety_stock'] ?? 0),
            'lead_time_days' => (int) ($d['lead_time_days'] ?? 0), 'storage_min_temp' => ($d['storage_min_temp'] ?? '') !== '' ? (float) $d['storage_min_temp'] : null,
            'storage_max_temp' => ($d['storage_max_temp'] ?? '') !== '' ? (float) $d['storage_max_temp'] : null, 'status' => $d['status'] ?? 'ACTIVE'];
        foreach ($flags as $f) {
            $row[$f] = !empty($d[$f]) ? 1 : 0;
        }
        return $row;
    }
}
