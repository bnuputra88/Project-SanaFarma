<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Users extends Web_Controller
{
    public function index(): void
    {
        $this->authorize('system.user.view');
        $this->render('users/index', ['title' => 'Pengguna', 'page' => $this->service(User_service::class)->list($this->input->get())]);
    }

    public function create(): void
    {
        $this->authorize('system.user.create');
        if ($this->input->method() === 'post') {
            $this->handle(fn() => $this->service(User_service::class)->create($this->input->post(null, false)), 'system/users', 'Pengguna berhasil dibuat');
            return;
        }
        $this->form(null);
    }

    public function edit(int $id): void
    {
        $this->authorize('system.user.edit');
        if ($this->input->method() === 'post') {
            $this->handle(fn() => $this->service(User_service::class)->update($id, $this->input->post(null, false)), 'system/users', 'Pengguna berhasil diperbarui');
            return;
        }
        $this->form($this->service(User_service::class)->get($id));
    }

    public function toggle(int $id): void
    {
        $this->requirePost();
        $this->authorize('system.user.edit');
        $this->handle(fn() => $this->service(User_service::class)->toggleActive($id), 'system/users', 'Status pengguna diperbarui');
    }

    private function form(?array $user): void
    {
        $this->render('users/form', ['title' => $user ? 'Ubah Pengguna' : 'Pengguna Baru', 'user' => $user,
            'roles' => $this->service(Role_service::class)->list(), 'branches' => $this->service(Master_service::class)->branches()]);
    }
}
