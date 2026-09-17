<?php
defined('BASEPATH') or exit('No direct script access allowed');

/** php index.php cli/migrate latest | version N | status */
class Migrate extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        if (!is_cli()) {
            show_404();
        }
        $this->load->library('migration');
    }

    public function index(string $cmd = 'latest', ?int $version = null): void
    {
        if ($cmd === 'status') {
            echo 'Current schema version: ' . (int) $this->db->select('version')->from('schema_migrations')->get()->row('version') . PHP_EOL;
            return;
        }
        $ok = $cmd === 'version' ? $this->migration->version((int) $version) : $this->migration->latest();
        if ($ok === false) {
            fwrite(STDERR, 'MIGRATION FAILED: ' . $this->migration->error_string() . PHP_EOL);
            exit(1);
        }
        echo "Migration OK (version {$ok})" . PHP_EOL;
    }
}
