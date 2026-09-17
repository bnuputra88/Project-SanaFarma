<?php
class User_service
{
    private $users;
    private $validator;
    private $auth;
    private $audit;
    private $ctx;
    private $db;

    public function __construct(User_repository $users, User_validator $validator, Auth_service $auth, Audit_service $audit, Request_context $ctx, Db $db)
    {
        $this->users = $users;
        $this->validator = $validator;
        $this->auth = $auth;
        $this->audit = $audit;
        $this->ctx = $ctx;
        $this->db = $db;
    }

    public function list(array $input): Paginator
    {
        return $this->users->paginate($input, $this->ctx->company_id);
    }

    public function get(int $id): array
    {
        $user = $this->users->findOrFail($id);
        if ((int) $user['company_id'] !== $this->ctx->company_id) {
            throw new Not_found_exception();
        }
        $user['role_ids'] = $this->users->roleIds($id);
        return $user;
    }

    public function create(array $d): int
    {
        $this->validator->validate($d, true);
        $this->ensureUnique($d, null);
        return $this->db->transaction(function () use ($d) {
            $id = $this->users->insert($this->mapRow($d) + ['company_id' => $this->ctx->company_id, 'password_hash' => 'pending', 'created_by' => $this->ctx->user_id]);
            $this->auth->setPassword($id, $d['password'], !empty($d['must_change_password']));
            $this->users->syncRoles($id, $d['role_ids']);
            $this->audit->log('system', 'create', 'users', $id, null, $this->mapRow($d) + ['role_ids' => $d['role_ids']]);
            return $id;
        });
    }

    public function update(int $id, array $d): void
    {
        $old = $this->get($id);
        $this->validator->validate($d, false);
        $this->ensureUnique($d, $id);
        $this->db->transaction(function () use ($id, $d, $old) {
            $row = $this->mapRow($d) + ['updated_by' => $this->ctx->user_id];
            $this->users->update($id, $row);
            $this->users->syncRoles($id, $d['role_ids']);
            if (!empty($d['password'])) {
                $this->auth->setPassword($id, $d['password'], !empty($d['must_change_password']));
            }
            [$o, $n] = $this->audit->diff($old, $row + ['role_ids' => implode(',', $d['role_ids'])]);
            $this->audit->log('system', 'update', 'users', $id, $o, $n);
        });
    }

    public function toggleActive(int $id): bool
    {
        $user = $this->get($id);
        if ((int) $user['id'] === $this->ctx->user_id) {
            throw new Domain_exception('Tidak dapat menonaktifkan akun sendiri');
        }
        $new = $user['is_active'] ? 0 : 1;
        $this->users->update($id, ['is_active' => $new, 'current_session_id' => null, 'updated_by' => $this->ctx->user_id]);
        $this->audit->log('system', $new ? 'activate' : 'deactivate', 'users', $id, ['is_active' => $user['is_active']], ['is_active' => $new]);
        return (bool) $new;
    }

    private function ensureUnique(array $d, ?int $excludeId): void
    {
        $errors = [];
        if ($this->users->exists(['username' => $d['username']], $excludeId)) {
            $errors['username'] = 'Username sudah digunakan';
        }
        if ($this->users->exists(['email' => strtolower($d['email'])], $excludeId)) {
            $errors['email'] = 'Email sudah digunakan';
        }
        if ($errors) {
            throw new Validation_exception($errors);
        }
    }

    private function mapRow(array $d): array
    {
        return ['username' => $d['username'], 'email' => strtolower($d['email']), 'full_name' => $d['full_name'], 'phone' => $d['phone'] ?: null,
            'branch_id' => !empty($d['branch_id']) ? (int) $d['branch_id'] : null, 'is_active' => !empty($d['is_active']) ? 1 : 0];
    }
}
