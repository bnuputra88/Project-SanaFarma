<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Health extends Api_Controller
{
    protected $requireAuth = false;

    public function index(): void
    {
        $this->run(function () {
            $dbOk = (bool) $this->db->query('SELECT 1 AS ok')->row();
            $this->ok(['status' => $dbOk ? 'ok' : 'degraded', 'database' => $dbOk ? 'up' : 'down', 'time' => date('c'), 'version' => '0.2.0'], [], $dbOk ? 200 : 503);
        });
    }
}
