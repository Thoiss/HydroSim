<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

if (!function_exists('db_error_helper')) {
    function db_error_helper(): string
    {
        return 'Database connection failed: ' . mysqli_connect_error() .
            ' (Host: ' . ini_get('mysqli.default_host') .
            ', Port: ' . ini_get('mysqli.default_port') . ')';
    }
}