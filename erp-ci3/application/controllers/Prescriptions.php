<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Prescriptions extends Web_Controller
{
    public function index(): void
    {
        $this->authorize('pharmacy.prescription.view');
        $this->render('pharmacy/prescriptions/index', ['title' => 'Resep', 'page' => $this->service(Prescription_service::class)->list($this->input->get())]);
    }

    public function create(): void
    {
        $this->authorize('pharmacy.prescription.create');
        if ($this->input->method() === 'post') {
            $this->handle(fn() => $this->service(Prescription_service::class)->create($this->input->post(null, false)), 'farmasi/resep', 'Resep dibuat (DRAFT)');
            return;
        }
        $this->render('pharmacy/prescriptions/form', ['title' => 'Resep Baru', 'rx' => null, 'warehouses' => $this->service(Master_service::class)->activeWarehouses()]);
    }

    public function edit(int $id): void
    {
        $this->authorize('pharmacy.prescription.edit');
        if ($this->input->method() === 'post') {
            $this->handle(fn() => $this->service(Prescription_service::class)->update($id, $this->input->post(null, false)), 'farmasi/resep/' . $id, 'Resep diperbarui');
            return;
        }
        $this->render('pharmacy/prescriptions/form', ['title' => 'Ubah Resep', 'rx' => $this->service(Prescription_service::class)->get($id), 'warehouses' => $this->service(Master_service::class)->activeWarehouses()]);
    }

    public function show(int $id): void
    {
        $this->authorize('pharmacy.prescription.view');
        $this->render('pharmacy/prescriptions/show', ['title' => 'Resep', 'rx' => $this->service(Prescription_service::class)->get($id)]);
    }

    public function verify(int $id): void
    {
        $this->requirePost();
        $this->handle(fn() => $this->service(Prescription_service::class)->verify($id, $this->input->post('notes')), 'farmasi/resep/' . $id, 'Resep terverifikasi');
    }

    public function dispense(int $id): void
    {
        $this->requirePost();
        $this->authorize('pharmacy.prescription.dispense');
        $this->handle(fn() => 'sale:' . $this->service(Prescription_service::class)->dispense($id, $this->input->post(null, false)), 'farmasi/resep/' . $id, 'Obat diserahkan (DISPENSED)');
    }

    public function cancel(int $id): void
    {
        $this->requirePost();
        $this->handle(fn() => $this->service(Prescription_service::class)->cancel($id, $this->input->post('notes')), 'farmasi/resep/' . $id, 'Resep dibatalkan');
    }

    public function etiket(int $id): void
    {
        $this->authorize('pharmacy.prescription.print');
        $svc = $this->service(Prescription_service::class);
        $this->load->view('pharmacy/prescriptions/etiket', ['rx' => $svc->get($id), 'apotek' => $svc->apotekInfo()]);
    }
}
