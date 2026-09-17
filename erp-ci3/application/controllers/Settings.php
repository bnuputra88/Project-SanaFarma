<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Settings extends Web_Controller
{
    public function index(): void
    {
        $this->authorize('system.setting.view');
        $this->render('settings/index', ['title' => 'Parameter Sistem', 'settings' => $this->service(Setting_service::class)->all()]);
    }

    public function save(): void
    {
        $this->requirePost();
        $this->authorize('system.setting.edit');
        $this->handle(fn() => $this->service(Setting_service::class)->saveMany($this->input->post('settings', false) ?: []), 'system/settings', 'Parameter tersimpan');
    }
}
