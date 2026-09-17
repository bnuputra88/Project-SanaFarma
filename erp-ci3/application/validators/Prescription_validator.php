<?php
class Prescription_validator extends Base_validator
{
    public function validate(array $d): void
    {
        $this->required($d, 'prescription_date', 'Tanggal resep');
        $this->date($d, 'prescription_date', 'Tanggal resep');
        $this->required($d, 'warehouse_id', 'Gudang/Etalase');
        $this->required($d, 'patient_name', 'Nama pasien');
        $this->maxLen($d, 'patient_name', 150, 'Nama pasien');
        $items = $this->cleanItems($d['items'] ?? []);
        if (!$items) {
            $this->addError('items', 'Minimal satu baris R/');
        }
        foreach ($items as $i => $it) {
            $type = ($it['item_type'] ?? 'PRODUCT') === 'COMPOUND' ? 'COMPOUND' : 'PRODUCT';
            if ($type === 'COMPOUND') {
                if (trim((string) ($it['compound_name'] ?? '')) === '') {
                    $this->addError("items.$i.compound_name", 'Nama racikan R/' . ($i + 1) . ' wajib diisi');
                }
                if (!$this->cleanIngredients($it['ingredients'] ?? [])) {
                    $this->addError("items.$i.ingredients", 'Racikan R/' . ($i + 1) . ' minimal satu bahan');
                }
            } elseif (empty($it['product_id'])) {
                $this->addError("items.$i.product_id", 'Obat R/' . ($i + 1) . ' wajib dipilih');
            }
            if (!is_numeric($it['qty'] ?? null) || (float) ($it['qty'] ?? 0) <= 0) {
                $this->addError("items.$i.qty", 'Jumlah R/' . ($i + 1) . ' harus > 0');
            }
        }
        $this->throwIfErrors();
    }

    public function cleanItems(array $items): array
    {
        return array_values(array_filter($items, function ($it) {
            if (!is_array($it)) {
                return false;
            }
            if (($it['item_type'] ?? '') === 'COMPOUND') {
                return trim((string) ($it['compound_name'] ?? '')) !== '' || (bool) $this->cleanIngredients($it['ingredients'] ?? []);
            }
            return !empty($it['product_id']) || !empty($it['qty']);
        }));
    }

    public function cleanIngredients(array $ings): array
    {
        return array_values(array_filter($ings, fn($g) => is_array($g) && !empty($g['product_id']) && (float) ($g['qty'] ?? 0) > 0));
    }
}
