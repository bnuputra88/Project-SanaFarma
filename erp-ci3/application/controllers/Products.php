<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Products extends Web_Controller
{
    public function index(): void
    {
        $this->authorize('master.product.view');
        $svc = $this->service(Product_service::class);
        $this->render('products/index', ['title' => 'Produk / Obat', 'page' => $svc->list($this->input->get()), 'refs' => $svc->references()]);
    }

    public function search(): void
    {
        $this->authorize('master.product.view');
        $this->json(['success' => true, 'data' => $this->service(Product_service::class)->search((string) $this->input->get('q'))]);
    }

    public function show(int $id): void
    {
        $this->authorize('master.product.view');
        $this->render('products/show', ['title' => 'Detail Produk', 'product' => $this->service(Product_service::class)->get($id),
            'batches' => $this->service(Batch_repository::class)->forProduct($id), 'audit' => $this->service(Audit_repository::class)->forEntity('products', (string) $id)]);
    }

    public function create(): void
    {
        $this->authorize('master.product.create');
        if ($this->input->method() === 'post') {
            $this->handle(fn() => $this->service(Product_service::class)->save($this->input->post(null, false)), 'master/products', 'Produk berhasil dibuat');
            return;
        }
        $this->form(null);
    }

    public function edit(int $id): void
    {
        $this->authorize('master.product.edit');
        if ($this->input->method() === 'post') {
            $this->handle(fn() => $this->service(Product_service::class)->save($this->input->post(null, false), $id), 'master/products/' . $id, 'Produk berhasil diperbarui');
            return;
        }
        $this->form($this->service(Product_service::class)->get($id));
    }

    public function toggle(int $id): void
    {
        $this->requirePost();
        $this->authorize('master.product.edit');
        $this->handle(fn() => $this->service(Product_service::class)->toggleStatus($id), 'master/products', 'Status produk diperbarui');
    }

    private function form(?array $product): void
    {
        $this->render('products/form', ['title' => $product ? 'Ubah Produk' : 'Produk Baru', 'product' => $product, 'refs' => $this->service(Product_service::class)->references()]);
    }
}
