<?php
/** Generic document + items repository for adjustment / transfer / opname (same shape, different tables). */
class Stock_document_repository extends Base_repository
{
    private $itemsTable;
    private $fk;

    public function __construct(CI_DB_query_builder $db, string $table = 'stock_adjustments', string $itemsTable = 'stock_adjustment_items', string $fk = 'adjustment_id')
    {
        parent::__construct($db);
        $this->table = $table;
        $this->itemsTable = $itemsTable;
        $this->fk = $fk;
    }

    public static function adjustments(CI_DB_query_builder $db): self
    {
        return new self($db, 'stock_adjustments', 'stock_adjustment_items', 'adjustment_id');
    }

    public static function transfers(CI_DB_query_builder $db): self
    {
        return new self($db, 'stock_transfers', 'stock_transfer_items', 'transfer_id');
    }

    public static function opnames(CI_DB_query_builder $db): self
    {
        return new self($db, 'stock_opnames', 'stock_opname_items', 'opname_id');
    }

    public function items(int $docId): array
    {
        return $this->db->select("i.*, p.sku, p.name AS product_name, b.batch_no, b.expiry_date, u.code AS uom_code, l.code AS location_code")
            ->from("{$this->itemsTable} i")->join('products p', 'p.id = i.product_id')->join('batches b', 'b.id = i.batch_id', 'left')
            ->join('uoms u', 'u.id = p.base_uom_id', 'left')->join('locations l', 'l.id = i.' . ($this->table === 'stock_transfers' ? 'from_location_id' : 'location_id'), 'left')
            ->where("i.{$this->fk}", $docId)->order_by('i.id')->get()->result_array();
    }

    public function replaceItems(int $docId, array $rows): void
    {
        $this->db->where($this->fk, $docId)->delete($this->itemsTable);
        foreach ($rows as &$r) {
            $r[$this->fk] = $docId;
        }
        if ($rows) {
            $this->db->insert_batch($this->itemsTable, $rows);
        }
    }

    public function updateItem(int $itemId, array $data): void
    {
        $this->db->where('id', $itemId)->update($this->itemsTable, $data);
    }

    public function paginate(array $input, int $companyId, string $noCol, string $dateCol): Paginator
    {
        $qb = $this->db->select("d.id, d.$noCol AS doc_no, d.$dateCol AS doc_date, d.status, d.notes, d.created_at, u.full_name AS created_by_name")
            ->from("{$this->table} d")->join('users u', 'u.id = d.created_by', 'left')->where('d.company_id', $companyId);
        if ($this->table === 'stock_transfers') {
            $qb->select('wf.name AS from_warehouse_name, wt.name AS to_warehouse_name')->join('warehouses wf', 'wf.id = d.from_warehouse_id')->join('warehouses wt', 'wt.id = d.to_warehouse_id');
        } else {
            $qb->select('w.name AS warehouse_name')->join('warehouses w', 'w.id = d.warehouse_id');
        }
        if (!empty($input['status'])) {
            $qb->where('d.status', $input['status']);
        }
        if (!empty($input['q'])) {
            $qb->like("d.$noCol", $input['q']);
        }
        return $this->paginateQuery($qb, $input, ['doc_no' => "d.$noCol", 'doc_date' => "d.$dateCol", 'status' => 'd.status'], 'd.id DESC');
    }
}
