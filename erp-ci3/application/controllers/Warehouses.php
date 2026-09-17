<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Warehouses extends Web_Controller
{
    public function index(): void
    {
        $this->authorize('master.warehouse.view');
        $svc = $this->service(Master_service::class);
        $this->render('master/warehouses', ['title' => 'Gudang', 'rows' => $svc->warehouses(), 'branches' => $svc->branches()]);
    }

    public function save(): void
    {
        $this->requirePost();
        $this->authorize($this->input->post('id') ? 'master.warehouse.edit' : 'master.warehouse.create');
        $this->handle(fn() => $this->service(Master_service::class)->saveWarehouse($this->input->post(null, false)), 'master/warehouses', lang('saved'));
    }

    public function locations(int $id): void
    {
        $this->authorize('master.warehouse.view');
        $svc = $this->service(Master_service::class);
        if ($this->isAjax()) {
            $this->json(['success' => true, 'data' => $svc->locations($id, true)]);
            return;
        }
        $this->render('master/locations', ['title' => 'Lokasi Gudang', 'warehouse' => $svc->warehouse($id), 'rows' => $svc->locations($id)]);
    }

    public function save_location(int $id): void
    {
        $this->requirePost();
        $this->authorize('master.warehouse.edit');
        $this->handle(fn() => $this->service(Master_service::class)->saveLocation($id, $this->input->post(null, false)), "master/warehouses/$id/locations", lang('saved'));
    }
}
