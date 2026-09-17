<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Uoms extends Web_Controller
{
    public function index(): void
    {
        $this->authorize('master.uom.view');
        $this->render('master/uoms', ['title' => 'Satuan (UOM)', 'rows' => $this->service(Product_repository::class)->uoms()]);
    }

    public function save(): void
    {
        $this->requirePost();
        $this->authorize($this->input->post('id') ? 'master.uom.edit' : 'master.uom.create');
        $this->handle(fn() => $this->service(Master_service::class)->saveUom($this->input->post(null, false)), 'master/uoms', lang('saved'));
    }
}
