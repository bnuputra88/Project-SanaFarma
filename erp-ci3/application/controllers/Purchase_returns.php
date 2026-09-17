<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Purchase_returns extends Web_Controller
{
    public function index(): void
    {
        $this->authorize('purchasing.return.view');
        $this->render('purchasing/returns/index', ['title' => 'Retur Pembelian', 'page' => $this->service(Purchase_return_service::class)->list($this->input->get()),
            'suppliers' => $this->service(Supplier_service::class)->active()]);
    }

    public function create(): void
    {
        $this->authorize('purchasing.return.create');
        if ($this->input->method() === 'post') {
            $this->handle(fn() => $this->service(Purchase_return_service::class)->create($this->input->post(null, false)), 'purchasing/returns', 'Retur dibuat (DRAFT)');
            return;
        }
        $this->render('purchasing/returns/form', ['title' => 'Retur Baru', 'doc' => null, 'suppliers' => $this->service(Supplier_service::class)->active(),
            'warehouses' => $this->service(Master_service::class)->activeWarehouses(), 'reasons' => $this->service(Master_service::class)->reasonCodes('RETURN', true), 'conditions' => $this->config->item('erp_stock_conditions')]);
    }

    public function edit(int $id): void
    {
        $this->authorize('purchasing.return.edit');
        if ($this->input->method() === 'post') {
            $this->handle(fn() => $this->service(Purchase_return_service::class)->update($id, $this->input->post(null, false)), 'purchasing/returns/' . $id, 'Retur diperbarui');
            return;
        }
        $this->render('purchasing/returns/form', ['title' => 'Ubah Retur', 'doc' => $this->service(Purchase_return_service::class)->get($id), 'suppliers' => $this->service(Supplier_service::class)->active(),
            'warehouses' => $this->service(Master_service::class)->activeWarehouses(), 'reasons' => $this->service(Master_service::class)->reasonCodes('RETURN', true), 'conditions' => $this->config->item('erp_stock_conditions')]);
    }

    public function show(int $id): void
    {
        $this->authorize('purchasing.return.view');
        $this->render('purchasing/returns/show', ['title' => 'Retur Pembelian', 'doc' => $this->service(Purchase_return_service::class)->get($id)]);
    }

    public function action(int $id, string $action): void
    {
        $this->requirePost();
        $this->handle(fn() => $this->service(Purchase_return_service::class)->action($id, $action, $this->input->post('notes')), 'purchasing/returns/' . $id, 'Aksi "' . $action . '" berhasil');
    }
}
