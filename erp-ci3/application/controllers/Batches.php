<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Batches extends Web_Controller
{
    public function index(): void
    {
        $this->authorize('inventory.batch.view');
        $this->render('inventory/batches', ['title' => 'Batch & Kedaluwarsa', 'page' => $this->service(Batch_repository::class)->paginate($this->input->get(), $this->context->company_id)]);
    }

    public function quarantine(int $id): void
    {
        $this->requirePost();
        $this->authorize('inventory.batch.quarantine');
        $this->handle(fn() => $this->service(Inventory_service::class)->setBatchCondition($id, 'QUARANTINE', (string) $this->input->post('reason')), 'inventory/batches', 'Batch dikarantina');
    }

    public function release(int $id): void
    {
        $this->requirePost();
        $this->authorize('inventory.batch.quarantine');
        $this->handle(fn() => $this->service(Inventory_service::class)->setBatchCondition($id, 'GOOD', (string) $this->input->post('reason')), 'inventory/batches', 'Batch dilepas dari karantina');
    }
}
