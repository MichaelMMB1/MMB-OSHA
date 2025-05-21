<?php
// public/api/verify_all.php

require_once __DIR__ . '/../../config/db_connect.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$userId = $_GET['user_id'] ?? null;

if (!$userId || !is_numeric($userId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid user ID']);
    exit;
}

$query = "UPDATE check_log SET verified = TRUE WHERE user_id = $1";
$result = pg_query_params($conn, $query, [$userId]);

if ($result) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Database update failed']);
}
?>
