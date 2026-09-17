<?php
class Stock_document_validator extends Base_validator
{
    private $conditions = ['GOOD', 'DAMAGED', 'QUARANTINE', 'EXPIRED'];

    public function validateAdjustment(array $d): void
    {
        $this->required($d, 'warehouse_id', 'Gudang');
        $this->required($d, 'adjustment_date', 'Tanggal');
        $this->date($d, 'adjustment_date', 'Tanggal');
        $this->required($d, 'reason_code_id', 'Alasan penyesuaian');
        $this->maxLen($d, 'notes', 500, 'Catatan');
        $items = $this->cleanItems($d['items'] ?? []);
        if (!$items) {
            $this->addError('items', 'Minimal satu baris item');
        }
        foreach ($items as $i => $it) {
            if (empty($it['product_id'])) {
                $this->addError("items.$i.product_id", 'Produk baris ' . ($i + 1) . ' wajib diisi');
            }
            if (!is_numeric($it['qty_change'] ?? null) || (float) $it['qty_change'] == 0) {
                $this->addError("items.$i.qty_change", 'Qty baris ' . ($i + 1) . ' harus angka bukan nol');
            }
            $this->inList($it, 'condition_code', $this->conditions, 'Kondisi baris ' . ($i + 1));
        }
        $this->throwIfErrors();
    }

    public function validateTransfer(array $d): void
    {
        $this->required($d, 'from_warehouse_id', 'Gudang asal');
        $this->required($d, 'to_warehouse_id', 'Gudang tujuan');
        if (!empty($d['from_warehouse_id']) && $d['from_warehouse_id'] === ($d['to_warehouse_id'] ?? null)) {
            $this->addError('to_warehouse_id', 'Gudang tujuan harus berbeda dari gudang asal');
        }
        $this->required($d, 'transfer_date', 'Tanggal');
        $this->date($d, 'transfer_date', 'Tanggal');
        $items = $this->cleanItems($d['items'] ?? []);
        if (!$items) {
            $this->addError('items', 'Minimal satu baris item');
        }
        foreach ($items as $i => $it) {
            if (empty($it['product_id'])) {
                $this->addError("items.$i.product_id", 'Produk baris ' . ($i + 1) . ' wajib diisi');
            }
            if (!is_numeric($it['qty_requested'] ?? null) || (float) $it['qty_requested'] <= 0) {
                $this->addError("items.$i.qty_requested", 'Qty baris ' . ($i + 1) . ' harus > 0');
            }
        }
        $this->throwIfErrors();
    }

    public function validateOpname(array $d): void
    {
        $this->required($d, 'warehouse_id', 'Gudang');
        $this->required($d, 'opname_date', 'Tanggal');
        $this->date($d, 'opname_date', 'Tanggal');
        $this->inList($d, 'opname_type', ['FULL', 'CYCLE'], 'Tipe opname');
        $this->throwIfErrors();
    }

    /** Drop empty template rows sent by the dynamic form. */
    public function cleanItems(array $items): array
    {
        return array_values(array_filter($items, fn($it) => is_array($it) && (!empty($it['product_id']) || !empty($it['qty_change']) || !empty($it['qty_requested']))));
    }
}
