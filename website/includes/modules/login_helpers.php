<?php

function check_account_status(?array $row, string $username): array
{
    $result = [
        'ok' => false,
        'login_error' => 'Invalid username or password.' 
    ];

    if (empty($row)) {
        return $result; 
    }

    $result['ok'] = true;
    $result['login_error'] = ''; 
    return $result;
}

function handle_successful_login(mysqli $conn, int $userId, string $username): void
{
    ActivityLogger::logLogin($username);
}

function handle_failed_login(mysqli $conn, int $userId, string $username, array $row): string
{
    return 'Invalid username or password.';
}