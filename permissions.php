<?php
session_start();

$permissions = [
    'admin' => [
        'dashboard',
        'invoices',
        'reports',
        'medicines',
        'users',
        'print'
    ],
    'manager' => [
        'dashboard',
        'invoices',
        'reports',
        'medicines',
        'print'
    ],
    'user' => [
        'dashboard',
        'invoices',
        'print'
    ]
];

function can($permission)
{
    global $permissions;

    if (!isset($_SESSION['role'])) {
        return false;
    }

    $role = $_SESSION['role'];

    return in_array($permission, $permissions[$role]);
}
