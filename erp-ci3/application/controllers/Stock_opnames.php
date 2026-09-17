<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Stock_opnames extends Web_Controller
{
    public function index(): void
    {
        $this->authorize('inventory.opname.view');
        $this->render('inventory/opnames/index', ['title' => 'Stock Opname', 'page' => $this->service(Stock_opname_service::class)->list($this->input->get())]);
    }

    public function create(): void
    {
        $this->authorize('inventory.opname.create');
        if ($this->input->method() === 'post') {
            $this->handle(fn() => $this->service(Stock_opname_service::class)->create($this->input->post(null, false)), 'inventory/opnames', 'Opname dibuat (DRAFT)');
            return;
        }
        $this->render('inventory/opnames/form', ['title' => 'Opname Baru', 'warehouses' => $this->service(Master_service::class)->activeWarehouses()]);
    }

    public function show(int $id): void
    {
        $this->authorize('inventory.opname.view');
        $this->render('inventory/opnames/show', ['title' => 'Stock Opname', 'doc' => $this->service(Stock_opname_service::class)->get($id)]);
    }

    public function count(int $id): void
    {
        $this->requirePost();
        $this->authorize('inventory.opname.edit');
        $this->handle(fn() => $this->service(Stock_opname_service::class)->saveCounts($id, $this->input->post('counts') ?: []), 'inventory/opnames/' . $id, 'Hasil hitung tersimpan');
    }

    public function action(int $id, string $action): void
    {
        $this->requirePost();
        $this->handle(fn() => $this->service(Stock_opname_service::class)->action($id, $action, $this->input->post('notes')), 'inventory/opnames/' . $id, 'Aksi "' . $action . '" berhasil');
    }
}
