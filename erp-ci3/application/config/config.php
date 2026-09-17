<?php
defined('BASEPATH') or exit('No direct script access allowed');

$config['base_url'] = Env::get('APP_URL', '');
$config['index_page'] = '';
$config['uri_protocol'] = 'REQUEST_URI';
$config['url_suffix'] = '';
$config['language'] = 'indonesian';
$config['charset'] = 'UTF-8';
$config['enable_hooks'] = true;
$config['subclass_prefix'] = 'MY_';
$config['composer_autoload'] = FCPATH . 'vendor/autoload.php';
$config['permitted_uri_chars'] = 'a-z 0-9~%.:_\-';
$config['allow_get_array'] = true;
$config['enable_query_strings'] = false;
$config['log_threshold'] = (int) Env::get('LOG_THRESHOLD', 1);
$config['log_path'] = '';
$config['log_file_extension'] = '';
$config['log_file_permissions'] = 0644;
$config['log_date_format'] = 'Y-m-d H:i:s';
$config['error_views_path'] = '';
$config['cache_path'] = '';
$config['cache_query_string'] = false;
$config['encryption_key'] = Env::required('APP_KEY');

// Session: database driver, secure defaults (fixation protection via regenerate on login + sess_regenerate_destroy)
$config['sess_driver'] = 'database';
$config['sess_cookie_name'] = 'pharmaerp_sess';
$config['sess_expiration'] = (int) Env::get('SESSION_TIMEOUT', 1800);
$config['sess_save_path'] = 'ci_sessions';
$config['sess_match_ip'] = false;
$config['sess_time_to_update'] = 300;
$config['sess_regenerate_destroy'] = true;

$config['cookie_prefix'] = '';
$config['cookie_domain'] = '';
$config['cookie_path'] = '/';
$config['cookie_secure'] = ENVIRONMENT === 'production';
$config['cookie_httponly'] = true;

$config['standardize_newlines'] = false;
$config['global_xss_filtering'] = false; // output escaping is done in views via e()
$config['csrf_protection'] = true;
$config['csrf_token_name'] = 'csrf_token';
$config['csrf_cookie_name'] = 'pharmaerp_csrf';
$config['csrf_expire'] = 7200;
$config['csrf_regenerate'] = false;
$config['csrf_exclude_uris'] = ['api/v1/.*'];

$config['compress_output'] = false;
$config['time_reference'] = 'local';
$config['rewrite_short_tags'] = false;
$config['proxy_ips'] = '';
