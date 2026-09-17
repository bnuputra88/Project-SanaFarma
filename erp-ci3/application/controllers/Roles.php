<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Roles extends Web_Controller
{
    public function index(): void
    {
        $this->authorize('system.role.view');
        $this->render('roles/index', ['title' => 'Role & Hak Akses', 'roles' => $this->service(Role_service::class)->list()]);
    }

    public function create(): void
    {
        $this->authorize('system.role.create');
        if ($this->input->method() === 'post') {
            $this->handle(fn() => $this->service(Role_service::class)->save($this->input->post(null, false)), 'system/roles', 'Role berhasil dibuat');
            return;
        }
        $this->form(null);
    }

    public function edit(int $id): void
    {
        $this->authorize('system.role.edit');
        if ($this->input->method() === 'post') {
            $this->handle(fn() => $this->service(Role_service::class)->save($this->input->post(null, false), $id), 'system/roles', 'Role berhasil diperbarui');
            return;
        }
        $this->form($this->service(Role_service::class)->get($id));
    }

    private function form(?array $role): void
    {
        $this->render('roles/form', ['title' => $role ? 'Ubah Role' : 'Role Baru', 'role' => $role, 'matrix' => $this->service(Role_service::class)->permissionMatrix(),
            'modules' => $this->config->item('erp_modules'), 'actions' => $this->config->item('erp_actions')]);
    }
}
