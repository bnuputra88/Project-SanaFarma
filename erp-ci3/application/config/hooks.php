<?php
defined('BASEPATH') or exit('No direct script access allowed');

$hook['pre_system'][] = ['class' => 'Bootstrap_hook', 'function' => 'run', 'filename' => 'Bootstrap_hook.php', 'filepath' => 'hooks'];
$hook['post_controller_constructor'][] = ['class' => 'Security_headers_hook', 'function' => 'run', 'filename' => 'Security_headers_hook.php', 'filepath' => 'hooks'];
