<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Product_categories extends Web_Controller
{
    public function index(): void
    {
        $this->authorize('master.category.view');
        $this->render('master/categories', ['title' => 'Kategori Produk', 'rows' => $this->service(Product_repository::class)->categories($this->context->company_id)]);
    }

    public function save(): void
    {
        $this->requirePost();
        $this->authorize($this->input->post('id') ? 'master.category.edit' : 'master.category.create');
        $this->handle(fn() => $this->service(Master_service::class)->saveCategory($this->input->post(null, false)), 'master/categories', lang('saved'));
    }
}
