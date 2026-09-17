<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Stock extends Api_Controller
{
    public function balances(): void
    {
        $this->run(function () {
            $this->authorize('inventory.stock.view');
            $page = $this->service(Stock_repository::class)->balances($this->input->get(), $this->context->company_id);
            $this->ok($page->items, $page->toArray()['meta']);
        });
    }

    public function fefo(): void
    {
        $this->run(function () {
            $this->authorize('inventory.stock.view');
            $this->ok($this->service(Inventory_service::class)->allocateFefo((int) $this->input->get('product_id'), (int) $this->input->get('warehouse_id'), (float) $this->input->get('qty')));
        });
    }

    public function ledger(): void
    {
        $this->run(function () {
            $this->authorize('inventory.stock.view');
            $page = $this->service(Stock_repository::class)->ledger($this->input->get(), $this->context->company_id);
            $this->ok($page->items, $page->toArray()['meta']);
        });
    }

    public function adjustments(): void
    {
        $this->run(function () {
            $svc = $this->service(Stock_adjustment_service::class);
            if ($this->input->method() === 'post') {
                $this->authorize('inventory.adjustment.create');
                $id = $svc->create($this->body());
                $this->ok($svc->get($id), [], 201);
                return;
            }
            $this->authorize('inventory.adjustment.view');
            $page = $svc->list($this->input->get());
            $this->ok($page->items, $page->toArray()['meta']);
        });
    }

    public function adjustment_action(int $id, string $action): void
    {
        $this->run(function () use ($id, $action) {
            $this->requireMethod('POST');
            $svc = $this->service(Stock_adjustment_service::class);
            $status = $svc->action($id, $action, $this->body()['notes'] ?? null);
            $this->ok(['id' => $id, 'status' => $status]);
        });
    }
}
