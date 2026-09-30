<?php
require_once __DIR__ . '/config.php';

function db_connect() {
    global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME;
    $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, 3307);
    if ($conn->connect_error) return null;
    $conn->set_charset('utf8mb4');
    return $conn;
}
