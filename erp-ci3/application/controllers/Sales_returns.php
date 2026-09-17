<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Sales_returns extends Web_Controller
{
    public function index(): void
    {
        $this->authorize('sales.return.view');
        $this->render('sales/returns/index', ['title' => 'Retur Penjualan', 'page' => $this->service(Sales_return_service::class)->list($this->input->get())]);
    }

    public function create(): void
    {
        $this->authorize('sales.return.create');
        if ($this->input->method() === 'post') {
            $this->handle(fn() => $this->service(Sales_return_service::class)->create($this->input->post(null, false)), 'sales/returns', 'Retur dibuat (DRAFT)');
            return;
        }
        $this->render('sales/returns/form', ['title' => 'Retur Penjualan Baru', 'doc' => null, 'warehouses' => $this->service(Master_service::class)->activeWarehouses(),
            'reasons' => $this->service(Master_service::class)->reasonCodes('RETURN', true), 'conditions' => $this->config->item('erp_stock_conditions')]);
    }

    public function show(int $id): void
    {
        $this->authorize('sales.return.view');
        $this->render('sales/returns/show', ['title' => 'Retur Penjualan', 'doc' => $this->service(Sales_return_service::class)->get($id)]);
    }

    public function action(int $id, string $action): void
    {
        $this->requirePost();
        $this->handle(fn() => $this->service(Sales_return_service::class)->action($id, $action, $this->input->post('notes')), 'sales/returns/' . $id, 'Aksi "' . $action . '" berhasil');
    }
}
