<?php
// public/api/update_project_field.php

// Always return JSON
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Load database connection
require_once __DIR__ . '/../../config/db_connect.php';
if (!isset($conn) || !$conn) {
    http_response_code(500);
    exit(json_encode(['success' => false, 'error' => 'Database connection failed']));
}

// Read and decode the JSON payload
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'error' => 'Invalid JSON']));
}

// Validate required parameters
if (
    empty($data['id']) ||
    !isset($data['field']) ||
    !array_key_exists('value', $data)
) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'error' => 'Missing parameters']));
}

$id    = (int) $data['id'];
$field = $data['field'];
$value = $data['value'];

// Whitelist allowed columns
$allowed = [
    'project_name',
    'address_line1',        // ← now allowed
    'project_manager_id',
    'site_superintendent'
];
if (!in_array($field, $allowed, true)) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'error' => 'Field not allowed']));
}

// Determine if this is an integer column that should accept NULL
$isIntCol = in_array($field, ['project_manager_id'], true);

if ($isIntCol && $value === '') {
    // Set integer column to NULL
    $sql = "UPDATE project_addresses SET \"{$field}\" = NULL WHERE id = \$1";
    $res = pg_query_params($conn, $sql, [$id]);
} else {
    // Standard parameterized update
    $sql = "UPDATE project_addresses SET \"{$field}\" = \$1 WHERE id = \$2";
    $res = pg_query_params($conn, $sql, [$value, $id]);
}

if ($res && pg_affected_rows($res) > 0) {
    echo json_encode(['success' => true]);
} elseif ($res) {
    // No rows changed (value was the same)
    echo json_encode(['success' => true, 'warning' => 'No change detected']);
} else {
    // Database error
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => pg_last_error($conn)]);
}
