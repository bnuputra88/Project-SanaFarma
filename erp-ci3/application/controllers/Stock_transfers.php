<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Stock_transfers extends Web_Controller
{
    public function index(): void
    {
        $this->authorize('inventory.transfer.view');
        $this->render('inventory/transfers/index', ['title' => 'Transfer Stok', 'page' => $this->service(Stock_transfer_service::class)->list($this->input->get())]);
    }

    public function create(): void
    {
        $this->authorize('inventory.transfer.create');
        if ($this->input->method() === 'post') {
            $this->handle(fn() => $this->service(Stock_transfer_service::class)->create($this->input->post(null, false)), 'inventory/transfers', 'Transfer dibuat (DRAFT)');
            return;
        }
        $this->render('inventory/transfers/form', ['title' => 'Transfer Baru', 'warehouses' => $this->service(Master_service::class)->activeWarehouses()]);
    }

    public function show(int $id): void
    {
        $this->authorize('inventory.transfer.view');
        $this->render('inventory/transfers/show', ['title' => 'Transfer Stok', 'doc' => $this->service(Stock_transfer_service::class)->get($id)]);
    }

    public function action(int $id, string $action): void
    {
        $this->requirePost();
        $this->handle(fn() => $this->service(Stock_transfer_service::class)->action($id, $action, $this->input->post('notes'), $this->input->post('received') ?: []), 'inventory/transfers/' . $id, 'Aksi "' . $action . '" berhasil');
    }
}
