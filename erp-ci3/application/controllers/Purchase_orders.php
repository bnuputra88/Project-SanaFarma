<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Purchase_orders extends Web_Controller
{
    public function index(): void
    {
        $this->authorize('purchasing.po.view');
        $this->render('purchasing/orders/index', ['title' => 'Pesanan Pembelian', 'page' => $this->service(Purchase_order_service::class)->list($this->input->get()),
            'suppliers' => $this->service(Supplier_service::class)->active()]);
    }

    public function create(): void
    {
        $this->authorize('purchasing.po.create');
        if ($this->input->method() === 'post') {
            $this->handle(fn() => $this->service(Purchase_order_service::class)->create($this->input->post(null, false)), 'purchasing/orders', 'PO dibuat (DRAFT)');
            return;
        }
        $prefill = null;
        if ($this->input->get('pr_id')) {
            $pr = $this->service(Purchase_request_service::class)->get((int) $this->input->get('pr_id'));
            $prefill = ['pr_id' => $pr['id'], 'warehouse_id' => $pr['warehouse_id'], 'notes' => 'Dari PR ' . $pr['pr_no'],
                'items' => array_map(fn($i) => ['product_id' => $i['product_id'], 'product_name' => $i['product_name'], 'sku' => $i['sku'], 'qty_ordered' => $i['qty']], $pr['items'])];
        }
        $this->render('purchasing/orders/form', ['title' => 'PO Baru', 'doc' => $prefill, 'suppliers' => $this->service(Supplier_service::class)->active(),
            'warehouses' => $this->service(Master_service::class)->activeWarehouses()]);
    }

    public function edit(int $id): void
    {
        $this->authorize('purchasing.po.edit');
        if ($this->input->method() === 'post') {
            $this->handle(fn() => $this->service(Purchase_order_service::class)->update($id, $this->input->post(null, false)), 'purchasing/orders/' . $id, 'PO diperbarui');
            return;
        }
        $this->render('purchasing/orders/form', ['title' => 'Ubah PO', 'doc' => $this->service(Purchase_order_service::class)->get($id),
            'suppliers' => $this->service(Supplier_service::class)->active(), 'warehouses' => $this->service(Master_service::class)->activeWarehouses()]);
    }

    public function show(int $id): void
    {
        $this->authorize('purchasing.po.view');
        $this->render('purchasing/orders/show', ['title' => 'Pesanan Pembelian', 'doc' => $this->service(Purchase_order_service::class)->get($id)]);
    }

    public function action(int $id, string $action): void
    {
        $this->requirePost();
        $this->handle(fn() => $this->service(Purchase_order_service::class)->action($id, $action, $this->input->post('notes')), 'purchasing/orders/' . $id, 'Aksi "' . $action . '" berhasil');
    }
}
