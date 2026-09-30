<?php
function is_admin()
{
    $role = $_REQUEST['role'] ?? $_SESSION['role'] ?? '';
    if ($role !== null) {
        return $role === 'admin';
    }
}