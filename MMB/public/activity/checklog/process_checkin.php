<?php
// public/activity/checklog/process_checkin.php – PostgreSQL version

// 1) Start session & auth
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// 2) Include DB connection
require_once __DIR__ . '/../../../config/db_connect.php';

// 3) Retrieve & validate inputs
$userId    = $_SESSION['user_id'];
$projectId = filter_input(INPUT_POST, 'project_id', FILTER_VALIDATE_INT);

if (!$userId || !$projectId) {
    http_response_code(400);
    echo 'Invalid request.';
    exit;
}

// 4) Fetch project details for name & address
$projSql = "SELECT project_name, address_line1 FROM project_addresses WHERE id = \$1";
$projRes = pg_query_params($conn, $projSql, [$projectId]);
if (!$projRes || pg_num_rows($projRes) === 0) {
    http_response_code(404);
    echo 'Project not found.';
    exit;
}
$projRow     = pg_fetch_assoc($projRes);
$projectName = $projRow['project_name'];
$address1    = $projRow['address_line1'];

// 5) Insert the check-in
$sql = <<<SQL
INSERT INTO check_log (
    user_id,
    project_id,
    project_name,
    address_line1,
    check_in_date,
    check_in_clock
) VALUES (
    \$1, \$2, \$3, \$4, CURRENT_DATE, CURRENT_TIME
)
SQL;
$params = [$userId, $projectId, $projectName, $address1];
$result = pg_query_params($conn, $sql, $params);

if (!$result) {
    http_response_code(500);
    echo 'Database error: ' . pg_last_error($conn);
    exit;
}

// 6) Redirect back to dashboard
header('Location: /dashboard.php');
exit;
?>
