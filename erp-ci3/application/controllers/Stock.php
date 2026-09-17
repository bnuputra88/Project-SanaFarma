<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Stock extends Web_Controller
{
    public function balances(): void
    {
        $this->authorize('inventory.stock.view');
        $this->render('inventory/balances', ['title' => 'Saldo Stok', 'page' => $this->service(Stock_repository::class)->balances($this->input->get(), $this->context->company_id),
            'warehouses' => $this->service(Master_service::class)->activeWarehouses()]);
    }

    public function expiry(): void
    {
        $this->authorize('inventory.stock.view');
        $input = $this->input->get() + ['expiring_days' => 90, 'sort' => 'expiry_date'];
        $this->render('inventory/balances', ['title' => 'Stok Mendekati / Kedaluwarsa', 'page' => $this->service(Stock_repository::class)->balances($input, $this->context->company_id),
            'warehouses' => $this->service(Master_service::class)->activeWarehouses(), 'expiry_mode' => true]);
    }

    public function card(int $productId): void
    {
        $this->authorize('inventory.stock.view');
        $input = $this->input->get() + ['product_id' => $productId];
        $this->render('inventory/card', ['title' => 'Kartu Stok', 'product' => $this->service(Product_service::class)->get($productId),
            'page' => $this->service(Stock_repository::class)->ledger($input, $this->context->company_id), 'warehouses' => $this->service(Master_service::class)->activeWarehouses()]);
    }

    public function ledger(): void
    {
        $this->authorize('inventory.stock.view');
        $this->render('inventory/ledger', ['title' => 'Buku Besar Stok', 'page' => $this->service(Stock_repository::class)->ledger($this->input->get(), $this->context->company_id),
            'warehouses' => $this->service(Master_service::class)->activeWarehouses()]);
    }

    public function movements(): void
    {
        $this->authorize('inventory.movement.view');
        $this->render('inventory/movements', ['title' => 'Mutasi Stok', 'page' => $this->service(Stock_repository::class)->movements($this->input->get(), $this->context->company_id),
            'types' => $this->config->item('erp_movement_types')]);
    }

    public function movement(int $id): void
    {
        $this->authorize('inventory.movement.view');
        $repo = $this->service(Stock_repository::class);
        $m = $repo->movement($id);
        if (!$m || (int) $m['company_id'] !== $this->context->company_id) {
            throw new Not_found_exception();
        }
        $this->render('inventory/movement', ['title' => 'Mutasi ' . $m['movement_no'], 'movement' => $m, 'items' => $repo->movementItems($id),
            'reasons' => $this->service(Master_service::class)->reasonCodes('REVERSAL', true), 'types' => $this->config->item('erp_movement_types')]);
    }

    public function reverse(int $id): void
    {
        $this->requirePost();
        $this->authorize('inventory.movement.reverse');
        $this->handle(fn() => $this->service(Inventory_service::class)->reverse($id, (string) $this->input->post('reason'), $this->input->post('reason_code_id') ? (int) $this->input->post('reason_code_id') : null),
            'inventory/movements/' . $id, 'Mutasi berhasil dibalik');
    }

    /** AJAX: FEFO preview for a product/warehouse/qty (used by forms and future POS). */
    public function fefo(): void
    {
        $this->authorize('inventory.stock.view');
        $this->json(['success' => true, 'data' => $this->service(Inventory_service::class)->allocateFefo((int) $this->input->get('product_id'), (int) $this->input->get('warehouse_id'), (float) $this->input->get('qty'))]);
    }
}
