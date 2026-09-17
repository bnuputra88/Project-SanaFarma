<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Suppliers extends Web_Controller
{
    public function index(): void
    {
        $this->authorize('purchasing.supplier.view');
        $this->render('purchasing/suppliers/index', ['title' => 'Supplier', 'page' => $this->service(Supplier_service::class)->list($this->input->get())]);
    }

    public function create(): void
    {
        $this->authorize('purchasing.supplier.create');
        if ($this->input->method() === 'post') {
            $this->handle(fn() => $this->service(Supplier_service::class)->save($this->input->post(null, false)), 'purchasing/suppliers', 'Supplier disimpan');
            return;
        }
        $this->render('purchasing/suppliers/form', ['title' => 'Supplier Baru', 'supplier' => null]);
    }

    public function edit(int $id): void
    {
        $this->authorize('purchasing.supplier.edit');
        if ($this->input->method() === 'post') {
            $post = $this->input->post(null, false);
            $post['id'] = $id;
            $this->handle(fn() => $this->service(Supplier_service::class)->save($post), 'purchasing/suppliers/' . $id, 'Supplier diperbarui');
            return;
        }
        $this->render('purchasing/suppliers/form', ['title' => 'Ubah Supplier', 'supplier' => $this->service(Supplier_service::class)->get($id)]);
    }

    public function show(int $id): void
    {
        $this->authorize('purchasing.supplier.view');
        $this->render('purchasing/suppliers/show', ['title' => 'Detail Supplier', 'supplier' => $this->service(Supplier_service::class)->get($id)]);
    }
}
