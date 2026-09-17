<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Goods_receipts extends Web_Controller
{
    public function index(): void
    {
        $this->authorize('purchasing.gr.view');
        $this->render('purchasing/receipts/index', ['title' => 'Penerimaan Barang', 'page' => $this->service(Goods_receipt_service::class)->list($this->input->get()),
            'suppliers' => $this->service(Supplier_service::class)->active()]);
    }

    public function create(): void
    {
        $this->authorize('purchasing.gr.create');
        if ($this->input->method() === 'post') {
            $this->handle(fn() => $this->service(Goods_receipt_service::class)->create($this->input->post(null, false)), 'purchasing/receipts', 'GR dibuat (DRAFT)');
            return;
        }
        $this->render('purchasing/receipts/form', ['title' => 'GR Baru', 'doc' => null, 'suppliers' => $this->service(Supplier_service::class)->active(),
            'warehouses' => $this->service(Master_service::class)->activeWarehouses(), 'orders' => $this->service(Purchase_order_service::class)->openOrders()]);
    }

    public function edit(int $id): void
    {
        $this->authorize('purchasing.gr.edit');
        if ($this->input->method() === 'post') {
            $this->handle(fn() => $this->service(Goods_receipt_service::class)->update($id, $this->input->post(null, false)), 'purchasing/receipts/' . $id, 'GR diperbarui');
            return;
        }
        $this->render('purchasing/receipts/form', ['title' => 'Ubah GR', 'doc' => $this->service(Goods_receipt_service::class)->get($id),
            'suppliers' => $this->service(Supplier_service::class)->active(), 'warehouses' => $this->service(Master_service::class)->activeWarehouses(), 'orders' => $this->service(Purchase_order_service::class)->openOrders()]);
    }

    public function show(int $id): void
    {
        $this->authorize('purchasing.gr.view');
        $this->render('purchasing/receipts/show', ['title' => 'Penerimaan Barang', 'doc' => $this->service(Goods_receipt_service::class)->get($id)]);
    }

    public function action(int $id, string $action): void
    {
        $this->requirePost();
        $this->handle(fn() => $this->service(Goods_receipt_service::class)->action($id, $action, $this->input->post('notes')), 'purchasing/receipts/' . $id, 'Aksi "' . $action . '" berhasil');
    }
}
