<?php
// public/api/create_project.php

// Return JSON
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(0);

require_once __DIR__ . '/../../config/db_connect.php';

// Decode JSON input
$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in) || empty($in['project_name']) || empty($in['address_line1'])) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

// Prepare values
$name    = pg_escape_literal($conn, $in['project_name']);
$address = pg_escape_literal($conn, $in['address_line1']);
$status  = pg_escape_literal($conn, $in['status'] ?? '');

$pm = isset($in['project_manager_id']) && $in['project_manager_id'] !== ''
    ? intval($in['project_manager_id']) : 'NULL';
$su = isset($in['superintendent_id']) && $in['superintendent_id'] !== ''
    ? intval($in['superintendent_id']) : 'NULL';

// Insert and return new ID
$sql = "
  INSERT INTO project_addresses
    (project_name, address_line1, status, project_manager_id, superintendent_id)
  VALUES
    ($name, $address, $status, $pm, $su)
  RETURNING id
";
$res = pg_query($conn, $sql);
if ($res) {
    $row = pg_fetch_assoc($res);
    echo json_encode(['success' => true, 'id' => (int)$row['id']]);
} else {
    echo json_encode(['success' => false, 'error' => pg_last_error($conn)]);
}
