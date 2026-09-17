<?php
/** Master pelanggan / pasien. */
class Customer_service
{
    private $customers;
    private $validator;
    private $master;
    private $numbering;
    private $audit;
    private $ctx;
    private $db;

    public function __construct(Customer_repository $customers, Sales_document_validator $validator, Master_repository $master, Numbering_service $numbering, Audit_service $audit, Request_context $ctx, Db $db)
    {
        $this->customers = $customers;
        $this->validator = $validator;
        $this->master = $master;
        $this->numbering = $numbering;
        $this->audit = $audit;
        $this->ctx = $ctx;
        $this->db = $db;
    }

    public function list(array $input): Paginator
    {
        return $this->customers->paginate($input, $this->ctx->company_id);
    }

    public function search(string $q): array
    {
        return $this->customers->search($this->ctx->company_id, $q);
    }

    public function get(int $id): array
    {
        $c = $this->customers->findOrFail($id);
        if ((int) $c['company_id'] !== $this->ctx->company_id) {
            throw new Not_found_exception();
        }
        return $c;
    }

    public function save(array $d): int
    {
        $this->validator->validateCustomer($d);
        return $this->db->transaction(function () use ($d) {
            $data = ['name' => trim($d['name']), 'customer_type' => $d['customer_type'] ?? 'WALK_IN', 'nik' => $d['nik'] ?: null, 'phone' => $d['phone'] ?: null,
                'email' => $d['email'] ?: null, 'address' => $d['address'] ?: null, 'date_of_birth' => $d['date_of_birth'] ?: null, 'allergy_notes' => $d['allergy_notes'] ?: null,
                'is_active' => isset($d['is_active']) ? (int) (bool) $d['is_active'] : 1];
            if (!empty($d['id'])) {
                $data['updated_by'] = $this->ctx->user_id;
                $this->customers->update((int) $d['id'], $data);
                $id = (int) $d['id'];
            } else {
                $code = $d['code'] ?: $this->numbering->next('CUSTOMER');
                $id = $this->customers->insert($data + ['company_id' => $this->ctx->company_id, 'code' => $code, 'created_by' => $this->ctx->user_id]);
            }
            $this->audit->log('sales', empty($d['id']) ? 'create' : 'update', 'customers', $id, null, ['name' => $data['name']]);
            return $id;
        });
    }
}
