<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/db_connect.php';

$name    = trim($_POST['project_name'] ?? '');
$address = trim($_POST['address_line1'] ?? '');
$pm      = intval($_POST['project_manager_id'] ?? 0) ?: null;
$su      = intval($_POST['site_superintendent_id'] ?? 0) ?: null;

if (!$name || !$address) {
  echo json_encode(['success'=>false,'error'=>'Name and address required.']); exit;
}

$res = pg_query_params($conn,
  'INSERT INTO project_addresses (project_name, address_line1, project_manager_id, site_superintendent_id, active) 
   VALUES ($1,$2,$3,$4,\'active\') RETURNING id',
  [$name,$address,$pm,$su]
);

if ($res && ($row=pg_fetch_assoc($res))) {
  echo json_encode(['success'=>true,'id'=>$row['id']]);
} else {
  echo json_encode(['success'=>false,'error'=>pg_last_error($conn)]);
}
