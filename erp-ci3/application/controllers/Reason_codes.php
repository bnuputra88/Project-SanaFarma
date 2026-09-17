<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Reason_codes extends Web_Controller
{
    public function index(): void
    {
        $this->authorize('master.reason_code.view');
        $this->render('master/reason_codes', ['title' => 'Kode Alasan', 'rows' => $this->service(Master_service::class)->reasonCodes()]);
    }

    public function save(): void
    {
        $this->requirePost();
        $this->authorize($this->input->post('id') ? 'master.reason_code.edit' : 'master.reason_code.create');
        $this->handle(fn() => $this->service(Master_service::class)->saveReasonCode($this->input->post(null, false)), 'master/reason-codes', lang('saved'));
    }
}
