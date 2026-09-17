<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Ap_invoices extends Web_Controller
{
    public function index(): void
    {
        $this->authorize('purchasing.ap.view');
        $this->render('purchasing/ap_invoices/index', ['title' => 'Tagihan Supplier (AP)', 'page' => $this->service(Ap_invoice_service::class)->list($this->input->get())]);
    }

    public function show(int $id): void
    {
        $this->authorize('purchasing.ap.view');
        $this->render('purchasing/ap_invoices/show', ['title' => 'Tagihan Supplier', 'doc' => $this->service(Ap_invoice_service::class)->get($id)]);
    }

    public function create_from_gr(int $grId): void
    {
        $this->requirePost();
        $this->authorize('purchasing.ap.create');
        $this->handle(fn() => 'ap:' . $this->service(Ap_invoice_service::class)->createFromReceipt($grId, $this->input->post(null, false)), 'purchasing/ap-invoices', 'AP invoice dibuat dari GR');
    }

    public function cancel(int $id): void
    {
        $this->requirePost();
        $this->authorize('purchasing.ap.edit');
        $this->handle(fn() => $this->service(Ap_invoice_service::class)->cancel($id, (string) $this->input->post('reason')), 'purchasing/ap-invoices/' . $id, 'AP invoice dibatalkan');
    }
}
