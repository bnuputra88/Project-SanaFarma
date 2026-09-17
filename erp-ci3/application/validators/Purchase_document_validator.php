<?php
class Purchase_document_validator extends Base_validator
{
    public function validateSupplier(array $d): void
    {
        $this->required($d, 'code', 'Kode supplier');
        $this->maxLen($d, 'code', 20, 'Kode supplier');
        $this->required($d, 'name', 'Nama supplier');
        $this->maxLen($d, 'name', 150, 'Nama supplier');
        $this->inList($d, 'supplier_type', ['DISTRIBUTOR', 'MANUFACTURER', 'PBF', 'IMPORTER', 'OTHER'], 'Tipe supplier');
        $this->email($d, 'email', 'Email');
        $this->numeric($d, 'payment_term_days', 'Tempo pembayaran');
        $this->throwIfErrors();
    }

    public function validateRequest(array $d): void
    {
        $this->required($d, 'request_date', 'Tanggal permintaan');
        $this->date($d, 'request_date', 'Tanggal permintaan');
        $this->date($d, 'required_date', 'Tanggal dibutuhkan');
        $items = $this->cleanItems($d['items'] ?? []);
        if (!$items) {
            $this->addError('items', 'Minimal satu baris item');
        }
        foreach ($items as $i => $it) {
            if (empty($it['product_id'])) {
                $this->addError("items.$i.product_id", 'Produk baris ' . ($i + 1) . ' wajib diisi');
            }
            if (!is_numeric($it['qty'] ?? null) || (float) $it['qty'] <= 0) {
                $this->addError("items.$i.qty", 'Qty baris ' . ($i + 1) . ' harus > 0');
            }
        }
        $this->throwIfErrors();
    }

    public function validateOrder(array $d): void
    {
        $this->required($d, 'supplier_id', 'Supplier');
        $this->required($d, 'warehouse_id', 'Gudang penerima');
        $this->required($d, 'order_date', 'Tanggal PO');
        $this->date($d, 'order_date', 'Tanggal PO');
        $this->date($d, 'expected_date', 'Tanggal perkiraan datang');
        $items = $this->cleanItems($d['items'] ?? []);
        if (!$items) {
            $this->addError('items', 'Minimal satu baris item');
        }
        foreach ($items as $i => $it) {
            if (empty($it['product_id'])) {
                $this->addError("items.$i.product_id", 'Produk baris ' . ($i + 1) . ' wajib diisi');
            }
            if (!is_numeric($it['qty_ordered'] ?? null) || (float) $it['qty_ordered'] <= 0) {
                $this->addError("items.$i.qty_ordered", 'Qty baris ' . ($i + 1) . ' harus > 0');
            }
            if (isset($it['unit_price']) && $it['unit_price'] !== '' && (float) $it['unit_price'] < 0) {
                $this->addError("items.$i.unit_price", 'Harga baris ' . ($i + 1) . ' tidak boleh negatif');
            }
        }
        $this->throwIfErrors();
    }

    public function validateReceipt(array $d): void
    {
        $this->required($d, 'supplier_id', 'Supplier');
        $this->required($d, 'warehouse_id', 'Gudang penerima');
        $this->required($d, 'receipt_date', 'Tanggal terima');
        $this->date($d, 'receipt_date', 'Tanggal terima');
        $items = $this->cleanReceiptItems($d['items'] ?? []);
        if (!$items) {
            $this->addError('items', 'Minimal satu baris item');
        }
        foreach ($items as $i => $it) {
            if (empty($it['product_id'])) {
                $this->addError("items.$i.product_id", 'Produk baris ' . ($i + 1) . ' wajib diisi');
            }
            if (!is_numeric($it['qty_received'] ?? null) || (float) $it['qty_received'] <= 0) {
                $this->addError("items.$i.qty_received", 'Qty terima baris ' . ($i + 1) . ' harus > 0');
            }
            $this->inList($it, 'inspection_result', ['ACCEPTED', 'QUARANTINE', 'REJECTED'], 'Hasil inspeksi baris ' . ($i + 1));
        }
        $this->throwIfErrors();
    }

    public function validateReturn(array $d): void
    {
        $this->required($d, 'supplier_id', 'Supplier');
        $this->required($d, 'warehouse_id', 'Gudang asal retur');
        $this->required($d, 'return_date', 'Tanggal retur');
        $this->date($d, 'return_date', 'Tanggal retur');
        $this->required($d, 'reason_code_id', 'Alasan retur');
        $items = $this->cleanReturnItems($d['items'] ?? []);
        if (!$items) {
            $this->addError('items', 'Minimal satu baris item');
        }
        foreach ($items as $i => $it) {
            if (empty($it['product_id'])) {
                $this->addError("items.$i.product_id", 'Produk baris ' . ($i + 1) . ' wajib diisi');
            }
            if (!is_numeric($it['qty'] ?? null) || (float) $it['qty'] <= 0) {
                $this->addError("items.$i.qty", 'Qty baris ' . ($i + 1) . ' harus > 0');
            }
        }
        $this->throwIfErrors();
    }

    public function cleanItems(array $items): array
    {
        return array_values(array_filter($items, fn($it) => is_array($it) && (!empty($it['product_id']) || !empty($it['qty']) || !empty($it['qty_ordered']))));
    }

    public function cleanReceiptItems(array $items): array
    {
        return array_values(array_filter($items, fn($it) => is_array($it) && (!empty($it['product_id']) || !empty($it['qty_received']) || !empty($it['batch_no']))));
    }

    public function cleanReturnItems(array $items): array
    {
        return array_values(array_filter($items, fn($it) => is_array($it) && (!empty($it['product_id']) || !empty($it['qty']))));
    }
}
