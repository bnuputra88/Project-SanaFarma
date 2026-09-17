<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Purchasing extends Web_Controller
{
    public function dashboard(): void
    {
        $this->authorize('purchasing.po.view');
        $this->render('purchasing/dashboard', ['title' => 'Ringkasan Pembelian', 'data' => $this->service(Purchasing_dashboard_service::class)->summary()]);
    }
}
