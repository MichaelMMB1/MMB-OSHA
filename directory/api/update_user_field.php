<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=UTF-8');
ini_set('display_errors','1');
ini_set('display_startup_errors','1');
error_reporting(E_ALL);

require_once __DIR__ . '/../../../config/db_connect.php';

$data  = json_decode(file_get_contents('php://input'), true) ?: [];
$id    = isset($data['id'])    ? (int)$data['id']    : 0;
$field = isset($data['field']) ? $data['field']      : '';
$value = isset($data['value']) ? $data['value']      : '';

$allowed = ['full_name','username','email','role','trade_id','color'];
if (!$id || !in_array($field, $allowed, true)) {
    echo json_encode(['success'=>false,'error'=>'Invalid request']);
    exit;
}

$sql = sprintf(
  'UPDATE users SET %s = $1 WHERE id = $2',
  pg_escape_identifier($field)
);
$res = pg_query_params($conn, $sql, [$value, $id]);

if ($res) {
    echo json_encode(['success'=>true]);
} else {
    echo json_encode([
      'success'=>false,
      'error'  => pg_last_error($conn) ?: 'Database error'
    ]);
}
