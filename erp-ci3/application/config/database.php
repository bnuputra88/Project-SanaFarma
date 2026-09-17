<?php
defined('BASEPATH') or exit('No direct script access allowed');

$active_group = 'default';
$query_builder = true;

$db['default'] = [
    'dsn' => '',
    'hostname' => Env::required('DB_HOST'),
    'port' => (int) Env::get('DB_PORT', 3306),
    'username' => Env::required('DB_USER'),
    'password' => (string) Env::required('DB_PASSWORD'),
    'database' => Env::required('DB_NAME'),
    'dbdriver' => 'mysqli',
    'dbprefix' => '',
    'pconnect' => false,
    'db_debug' => ENVIRONMENT === 'development',
    'cache_on' => false,
    'cachedir' => '',
    'char_set' => 'utf8mb4',
    'dbcollat' => 'utf8mb4_unicode_ci',
    'swap_pre' => '',
    'encrypt' => false,
    'compress' => false,
    'stricton' => true,
    'failover' => [],
    'save_queries' => ENVIRONMENT === 'development',
];
