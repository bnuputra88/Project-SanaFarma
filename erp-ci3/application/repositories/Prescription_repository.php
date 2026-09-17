<?php
/** Header + item + bahan racikan resep. */
class Prescription_repository extends Base_repository
{
    protected $table = 'prescriptions';

    public function paginate(array $input, int $companyId): Paginator
    {
        $qb = $this->db->select('p.id, p.prescription_no, p.prescription_date, p.patient_name, p.doctor_name, p.status, p.grand_total, c.name AS customer_name, u.full_name AS created_by_name', false)
            ->from('prescriptions p')->join('customers c', 'c.id = p.customer_id', 'left')->join('users u', 'u.id = p.created_by', 'left')->where('p.company_id', $companyId);
        if (!empty($input['status'])) {
            $qb->where('p.status', $input['status']);
        }
        if (!empty($input['q'])) {
            $qb->group_start()->like('p.prescription_no', $input['q'])->or_like('p.patient_name', $input['q'])->or_like('p.doctor_name', $input['q'])->group_end();
        }
        return $this->paginateQuery($qb, $input, ['prescription_no' => 'p.prescription_no', 'prescription_date' => 'p.prescription_date', 'status' => 'p.status'], 'p.id DESC');
    }

    public function items(int $prescriptionId): array
    {
        return $this->db->select('i.*, p.sku, p.name AS product_name, p.dosage_form, p.strength, p.is_batch_tracked, p.base_uom_id, p.selling_price')->from('prescription_items i')
            ->join('products p', 'p.id = i.product_id', 'left')->where('i.prescription_id', $prescriptionId)->order_by('i.line_no')->get()->result_array();
    }

    public function compounds(int $prescriptionId): array
    {
        return $this->db->select('ci.id, ci.prescription_item_id, ci.line_no, ci.product_id, ci.qty, ci.unit_price, ci.notes, p.sku, p.name AS product_name, p.is_batch_tracked, p.base_uom_id, p.selling_price')
            ->from('prescription_compound_items ci')->join('prescription_items i', 'i.id = ci.prescription_item_id')->join('products p', 'p.id = ci.product_id')
            ->where('i.prescription_id', $prescriptionId)->order_by('ci.prescription_item_id')->order_by('ci.line_no')->get()->result_array();
    }

    public function insertItem(array $row): int
    {
        $this->db->insert('prescription_items', $row);
        return (int) $this->db->insert_id();
    }

    public function updateItem(int $id, array $row): void
    {
        $this->db->where('id', $id)->update('prescription_items', $row);
    }

    public function insertCompound(array $row): void
    {
        $this->db->insert('prescription_compound_items', $row);
    }

    public function deleteItems(int $prescriptionId): void
    {
        $this->db->where('prescription_id', $prescriptionId)->delete('prescription_items');
    }
}
