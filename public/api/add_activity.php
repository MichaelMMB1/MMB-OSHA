<?php
// public/api/add_activity.php

header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../../config/db_connect.php';

// read incoming JSON
$payload    = json_decode(file_get_contents('php://input'), true);
$user_id    = $payload['user_id']    ?? null;
$project_id = $payload['project_id'] ?? null;

if (!$user_id || !$project_id) {
    echo json_encode([
      'success' => false,
      'error'   => 'Missing user_id or project_id'
    ]);
    exit;
}

// insert a new check-in record
$res = pg_query_params($conn, "
  INSERT INTO public.activities_log
    (user_id, project_id, check_in_date, check_in_clock)
  VALUES
    ($1,       $2,         CURRENT_DATE,    CURRENT_TIME)
  RETURNING id
", [
  $user_id,
  $project_id
]);

if ($res && $row = pg_fetch_assoc($res)) {
    echo json_encode([
      'success' => true,
      'id'      => $row['id']
    ]);
} else {
    echo json_encode([
      'success' => false,
      'error'   => 'Database insert failed'
    ]);
}
