<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Customers extends Web_Controller
{
    public function index(): void
    {
        $this->authorize('sales.customer.view');
        $this->render('sales/customers/index', ['title' => 'Pelanggan / Pasien', 'page' => $this->service(Customer_service::class)->list($this->input->get())]);
    }

    public function create(): void
    {
        $this->authorize('sales.customer.create');
        if ($this->input->method() === 'post') {
            $this->handle(fn() => $this->service(Customer_service::class)->save($this->input->post(null, false)), 'sales/customers', 'Pelanggan disimpan');
            return;
        }
        $this->render('sales/customers/form', ['title' => 'Pelanggan Baru', 'customer' => null]);
    }

    public function edit(int $id): void
    {
        $this->authorize('sales.customer.edit');
        if ($this->input->method() === 'post') {
            $post = $this->input->post(null, false);
            $post['id'] = $id;
            $this->handle(fn() => $this->service(Customer_service::class)->save($post), 'sales/customers', 'Pelanggan diperbarui');
            return;
        }
        $this->render('sales/customers/form', ['title' => 'Ubah Pelanggan', 'customer' => $this->service(Customer_service::class)->get($id)]);
    }

    public function search(): void
    {
        $this->authorize('sales.customer.view');
        $this->json(['success' => true, 'data' => $this->service(Customer_service::class)->search((string) $this->input->get('q'))]);
    }
}
