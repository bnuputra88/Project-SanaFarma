<?php
defined('BASEPATH') or exit('No direct script access allowed');

/** php index.php cli/health — exit 0 when healthy, 1 otherwise. For k8s/docker healthchecks and monitoring. */
class Health extends CI_Controller
{
    public function index(): void
    {
        if (!is_cli()) {
            show_404();
        }
        $checks = [];
        try {
            $this->db->query('SELECT 1');
            $checks['database'] = 'ok';
            $checks['schema_version'] = (int) $this->db->select('version')->from('schema_migrations')->get()->row('version');
        } catch (Throwable $e) {
            $checks['database'] = 'FAIL: ' . $e->getMessage();
        }
        $checks['logs_writable'] = is_writable(APPPATH . 'logs') ? 'ok' : 'FAIL';
        $checks['env'] = ENVIRONMENT;
        $checks['jwt_secret_set'] = strlen((string) Env::get('JWT_SECRET', '')) >= 32 ? 'ok' : 'FAIL';
        foreach ($checks as $k => $v) {
            echo str_pad($k, 18) . ': ' . $v . PHP_EOL;
        }
        exit(in_array('FAIL', array_map(fn($v) => strpos((string) $v, 'FAIL') === 0 ? 'FAIL' : 'ok', $checks), true) ? 1 : 0);
    }
}
