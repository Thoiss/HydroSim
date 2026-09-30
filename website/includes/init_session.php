<?php
$cookie_params = [
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => false,
    'httponly' => true,
    'samesite' => 'Lax'
];

session_set_cookie_params($cookie_params);

session_start();
