<?php
// public/users/update_user.php

session_start();
require_once __DIR__ . '/../../config/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

if (
    !empty($_POST['user_id']) &&
    !empty($_POST['field']) &&
    array_key_exists('value', $_POST)
) {
    // whitelist columns
    $allowed = [
        'full_name',
        'username',
        'email',
        'phone',
        'position_title',
        'role'
    ];

    $field = $_POST['field'];
    if (in_array($field, $allowed, true)) {
        // build & run safe query
        $sql = "UPDATE users SET `$field` = ? WHERE id = ?";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param('si', $_POST['value'], $_POST['user_id']);
        $stmt->execute();
        $stmt->close();
    }
}

// back to Users tab
header('Location: users.php#usersTab');
exit;
