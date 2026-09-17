<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Purchase_returns extends Api_Controller
{
    public function index(): void
    {
        $this->run(function () {
            $svc = $this->service(Purchase_return_service::class);
            if ($this->input->method() === 'post') {
                $this->authorize('purchasing.return.create');
                $id = $svc->create($this->body());
                $this->ok($svc->get($id), [], 201);
                return;
            }
            $this->authorize('purchasing.return.view');
            $page = $svc->list($this->input->get());
            $this->ok($page->items, $page->toArray()['meta']);
        });
    }

    public function show(int $id): void
    {
        $this->run(function () use ($id) {
            $this->authorize('purchasing.return.view');
            $this->ok($this->service(Purchase_return_service::class)->get($id));
        });
    }

    public function action(int $id, string $action): void
    {
        $this->run(function () use ($id, $action) {
            $this->requireMethod('POST');
            $status = $this->service(Purchase_return_service::class)->action($id, $action, $this->body()['notes'] ?? null);
            $this->ok(['id' => $id, 'status' => $status]);
        });
    }
}
