<?php
// public/api/update_project.php

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(0);

require_once __DIR__ . '/../../config/db_connect.php';

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input) || empty($input['id'])) {
  echo json_encode(['success'=>false,'error'=>'Invalid request']);
  exit;
}

$id     = (int)$input['id'];
$status = pg_escape_literal($conn, $input['status'] ?? '');
$pm     = $input['project_manager_id'] !== '' ? (int)$input['project_manager_id'] : 'NULL';
$su     = $input['superintendent_id']  !== '' ? (int)$input['superintendent_id']  : 'NULL';

$sql = "
  UPDATE project_addresses
     SET status = {$status},
         project_manager_id   = {$pm},
         superintendent_id    = {$su}
   WHERE id = {$id}
";
$res = pg_query($conn, $sql);

if ($res) {
  echo json_encode(['success'=>true]);
} else {
  echo json_encode([
    'success'=>false,
    'error'  => pg_last_error($conn)
  ]);
}
