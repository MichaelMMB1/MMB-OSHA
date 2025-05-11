<?php
session_start();
require_once(__DIR__ . '/../../config/db_connect.php');

$id         = intval($_POST['id']);
$full_name  = $_POST['full_name'];
$username   = $_POST['username'];
$email      = $_POST['email'];
$phone      = $_POST['phone'];
$position   = $_POST['position_title'];
$role       = $_POST['role'];
$password   = trim($_POST['password']);

if ($password !== '') {
    // Update all fields including password
    $stmt = $mysqli->prepare("UPDATE users SET full_name=?, username=?, email=?, phone=?, position_title=?, role=?, password=? WHERE id=?");
    $stmt->bind_param("sssssssi", $full_name, $username, $email, $phone, $position, $role, $password, $id);
} else {
    // Update all fields except password
    $stmt = $mysqli->prepare("UPDATE users SET full_name=?, username=?, email=?, phone=?, position_title=?, role=? WHERE id=?");
    $stmt->bind_param("ssssssi", $full_name, $username, $email, $phone, $position, $role, $id);
}

if ($stmt->execute()) {
    $_SESSION['flash_success'] = "User updated successfully.";
} else {
    $_SESSION['flash_success'] = "Error updating user.";
}
$stmt->close();

header("Location: users.php");
exit;
