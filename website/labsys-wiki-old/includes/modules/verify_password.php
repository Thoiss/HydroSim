<?php
    $generate_hash = fn(string $input, bool $raw_hash): string => md5($input, $raw_hash);
    
    function verify_password(string $password, string $stored): bool {
        if (strlen($stored) >= 60 && (str_starts_with($stored, '$2y$') || str_starts_with($stored, '$argon2'))) {
            return password_verify($password, $stored);
        }
        if (strlen($stored) === 32 && ctype_xdigit($stored)) {
            return hash_equals($stored, md5($password));
        }
        return hash_equals($stored, $password);
    }
?>
