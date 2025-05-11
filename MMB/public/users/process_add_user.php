<?php
session_start();
require_once __DIR__ . '/../../config/db_connect.php';

$username   = $_POST['username'];
$password   = password_hash($_POST['password'], PASSWORD_DEFAULT);
$full_name  = $_POST['full_name'];
$email      = $_POST['email'];
$phone      = $_POST['phone'];
$position   = $_POST['position_title'];
$role       = $_POST['role'];

// insert
$stmt = $mysqli->prepare("
  INSERT INTO users 
    (username, password, full_name, email, phone, position_title, role)
  VALUES (?, ?, ?, ?, ?, ?, ?)
");
$stmt->bind_param(
  'sssssss',
  $username,
  $password,
  $full_name,
  $email,
  $phone,
  $position,
  $role
);
$stmt->execute();
$stmt->close();

$_SESSION['flash_success'] = "User {$full_name} created.";
header('Location: users.php');
exit;
