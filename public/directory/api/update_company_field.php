<?php
declare(strict_types=1);

// Show errors while debugging; remove in production
ini_set('display_errors','1');
ini_set('display_startup_errors','1');
error_reporting(E_ALL);

// Always return JSON
header('Content-Type: application/json; charset=UTF-8');

// Connect to database
require_once __DIR__ . '/../../../config/db_connect.php';

// Decode JSON payload
$in    = json_decode(file_get_contents('php://input'), true) ?: [];
$id    = isset($in['id'])    ? (int)$in['id']    : 0;
$field = isset($in['field']) ? $in['field']      : '';
$value = isset($in['value']) ? $in['value']      : '';

// Whitelist allowed columns on companies table
$allowed = ['name','trade','website','email','phone','full_address'];
if (!$id || !in_array($field, $allowed, true)) {
    echo json_encode(['success'=>false,'error'=>'Invalid request']);
    exit;
}

// Perform the update
$sql = sprintf(
    'UPDATE companies SET %s = $1 WHERE id = $2',
    pg_escape_identifier($field)
);
$res = pg_query_params($conn, $sql, [$value, $id]);

if ($res) {
    echo json_encode(['success'=>true]);
} else {
    echo json_encode([
      'success'=>false,
      'error'  => pg_last_error($conn) ?? 'Database error'
    ]);
}
