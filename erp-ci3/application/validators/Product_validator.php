<?php
class Product_validator extends Base_validator
{
    public function validate(array $d): void
    {
        $this->required($d, 'sku', 'SKU');
        $this->pattern($d, 'sku', '/^[A-Za-z0-9._\/-]{2,40}$/', 'SKU 2-40 karakter alfanumerik');
        $this->required($d, 'name', 'Nama produk');
        $this->maxLen($d, 'name', 200, 'Nama produk');
        $this->required($d, 'base_uom_id', 'Satuan dasar');
        $this->numeric($d, 'purchase_price', 'Harga beli');
        $this->numeric($d, 'selling_price', 'Harga jual');
        foreach (['min_stock' => 'Stok minimum', 'max_stock' => 'Stok maksimum', 'reorder_point' => 'Reorder point', 'safety_stock' => 'Safety stock', 'lead_time_days' => 'Lead time'] as $f => $l) {
            $this->numeric($d, $f, $l);
        }
        if (isset($d['min_stock'], $d['max_stock']) && $d['max_stock'] !== '' && (float) $d['max_stock'] > 0 && (float) $d['min_stock'] > (float) $d['max_stock']) {
            $this->addError('max_stock', 'Stok maksimum harus >= stok minimum');
        }
        $this->inList($d, 'status', ['ACTIVE', 'INACTIVE', 'DISCONTINUED'], 'Status');
        if (!empty($d['is_expiry_tracked']) && empty($d['is_batch_tracked'])) {
            $this->addError('is_expiry_tracked', 'Expiry tracking memerlukan batch tracking');
        }
        if (!empty($d['is_cold_chain']) && (($d['storage_min_temp'] ?? '') === '' || ($d['storage_max_temp'] ?? '') === '')) {
            $this->addError('storage_min_temp', 'Produk cold-chain wajib memiliki rentang suhu penyimpanan');
        }
        $this->throwIfErrors();
    }

    public function validateCategory(array $d): void
    {
        $this->required($d, 'code', 'Kode');
        $this->maxLen($d, 'code', 20, 'Kode');
        $this->required($d, 'name', 'Nama');
        $this->throwIfErrors();
    }

    public function validateWarehouse(array $d): void
    {
        $this->required($d, 'code', 'Kode');
        $this->maxLen($d, 'code', 20, 'Kode');
        $this->required($d, 'name', 'Nama');
        $this->required($d, 'branch_id', 'Cabang');
        $this->inList($d, 'warehouse_type', ['MAIN', 'RETAIL', 'TRANSIT', 'QUARANTINE', 'RETURN', 'COLD'], 'Tipe gudang');
        $this->throwIfErrors();
    }

    public function validateLocation(array $d): void
    {
        $this->required($d, 'code', 'Kode lokasi');
        $this->maxLen($d, 'code', 30, 'Kode lokasi');
        $this->inList($d, 'location_type', ['ZONE', 'RACK', 'BIN'], 'Tipe lokasi');
        $this->throwIfErrors();
    }

    public function validateReasonCode(array $d): void
    {
        $this->required($d, 'code', 'Kode');
        $this->required($d, 'name', 'Nama');
        $this->inList($d, 'reason_type', ['ADJUSTMENT', 'RETURN', 'CANCELLATION', 'QUARANTINE', 'REVERSAL', 'OPNAME'], 'Tipe alasan');
        $this->throwIfErrors();
    }
}
