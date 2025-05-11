<?php
require_once __DIR__ . '/../../config/db_connect.php';

$userId   = isset($_POST['user_id'])   ? (int)$_POST['user_id'] : 0;
$roleName = isset($_POST['role'])      ? trim($_POST['role'])  : '';

if ($userId === 0 || $roleName === '') {
    // bad request — go back to the users tab
    header('Location: users.php#usersTab');
    exit;
}

$stmt = $mysqli->prepare("
  UPDATE users
     SET role = ?
   WHERE id   = ?
");
$stmt->bind_param('si', $roleName, $userId);
$stmt->execute();

header('Location: users.php#usersTab');
exit;