<?php
// public/activity/checklog/process_checkout.php
session_start();

// adjust path to your config
require_once __DIR__ . '/../../../config/db_connect.php';

// 1) guard
if (empty($_SESSION['id'])) {
    header('Location: /login.php');
    exit;
}

$user_id     = (int) $_SESSION['id'];
$activity_id = (int) ($_POST['checkout_id'] ?? 0);  // ← use checkout_id

// 2) only if valid
if ($activity_id > 0) {
    $sql = "
      UPDATE public.activities_log
         SET check_out_date  = CURRENT_DATE,
             check_out_clock = CURRENT_TIME
       WHERE id = $1
         AND user_id = $2
    ";
    $res = pg_query_params($conn, $sql, [ $activity_id, $user_id ]);

    if (!$res) {
        error_log("❌ process_checkout UPDATE failed: " . pg_last_error($conn));
    } else {
        error_log("✅ process_checkout OK for activity_id={$activity_id}");
    }
}

// 3) back to dashboard
header('Location: /dashboard_standard.php');
exit;
