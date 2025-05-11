<?php
// public/users/update_user_position.php
session_start();
require_once __DIR__ . '/../../config/db_connect.php';

$user_id  = (int)$_POST['user_id'];
$newPos   = $_POST['position_title'];

// simple validation
if ($user_id && $newPos) {
    $stmt = $mysqli->prepare("
      UPDATE users
         SET position_title = ?
       WHERE id = ?
    ");
    $stmt->bind_param('si', $newPos, $user_id);
    $stmt->execute();
    $stmt->close();
    $_SESSION['flash_success'] = "Position updated.";
} else {
    $_SESSION['flash_error'] = "Update failed.";
}

header('Location: users.php');
exit;
