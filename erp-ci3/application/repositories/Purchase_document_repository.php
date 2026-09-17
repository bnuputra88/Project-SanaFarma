<?php
/** Generic header+items repository untuk dokumen procurement (PR/PO/GR/Return): bentuk sama, tabel & join berbeda. */
class Purchase_document_repository extends Base_repository
{
    private $itemsTable;
    private $fk;
    private $hasUom;
    private $hasBatchId;
    private $hasLocation;
    private $hasSupplier;

    public function __construct(CI_DB_query_builder $db, string $table, string $itemsTable, string $fk, bool $hasUom, bool $hasBatchId, bool $hasLocation, bool $hasSupplier)
    {
        parent::__construct($db);
        $this->table = $table;
        $this->itemsTable = $itemsTable;
        $this->fk = $fk;
        $this->hasUom = $hasUom;
        $this->hasBatchId = $hasBatchId;
        $this->hasLocation = $hasLocation;
        $this->hasSupplier = $hasSupplier;
    }

    public static function requests(CI_DB_query_builder $db): self
    {
        return new self($db, 'purchase_requests', 'purchase_request_items', 'pr_id', true, false, false, false);
    }

    public static function orders(CI_DB_query_builder $db): self
    {
        return new self($db, 'purchase_orders', 'purchase_order_items', 'po_id', true, false, false, true);
    }

    public static function receipts(CI_DB_query_builder $db): self
    {
        return new self($db, 'goods_receipts', 'goods_receipt_items', 'gr_id', false, false, true, true);
    }

    public static function returns(CI_DB_query_builder $db): self
    {
        return new self($db, 'purchase_returns', 'purchase_return_items', 'return_id', false, true, true, true);
    }

    public function items(int $docId): array
    {
        $qb = $this->db->select('i.*, p.sku, p.name AS product_name, p.is_batch_tracked, p.is_expiry_tracked')->from("{$this->itemsTable} i")->join('products p', 'p.id = i.product_id');
        if ($this->hasUom) {
            $qb->select('u.code AS uom_code')->join('uoms u', 'u.id = i.uom_id', 'left');
        }
        if ($this->hasBatchId) {
            $qb->select('b.batch_no, b.expiry_date')->join('batches b', 'b.id = i.batch_id', 'left');
        }
        if ($this->hasLocation) {
            $qb->select('l.code AS location_code')->join('locations l', 'l.id = i.location_id', 'left');
        }
        return $qb->where("i.{$this->fk}", $docId)->order_by('i.line_no')->get()->result_array();
    }

    public function replaceItems(int $docId, array $rows): void
    {
        $this->db->where($this->fk, $docId)->delete($this->itemsTable);
        foreach ($rows as &$r) {
            $r[$this->fk] = $docId;
        }
        unset($r);
        if ($rows) {
            $this->db->insert_batch($this->itemsTable, $rows);
        }
    }

    public function insertItems(int $docId, array $rows): void
    {
        $this->replaceItems($docId, $rows);
    }

    public function updateItem(int $itemId, array $data): void
    {
        $this->db->where('id', $itemId)->update($this->itemsTable, $data);
    }

    public function paginate(array $input, int $companyId, string $noCol, string $dateCol): Paginator
    {
        $qb = $this->db->select("d.id, d.$noCol AS doc_no, d.$dateCol AS doc_date, d.status, d.notes, d.created_at, u.full_name AS created_by_name")
            ->from("{$this->table} d")->join('users u', 'u.id = d.created_by', 'left')->where('d.company_id', $companyId);
        if ($this->hasSupplier) {
            $qb->select('s.name AS supplier_name, d.grand_total')->join('suppliers s', 's.id = d.supplier_id', 'left');
        }
        if (!empty($input['status'])) {
            $qb->where('d.status', $input['status']);
        }
        if (!empty($input['supplier_id'])) {
            $qb->where('d.supplier_id', (int) $input['supplier_id']);
        }
        if (!empty($input['q'])) {
            $qb->like("d.$noCol", $input['q']);
        }
        return $this->paginateQuery($qb, $input, ['doc_no' => "d.$noCol", 'doc_date' => "d.$dateCol", 'status' => 'd.status'], 'd.id DESC');
    }
}
