<?php

function validate_password(string $password, string $password_confirm = null): array
{
    $errors = [];

    if ($password !== $password_confirm) {
        $errors[] = 'Passwords do not match.';
    }

    return $errors; 
}