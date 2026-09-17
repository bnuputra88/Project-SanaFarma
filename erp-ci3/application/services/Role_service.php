<?php
class Role_service
{
    private $roles;
    private $audit;
    private $ctx;
    private $db;

    public function __construct(Role_repository $roles, Audit_service $audit, Request_context $ctx, Db $db)
    {
        $this->roles = $roles;
        $this->audit = $audit;
        $this->ctx = $ctx;
        $this->db = $db;
    }

    public function list(): array
    {
        return $this->roles->listWithCounts($this->ctx->company_id);
    }

    /** Permissions grouped by module → resource → [action => id]. */
    public function permissionMatrix(): array
    {
        $out = [];
        foreach ($this->roles->allPermissions() as $p) {
            $out[$p['module']][$p['resource']][$p['action']] = (int) $p['id'];
        }
        return $out;
    }

    public function get(int $id): array
    {
        $role = $this->roles->findOrFail($id);
        if ((int) $role['company_id'] !== $this->ctx->company_id) {
            throw new Not_found_exception();
        }
        $role['permission_ids'] = $this->roles->permissionIds($id);
        return $role;
    }

    public function save(array $d, ?int $id = null): int
    {
        $errors = [];
        if (empty($d['code']) || !preg_match('/^[A-Z0-9_]{2,40}$/', $d['code'])) {
            $errors['code'] = 'Kode role 2-40 karakter huruf besar/angka/underscore';
        }
        if (empty($d['name'])) {
            $errors['name'] = 'Nama role wajib diisi';
        }
        if (!$errors && $this->roles->exists(['company_id' => $this->ctx->company_id, 'code' => $d['code']], $id)) {
            $errors['code'] = 'Kode role sudah digunakan';
        }
        if ($errors) {
            throw new Validation_exception($errors);
        }
        $permIds = array_map('intval', $d['permission_ids'] ?? []);
        return $this->db->transaction(function () use ($d, $id, $permIds) {
            $row = ['code' => $d['code'], 'name' => $d['name'], 'description' => $d['description'] ?: null, 'is_active' => !empty($d['is_active']) ? 1 : 0];
            if ($id) {
                $old = $this->get($id);
                if ($old['is_system'] && $row['code'] !== $old['code']) {
                    throw new Domain_exception('Kode role sistem tidak dapat diubah');
                }
                $this->roles->update($id, $row);
                $this->audit->log('system', 'update', 'roles', $id, ['permission_ids' => $old['permission_ids']] + $old, ['permission_ids' => $permIds] + $row);
            } else {
                $id = $this->roles->insert($row + ['company_id' => $this->ctx->company_id]);
                $this->audit->log('system', 'create', 'roles', $id, null, $row + ['permission_ids' => $permIds]);
            }
            $this->roles->syncPermissions($id, $permIds);
            return $id;
        });
    }
}
