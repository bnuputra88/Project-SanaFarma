<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Purchase_requests extends Web_Controller
{
    public function index(): void
    {
        $this->authorize('purchasing.pr.view');
        $this->render('purchasing/requests/index', ['title' => 'Permintaan Pembelian', 'page' => $this->service(Purchase_request_service::class)->list($this->input->get())]);
    }

    public function create(): void
    {
        $this->authorize('purchasing.pr.create');
        if ($this->input->method() === 'post') {
            $this->handle(fn() => $this->service(Purchase_request_service::class)->create($this->input->post(null, false)), 'purchasing/requests', 'PR dibuat (DRAFT)');
            return;
        }
        $this->render('purchasing/requests/form', ['title' => 'PR Baru', 'doc' => null, 'warehouses' => $this->service(Master_service::class)->activeWarehouses()]);
    }

    public function edit(int $id): void
    {
        $this->authorize('purchasing.pr.edit');
        if ($this->input->method() === 'post') {
            $this->handle(fn() => $this->service(Purchase_request_service::class)->update($id, $this->input->post(null, false)), 'purchasing/requests/' . $id, 'PR diperbarui');
            return;
        }
        $this->render('purchasing/requests/form', ['title' => 'Ubah PR', 'doc' => $this->service(Purchase_request_service::class)->get($id), 'warehouses' => $this->service(Master_service::class)->activeWarehouses()]);
    }

    public function show(int $id): void
    {
        $this->authorize('purchasing.pr.view');
        $this->render('purchasing/requests/show', ['title' => 'Permintaan Pembelian', 'doc' => $this->service(Purchase_request_service::class)->get($id)]);
    }

    public function action(int $id, string $action): void
    {
        $this->requirePost();
        $this->handle(fn() => $this->service(Purchase_request_service::class)->action($id, $action, $this->input->post('notes')), 'purchasing/requests/' . $id, 'Aksi "' . $action . '" berhasil');
    }
}
