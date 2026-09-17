<?php
/** Master data services with small footprint: categories, UOM, warehouses, locations, reason codes. */
class Master_service
{
    private $master;
    private $warehouses;
    private $companies;
    private $validator;
    private $audit;
    private $ctx;

    public function __construct(Master_repository $master, Warehouse_repository $warehouses, Company_repository $companies, Product_validator $validator, Audit_service $audit, Request_context $ctx)
    {
        $this->master = $master;
        $this->warehouses = $warehouses;
        $this->companies = $companies;
        $this->validator = $validator;
        $this->audit = $audit;
        $this->ctx = $ctx;
    }

    public function branches(): array
    {
        return $this->companies->branches($this->ctx->company_id);
    }

    public function saveCategory(array $d): int
    {
        $this->validator->validateCategory($d);
        if ($this->master->codeExists('product_categories', ['company_id' => $this->ctx->company_id, 'code' => $d['code']], $d['id'] ?? null)) {
            throw new Validation_exception(['code' => 'Kode kategori sudah ada']);
        }
        $id = $this->master->saveCategory($this->ctx->company_id, $d);
        $this->audit->log('master', empty($d['id']) ? 'create' : 'update', 'product_categories', $id, null, $d);
        return $id;
    }

    public function saveUom(array $d): int
    {
        $this->validator->validateCategory($d);
        if ($this->master->codeExists('uoms', ['code' => strtoupper($d['code'])], $d['id'] ?? null)) {
            throw new Validation_exception(['code' => 'Kode satuan sudah ada']);
        }
        $id = $this->master->saveUom($d);
        $this->audit->log('master', empty($d['id']) ? 'create' : 'update', 'uoms', $id, null, $d);
        return $id;
    }

    public function warehouses(): array
    {
        return $this->warehouses->listWithBranch($this->ctx->company_id);
    }

    public function activeWarehouses(): array
    {
        return $this->warehouses->active($this->ctx->company_id);
    }

    public function saveWarehouse(array $d): int
    {
        $this->validator->validateWarehouse($d);
        if ($this->master->codeExists('warehouses', ['company_id' => $this->ctx->company_id, 'code' => strtoupper($d['code'])], $d['id'] ?? null)) {
            throw new Validation_exception(['code' => 'Kode gudang sudah ada']);
        }
        $id = $this->warehouses->save($this->ctx->company_id, $d);
        $this->audit->log('master', empty($d['id']) ? 'create' : 'update', 'warehouses', $id, null, $d);
        return $id;
    }

    public function warehouse(int $id): array
    {
        $w = $this->warehouses->findOrFail($id);
        if ((int) $w['company_id'] !== $this->ctx->company_id) {
            throw new Not_found_exception();
        }
        return $w;
    }

    public function locations(int $warehouseId, bool $activeOnly = false): array
    {
        return $this->warehouses->locations($warehouseId, $activeOnly);
    }

    public function saveLocation(int $warehouseId, array $d): int
    {
        $this->warehouse($warehouseId);
        $this->validator->validateLocation($d);
        if ($this->master->codeExists('locations', ['warehouse_id' => $warehouseId, 'code' => strtoupper($d['code'])], $d['id'] ?? null)) {
            throw new Validation_exception(['code' => 'Kode lokasi sudah ada di gudang ini']);
        }
        $id = $this->warehouses->saveLocation($warehouseId, $d);
        $this->audit->log('master', empty($d['id']) ? 'create' : 'update', 'locations', $id, null, $d + ['warehouse_id' => $warehouseId]);
        return $id;
    }

    public function reasonCodes(?string $type = null, bool $activeOnly = false): array
    {
        return $this->master->reasonCodes($this->ctx->company_id, $type, $activeOnly);
    }

    public function saveReasonCode(array $d): int
    {
        $this->validator->validateReasonCode($d);
        if ($this->master->codeExists('reason_codes', ['company_id' => $this->ctx->company_id, 'reason_type' => $d['reason_type'], 'code' => strtoupper($d['code'])], $d['id'] ?? null)) {
            throw new Validation_exception(['code' => 'Kode alasan sudah ada untuk tipe ini']);
        }
        $id = $this->master->saveReasonCode($this->ctx->company_id, $d);
        $this->audit->log('master', empty($d['id']) ? 'create' : 'update', 'reason_codes', $id, null, $d);
        return $id;
    }
}
