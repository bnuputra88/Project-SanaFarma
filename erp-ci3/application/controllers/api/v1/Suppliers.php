<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Suppliers extends Api_Controller
{
    public function index(): void
    {
        $this->run(function () {
            $this->authorize('purchasing.supplier.view');
            $page = $this->service(Supplier_service::class)->list($this->input->get());
            $this->ok($page->items, $page->toArray()['meta']);
        });
    }

    public function show(int $id): void
    {
        $this->run(function () use ($id) {
            $this->authorize('purchasing.supplier.view');
            $this->ok($this->service(Supplier_service::class)->get($id));
        });
    }

    public function store(): void
    {
        $this->run(function () {
            $this->requireMethod('POST');
            $this->authorize('purchasing.supplier.create');
            $id = $this->service(Supplier_service::class)->save($this->body());
            $this->ok($this->service(Supplier_service::class)->get($id), [], 201);
        });
    }

    public function price_history(int $id): void
    {
        $this->run(function () use ($id) {
            $this->authorize('purchasing.supplier.view');
            $this->ok($this->service(Supplier_service::class)->priceHistory($id, $this->input->get('product_id') ? (int) $this->input->get('product_id') : null));
        });
    }
}
