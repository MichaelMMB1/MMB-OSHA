<?php
require_once __DIR__ . '/../../config/db_connect.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    http_response_code(405);
    echo json_encode(['error' => 'Invalid method']);
    exit;
}

parse_str($_SERVER['QUERY_STRING'], $params);
$id = $params['id'] ?? null;

if (!$id) {
    echo json_encode(['error' => 'Missing ID']);
    exit;
}

$res = pg_query_params($conn, "DELETE FROM check_log WHERE id = $1", [$id]);

if ($res) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['error' => 'Delete failed', 'details' => pg_last_error($conn)]);
}
