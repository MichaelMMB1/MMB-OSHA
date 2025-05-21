<?php
// public/activity/checklog/process_checkout.php – PostgreSQL version with redirect

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}

// Include PostgreSQL connection (defines $conn)
require_once __DIR__ . '/../../../config/db_connect.php';

$user_id    = $_SESSION['user_id'];
$checkin_id = filter_input(INPUT_POST, 'checkin_id', FILTER_VALIDATE_INT);

if (!$checkin_id) {
    http_response_code(400);
    echo "Missing or invalid check-in ID.";
    exit;
}

// Validate the check-in record belongs to the user and is still open
$result = pg_query_params(
    $conn,
    "SELECT id FROM check_log
     WHERE id = $1
       AND user_id = $2
       AND check_out_date IS NULL
       AND check_out_clock IS NULL",
    [$checkin_id, $user_id]
);

if (!$result || pg_num_rows($result) === 0) {
    http_response_code(403);
    echo "Unauthorized or already checked out.";
    exit;
}

// Perform the check-out
$current_date = date('Y-m-d');
$current_time = date('H:i:s');

$update = pg_query_params(
    $conn,
    "UPDATE check_log
        SET check_out_date = $1,
            check_out_clock = $2
      WHERE id = $3",
    [$current_date, $current_time, $checkin_id]
);

if ($update) {
    // Redirect back to the dashboard
    header('Location: /dashboard.php');
    exit;
} else {
    http_response_code(500);
    echo "Check-out failed: " . pg_last_error($conn);
    exit;
}
?>
