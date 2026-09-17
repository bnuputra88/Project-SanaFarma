<?php
defined('BASEPATH') or exit('No direct script access allowed');

$config['migration_enabled'] = true;
$config['migration_type'] = 'sequential';
$config['migration_table'] = 'schema_migrations';
$config['migration_auto_latest'] = false;
$config['migration_version'] = 5;
$config['migration_path'] = APPPATH . 'migrations/';
