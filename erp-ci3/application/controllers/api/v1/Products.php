<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Products extends Api_Controller
{
    public function index(): void
    {
        $this->run(function () {
            $this->authorize('master.product.view');
            $page = $this->service(Product_service::class)->list($this->input->get());
            $this->ok($page->items, $page->toArray()['meta']);
        });
    }

    public function show(int $id): void
    {
        $this->run(function () {
            $this->authorize('master.product.view');
            $this->ok($this->service(Product_service::class)->get($id));
        });
    }
}
