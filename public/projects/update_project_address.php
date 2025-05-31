<?php
// public/projects/update_project_address.php

// Ensure only JSON output
ini_set('display_errors', 0);
error_reporting(0);
ob_start(); // Suppress any accidental output

require_once __DIR__ . '/../../config/db_connect.php';

// Ensure site_superintendent column can be NULL
pg_query($conn, "ALTER TABLE project_addresses ALTER COLUMN site_superintendent DROP NOT NULL;");

// Read and decode JSON payload
$raw = file_get_contents('php://input');
$input = json_decode($raw, true) ?: [];

$id    = isset($input['id']) ? intval($input['id']) : 0;
// Batch update if multiple field keys present
$batchFields = ['project_name','address_line1','status','project_manager_id','site_superintendent'];
$intFields   = ['project_manager_id','site_superintendent'];

$hasBatch = false;
foreach ($batchFields as $bf) {
    if (array_key_exists($bf, $input)) { $hasBatch = true; break; }
}

// If payload contains multiple fields, apply batch update
if ($id && $hasBatch) {
    foreach ($batchFields as $bf) {
        if (!array_key_exists($bf, $input)) continue;
        $value = $input[$bf];
        // Normalize empty strings for integer fields
        if (in_array($bf, $intFields, true) && ($value === '' || $value === null)) {
            $paramVal = null;
        } else {
            $paramVal = $value;
        }
        $sql = sprintf('UPDATE project_addresses SET %s = $1 WHERE id = $2', pg_escape_identifier($conn, $bf));
        pg_query_params($conn, $sql, [$paramVal, $id]);
    }
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit;
}

// Single-field update fallback
$field = $input['field'] ?? '';
$value = array_key_exists('value', $input) ? $input['value'] : null;

// Only allow specific fields to be updated
$allowed      = $batchFields;
$intFields    = ['project_manager_id','site_superintendent'];

// Validate
if (!$id || !in_array($field, $allowed, true)) {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit;
}
$allowed      = ['project_name', 'address_line1', 'status', 'project_manager_id', 'site_superintendent'];
$intFields    = ['project_manager_id', 'site_superintendent'];

// Validate
if (!$id || !in_array($field, $allowed, true)) {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit;
}

// Normalize empty strings for integer fields to NULL
if (in_array($field, $intFields, true) && ($value === '' || $value === null)) {
    $paramValue = null;
} else {
    $paramValue = $value;
}

// Build and execute safe update
$sql = sprintf(
    'UPDATE project_addresses SET %s = $1 WHERE id = $2',
    pg_escape_identifier($conn, $field)
);
$res = pg_query_params($conn, $sql, [$paramValue, $id]);

// Return JSON
ob_end_clean();
header('Content-Type: application/json');
if ($res && pg_affected_rows($res) === 1) {
    echo json_encode(['success' => true]);
} else {
    $err = pg_last_error($conn) ?: 'Unknown error';
    echo json_encode(['success' => false, 'error' => $err]);
}
