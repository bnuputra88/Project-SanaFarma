<?php
/** Supplier master + katalog produk + riwayat harga. */
class Supplier_service
{
    private $suppliers;
    private $catalog;
    private $validator;
    private $master;
    private $audit;
    private $ctx;
    private $db;

    public function __construct(Supplier_repository $suppliers, Supplier_product_repository $catalog, Purchase_document_validator $validator, Master_repository $master, Audit_service $audit, Request_context $ctx, Db $db)
    {
        $this->suppliers = $suppliers;
        $this->catalog = $catalog;
        $this->validator = $validator;
        $this->master = $master;
        $this->audit = $audit;
        $this->ctx = $ctx;
        $this->db = $db;
    }

    public function list(array $input): Paginator
    {
        return $this->suppliers->paginate($input, $this->ctx->company_id);
    }

    public function active(): array
    {
        return $this->suppliers->active($this->ctx->company_id);
    }

    public function get(int $id): array
    {
        $s = $this->suppliers->findOrFail($id);
        if ((int) $s['company_id'] !== $this->ctx->company_id) {
            throw new Not_found_exception();
        }
        $s['catalog'] = $this->catalog->forSupplier($id);
        $s['price_history'] = $this->catalog->priceHistory($id);
        return $s;
    }

    public function save(array $d): int
    {
        $this->validator->validateSupplier($d);
        if ($this->master->codeExists('suppliers', ['company_id' => $this->ctx->company_id, 'code' => strtoupper($d['code'])], $d['id'] ?? null)) {
            throw new Validation_exception(['code' => 'Kode supplier sudah ada']);
        }
        $d['_actor'] = $this->ctx->user_id;
        $id = $this->suppliers->save($this->ctx->company_id, $d);
        $this->audit->log('purchasing', empty($d['id']) ? 'create' : 'update', 'suppliers', $id, null, ['code' => $d['code'], 'name' => $d['name']], $d['code']);
        return $id;
    }

    public function priceHistory(int $supplierId, ?int $productId = null): array
    {
        $this->get($supplierId);
        return $this->catalog->priceHistory($supplierId, $productId);
    }
}
