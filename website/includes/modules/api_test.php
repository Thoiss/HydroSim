<?php
if ($action === 'api_test') {
    if (isset($_GET['show_session'])) {
        echo json_encode([
            'session' => $_SESSION,
            'session_id' => session_id(),
            'session_name' => session_name(),
            'session_save_path' => session_save_path(),
            'session_cookie_params' => session_get_cookie_params(),
            'session_status' => session_status(),
            'timestamp' => time(),
            'server_time' => date('Y-m-d H:i:s'),
            'timezone' => date_default_timezone_get(),
            'cookies' => $_COOKIE ?? [],
            'request_headers' => getallheaders()
        ]);
        exit;
    }
    
    if (isset($_GET['show_config'])) {
        echo json_encode([
            'php_version' => phpversion(),
            'zend_version' => zend_version(),
            'include_path' => get_include_path(),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'max_input_time' => ini_get('max_input_time'),
            'display_errors' => ini_get('display_errors'),
            'error_reporting' => ini_get('error_reporting'),
            'log_errors' => ini_get('log_errors'),
            'error_log' => ini_get('error_log'),
            'allow_url_fopen' => ini_get('allow_url_fopen'),
            'allow_url_include' => ini_get('allow_url_include'),
            'disable_functions' => ini_get('disable_functions'),
            'open_basedir' => ini_get('open_basedir'),
            'session_save_path' => ini_get('session.save_path'),
            'session_name' => ini_get('session.name'),
            'session_cookie_httponly' => ini_get('session.cookie_httponly'),
            'session_cookie_secure' => ini_get('session.cookie_secure'),
            'extension_dir' => ini_get('extension_dir'),
            'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? '',
            'script_filename' => $_SERVER['SCRIPT_FILENAME'] ?? '',
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? '',
            'server_name' => $_SERVER['SERVER_NAME'] ?? '',
            'server_addr' => $_SERVER['SERVER_ADDR'] ?? '',
            'server_port' => $_SERVER['SERVER_PORT'] ?? '',
            'server_protocol' => $_SERVER['SERVER_PROTOCOL'] ?? '',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
            'current_working_directory' => getcwd(),
            'script_owner' => get_current_user(),
            'temp_directory' => sys_get_temp_dir(),
            'os' => PHP_OS,
            'os_family' => PHP_OS_FAMILY,
            'sapi_name' => php_sapi_name(),
            'debug_mode' => (function_exists('xdebug_info')),
            'opcache_enabled' => function_exists('opcache_get_status') ? opcache_get_status() : false
        ], JSON_PRETTY_PRINT);
        exit;
    }
}