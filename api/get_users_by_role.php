<?php
// public/api/get_users_by_role.php

header('Content-Type: application/json');
ini_set('display_errors', 0);
require_once __DIR__ . '/../../config/db_connect.php';
if (!isset($conn) || !$conn) {
    http_response_code(500);
    exit(json_encode(['error'=>'DB connection failed']));
}

$role = $_GET['role'] ?? '';
if ($role === '') {
    http_response_code(400);
    exit(json_encode(['error'=>'Missing role']));
}

$res = pg_query_params($conn,
    'SELECT id, full_name FROM users WHERE role = $1 ORDER BY full_name',
    [$role]
);
if (!$res) {
    http_response_code(500);
    exit(json_encode(['error'=>pg_last_error($conn)]));
}

echo json_encode(pg_fetch_all($res) ?: []);
