<?php
// public/api/add_multiple_records.php

header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../../config/db_connect.php';

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload) || empty($payload)) {
    echo json_encode([
      'success' => false,
      'error'   => 'Invalid or empty payload'
    ]);
    exit;
}

$errors = [];

foreach ($payload as $i => $rec) {
    $user_id    = $rec['user_id']    ?? null;
    $project_id = $rec['project_id'] ?? null;
    $check_in   = $rec['check_in']   ?? null;
    $check_out  = $rec['check_out']  ?? null;
    $d          = isset($rec['day_of_week']) ? intval($rec['day_of_week']) : null;

    if (!$user_id || !$project_id || !$check_in || !$check_out || $d === null) {
        $errors[] = "Record #{$i}: missing fields";
        continue;
    }

    // Convert JS day (0=Sun..6=Sat) to ISO day (1=Mon..7=Sun)
    $iso = ($d === 0) ? 7 : $d;
    // Calculate the actual date within the current ISO-week
    // date_trunc('week',current_date) yields Monday
    $offset = $iso - 1; 
    $resDate = pg_query($conn, "
      SELECT (date_trunc('week', CURRENT_DATE)::date + $1) AS dt
    ", [ $offset ]);
    if (! $resDate || ! ($row = pg_fetch_assoc($resDate))) {
        $errors[] = "Record #{$i}: failed to compute date";
        continue;
    }
    $date = $row['dt'];

    // Insert with both check-in and check-out
    $ins = pg_query_params($conn, "
      INSERT INTO public.activities_log
        (user_id, project_id, check_in_date, check_out_date, check_in_clock, check_out_clock)
      VALUES
        ($1,       $2,         $3,              $3,              $4,             $5)
    ", [
      $user_id,
      $project_id,
      $date,
      $check_in,
      $check_out
    ]);

    if (! $ins) {
        $errors[] = "Record #{$i}: database error";
    }
}

if ($errors) {
    echo json_encode([
      'success' => false,
      'error'   => implode('; ', $errors)
    ]);
} else {
    echo json_encode([ 'success' => true ]);
}
