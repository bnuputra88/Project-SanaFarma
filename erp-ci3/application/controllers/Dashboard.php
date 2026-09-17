<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Dashboard extends Web_Controller
{
    public function index(): void
    {
        $this->authorize('system.dashboard.view');
        $this->render('dashboard/index', ['title' => 'Dashboard', 'kpi' => $this->service(Dashboard_service::class)->kpis()]);
    }
}
