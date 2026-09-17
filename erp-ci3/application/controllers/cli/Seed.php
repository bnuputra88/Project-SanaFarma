<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * php index.php cli/seed run  — idempotent demo/reference data (dummy only, no real patient data).
 * Runs through the real services (Inventory_service etc.) so seed data obeys business rules & audit trail.
 */
class Seed extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        if (!is_cli()) {
            show_404();
        }
    }

    public function index(string $cmd = 'run'): void
    {
        if ($cmd !== 'run') {
            echo "Usage: php index.php cli/seed run\n";
            return;
        }
        $seeder = $this->container->get(Demo_seeder::class);
        $seeder->run(fn($m) => print($m . PHP_EOL));
    }
}
