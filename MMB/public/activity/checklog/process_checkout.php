<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: /MMB/dashboard.php");
    exit;
}

// load DB
require_once __DIR__ . '/../../../config/db_connect.php';

$user_id = $_SESSION['user_id'];
$date    = date('Y-m-d');
$time    = date('H:i:s');

// 1) Find the open check-in
$stmt = $mysqli->prepare("
    SELECT id
      FROM check_log
     WHERE user_id        = ?
       AND check_out_date IS NULL
       AND check_out_clock IS NULL
     ORDER BY check_in_date  DESC,
              check_in_clock DESC
     LIMIT 1
");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($open_id);
$stmt->fetch();
$stmt->close();

// 2) Close it if found
if (!empty($open_id)) {
    $u = $mysqli->prepare("
      UPDATE check_log
         SET check_out_date  = ?,
             check_out_clock = ?
       WHERE id             = ?
    ");
    $u->bind_param('ssi', $date, $time, $open_id);
    $u->execute();
    $u->close();
}

// 3) Back to Dashboard
$back = $_SERVER['HTTP_REFERER'] ?? '/MMB/dashboard.php';
header("Location: $back");
exit;
