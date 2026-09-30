<?php
function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode($data) {
    return base64_decode(strtr($data, '-_', '+/'));
}

if (isset($_GET['api_token']) && !empty($_GET['api_token'])) {
    $provided_token = $_GET['api_token'];
    $parts = explode('.', $provided_token);

    if (count($parts) === 3) {
        $header = json_decode(base64url_decode($parts[0]), true);
        $payload = json_decode(base64url_decode($parts[1]), true);

        if ($header && $payload && isset($payload['user_id'])) {
            $_SESSION['username'] = $payload['username'];
            $_SESSION['email'] = $payload['email'] ?? '';
            $_SESSION['role'] = $payload['role'] ?? 'user';
            $_SESSION['user_id'] = $payload['user_id'];
            $_SESSION['login_time'] = $payload['issued_at'];
            $_SESSION['authenticated_via'] = 'api_token';
        }
    }
}

function jwt_token_builder(array $user_row): string
{
    $header = ['alg' => 'none', 'typ' => 'JWT'];
    $payload = [
        'user_id' => $user_row['id'],
        'username' => $user_row['username'],
        'email' => $user_row['email'] ?? '',
        'role' => $user_row['role'],
        'issued_at' => time()
    ];
    return base64url_encode(json_encode($header)) . '.' .
           base64url_encode(json_encode($payload)) . '.';
}