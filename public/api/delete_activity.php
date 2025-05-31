<?php
// public/api/delete_activity.php
header('Content-Type: application/json');

// Only allow DELETE
if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Read the id from the query string
if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing id']);
    exit;
}
$id = (int) $_GET['id'];

require_once __DIR__ . '/../../config/db_connect.php';

// Perform the delete
$res = pg_query_params($conn,
    'DELETE FROM activities_log WHERE id = $1',
    [ $id ]
);

if ($res) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => pg_last_error($conn)
    ]);
}
