<?php
declare(strict_types=1);
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/db_connect.php';
session_start();

// read JSON
$input = json_decode(file_get_contents('php://input'), true);

// validate
$projName = trim($input['project_name'] ?? '');
$address  = trim($input['address'] ?? '');
$status   = trim($input['status'] ?? '');
$pm       = (int)($input['project_manager'] ?? 0);
$super    = (int)($input['site_superintendent'] ?? 0);
$users    = $input['users']    ?? [];
$days     = $input['weekdays'] ?? [];

if (!$projName || !$pm) {
  echo json_encode(['success'=>false,'error'=>'Missing required fields.']);
  exit;
}

try {
  // 1) insert project
  $res = pg_query_params($conn, "
    INSERT INTO project_addresses
      (project_name, address, status, project_manager_id, site_superintendent_id)
    VALUES
      ($1, $2, $3, $4, $5)
    RETURNING id
  ", [
    $projName, $address, $status, $pm, $super
  ]);
  $row = pg_fetch_assoc($res);
  $projectId = $row['id'];

  // 2) assign schedule rows
  if (!empty($users) && !empty($days)) {
    foreach ($days as $dow) {
      foreach ($users as $uid) {
        pg_query_params($conn, "
          INSERT INTO schedules
            (project_id, day_of_week, user_id)
          VALUES
            ($1, $2, $3)
        ", [
          $projectId, $dow, (int)$uid
        ]);
      }
    }
  }

  echo json_encode(['success'=>true,'project_id'=>$projectId]);
} catch (Exception $e) {
  echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
