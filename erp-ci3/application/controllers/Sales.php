<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Sales extends Web_Controller
{
    public function index(): void
    {
        $this->authorize('sales.pos.view');
        $this->render('sales/pos/index', ['title' => 'Penjualan (POS)', 'page' => $this->service(Pos_sale_service::class)->list($this->input->get())]);
    }

    public function create(): void
    {
        $this->authorize('sales.pos.create');
        if ($this->input->method() === 'post') {
            $this->handle(fn() => 'sale:' . $this->service(Pos_sale_service::class)->checkout($this->input->post(null, false)), 'sales/pos', 'Transaksi berhasil (LUNAS)');
            return;
        }
        $this->render('sales/pos/form', ['title' => 'POS — Transaksi Baru', 'warehouses' => $this->service(Master_service::class)->activeWarehouses(),
            'current' => $this->service(Cashier_shift_service::class)->currentOpen()]);
    }

    public function show(int $id): void
    {
        $this->authorize('sales.pos.view');
        $this->render('sales/pos/show', ['title' => 'Struk Penjualan', 'sale' => $this->service(Pos_sale_service::class)->get($id)]);
    }

    public function void(int $id): void
    {
        $this->requirePost();
        $this->authorize('sales.pos.void');
        $this->handle(fn() => $this->service(Pos_sale_service::class)->void($id, (string) $this->input->post('reason')), 'sales/pos/' . $id, 'Penjualan di-void');
    }
}
