<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Cashier_shifts extends Web_Controller
{
    public function index(): void
    {
        $this->authorize('sales.shift.view');
        $this->render('sales/shifts/index', ['title' => 'Shift Kasir', 'page' => $this->service(Cashier_shift_service::class)->list($this->input->get()),
            'current' => $this->service(Cashier_shift_service::class)->currentOpen(), 'warehouses' => $this->service(Master_service::class)->activeWarehouses()]);
    }

    public function open(): void
    {
        $this->requirePost();
        $this->authorize('sales.shift.open');
        $this->handle(fn() => $this->service(Cashier_shift_service::class)->open($this->input->post(null, false)), 'sales/shifts', 'Shift dibuka');
    }

    public function close(int $id): void
    {
        $this->requirePost();
        $this->authorize('sales.shift.close');
        $this->handle(fn() => $this->service(Cashier_shift_service::class)->close($id, $this->input->post(null, false)), 'sales/shifts', 'Shift ditutup');
    }

    public function show(int $id): void
    {
        $this->authorize('sales.shift.view');
        $this->render('sales/shifts/show', ['title' => 'Detail Shift', 'shift' => $this->service(Cashier_shift_service::class)->get($id)]);
    }
}
