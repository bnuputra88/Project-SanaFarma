<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Audit_logs extends Web_Controller
{
    public function index(): void
    {
        $this->authorize('audit.log.view');
        $this->render('audit/index', ['title' => 'Audit Trail', 'page' => $this->service(Audit_repository::class)->paginate($this->input->get(), $this->context->company_id),
            'modules' => $this->config->item('erp_modules')]);
    }

    public function show(int $id): void
    {
        $this->authorize('audit.log.view');
        $row = $this->service(Audit_repository::class)->findOrFail($id);
        if ((int) $row['company_id'] !== $this->context->company_id) {
            throw new Not_found_exception();
        }
        $this->render('audit/show', ['title' => 'Detail Audit #' . $id, 'row' => $row]);
    }

    public function login_history(): void
    {
        $this->authorize('audit.login_history.view');
        $this->render('audit/login_history', ['title' => 'Riwayat Login', 'page' => $this->service(Audit_repository::class)->loginHistory($this->input->get(), $this->context->company_id)]);
    }
}
