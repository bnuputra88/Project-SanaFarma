<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Stock_adjustments extends Web_Controller
{
    public function index(): void
    {
        $this->authorize('inventory.adjustment.view');
        $this->render('inventory/adjustments/index', ['title' => 'Penyesuaian Stok', 'page' => $this->service(Stock_adjustment_service::class)->list($this->input->get())]);
    }

    public function create(): void
    {
        $this->authorize('inventory.adjustment.create');
        if ($this->input->method() === 'post') {
            $this->handle(fn() => $this->service(Stock_adjustment_service::class)->create($this->input->post(null, false)), 'inventory/adjustments', 'Penyesuaian dibuat (DRAFT)');
            return;
        }
        $this->form(null);
    }

    public function edit(int $id): void
    {
        $this->authorize('inventory.adjustment.edit');
        if ($this->input->method() === 'post') {
            $this->handle(fn() => $this->service(Stock_adjustment_service::class)->update($id, $this->input->post(null, false)), 'inventory/adjustments/' . $id, 'Penyesuaian diperbarui');
            return;
        }
        $this->form($this->service(Stock_adjustment_service::class)->get($id));
    }

    public function show(int $id): void
    {
        $this->authorize('inventory.adjustment.view');
        $this->render('inventory/adjustments/show', ['title' => 'Penyesuaian Stok', 'doc' => $this->service(Stock_adjustment_service::class)->get($id), 'doc_type' => 'adjustment', 'base' => 'inventory/adjustments']);
    }

    public function action(int $id, string $action): void
    {
        $this->requirePost();
        $this->handle(fn() => $this->service(Stock_adjustment_service::class)->action($id, $action, $this->input->post('notes')), 'inventory/adjustments/' . $id, 'Aksi "' . $action . '" berhasil');
    }

    private function form(?array $doc): void
    {
        $svc = $this->service(Master_service::class);
        $this->render('inventory/adjustments/form', ['title' => $doc ? 'Ubah Penyesuaian' : 'Penyesuaian Baru', 'doc' => $doc, 'warehouses' => $svc->activeWarehouses(),
            'reasons' => $svc->reasonCodes('ADJUSTMENT', true), 'conditions' => $this->config->item('erp_stock_conditions')]);
    }
}
