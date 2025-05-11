<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: /MMB/dashboard.php");
    exit;
}

// 1) load the DB
require_once __DIR__ . '/../../../config/db_connect.php';

$user_id = $_SESSION['user_id'];

// 2) Prevent double-check-in
$stmt = $mysqli->prepare("
    SELECT id
      FROM check_log
     WHERE user_id        = ?
       AND check_out_date IS NULL
       AND check_out_clock IS NULL
    LIMIT 1
");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    // Already in—just bounce back
    $stmt->close();
    $back = $_SERVER['HTTP_REFERER'] ?? '/MMB/dashboard.php';
    header("Location: $back");
    exit;
}
$stmt->close();

// 3) Look up project name
$project_name = 'Dashboard';
if (!empty($_POST['project_id'])) {
    $pid = (int) $_POST['project_id'];
    $q = $mysqli->prepare("SELECT project_name FROM project_addresses WHERE id = ?");
    $q->bind_param('i', $pid);
    $q->execute();
    $q->bind_result($project_name);
    $q->fetch();
    $q->close();
}

// 4) Insert the check-in
$date = date('Y-m-d');
$time = date('H:i:s');

$stmt = $mysqli->prepare("
    INSERT INTO check_log
      (user_id, location, check_in_date, check_in_clock)
    VALUES (?,?,?,?)
");
$stmt->bind_param('isss', $user_id, $project_name, $date, $time);
$stmt->execute();
$stmt->close();

// 5) Redirect back to Dashboard
$back = $_SERVER['HTTP_REFERER'] ?? '/MMB/dashboard.php';
header("Location: $back");
exit;
