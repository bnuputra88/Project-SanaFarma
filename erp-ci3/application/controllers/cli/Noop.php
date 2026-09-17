<?php
defined('BASEPATH') or exit('No direct script access allowed');

/** No-op CLI entry used by the PHPUnit integration bootstrap to obtain a fully booted CI instance. */
class Noop extends MY_Controller
{
    public function index(): void
    {
        if (!is_cli()) {
            show_404();
        }
    }
}
