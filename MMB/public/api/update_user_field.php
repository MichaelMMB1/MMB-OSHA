<?php
// public/api/update_user_field.php

// Always return JSON
header('Content-Type: application/json');
// Don’t let PHP warnings contaminate our output
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Correct path up two levels into /config
require_once __DIR__ . '/../../config/db_connect.php';

// Verify connection
if (!isset($conn) || !$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection error']);
    exit;
}

// Read & decode
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error'   => 'Invalid JSON: ' . json_last_error_msg()
    ]);
    exit;
}

// Validate
if (
    empty($data['id'])    || !is_numeric($data['id']) ||
    empty($data['field']) || !is_string($data['field'])  ||
    !array_key_exists('value', $data)
) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing or invalid parameters.']);
    exit;
}

$id    = (int) $data['id'];
$field = $data['field'];
$value = (string) $data['value'];

// Whitelist
$allowed = ['full_name','username','email','role','trade_id'];
if (!in_array($field, $allowed, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Field not allowed.']);
    exit;
}

// Escape & update
$escapedValue = pg_escape_literal($conn, $value);
$sql = "UPDATE users SET \"{$field}\" = {$escapedValue} WHERE id = {$id}";
$res = pg_query($conn, $sql);

if ($res && pg_affected_rows($res) > 0) {
    echo json_encode(['success' => true]);
} elseif ($res) {
    // no change, but not an error
    echo json_encode(['success' => true, 'warning' => 'No changes detected.']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => pg_last_error($conn)]);
}
