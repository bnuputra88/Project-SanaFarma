<?php
class Sales_document_validator extends Base_validator
{
    public function validateCustomer(array $d): void
    {
        $this->required($d, 'name', 'Nama pelanggan');
        $this->maxLen($d, 'name', 150, 'Nama');
        $this->inList($d, 'customer_type', ['WALK_IN', 'MEMBER', 'PATIENT', 'CORPORATE'], 'Tipe pelanggan');
        $this->email($d, 'email', 'Email');
        $this->throwIfErrors();
    }

    public function validateSale(array $d): void
    {
        $this->required($d, 'warehouse_id', 'Gudang/Etalase');
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

    public function validateReturn(array $d): void
    {
        $this->required($d, 'warehouse_id', 'Gudang');
        $this->required($d, 'return_date', 'Tanggal retur');
        $this->date($d, 'return_date', 'Tanggal retur');
        $this->required($d, 'reason_code_id', 'Alasan retur');
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

    public function cleanItems(array $items): array
    {
        return array_values(array_filter($items, fn($it) => is_array($it) && (!empty($it['product_id']) || !empty($it['qty']))));
    }

    public function cleanPayments(array $payments): array
    {
        return array_values(array_filter($payments, fn($p) => is_array($p) && isset($p['amount']) && (float) $p['amount'] > 0));
    }
}
