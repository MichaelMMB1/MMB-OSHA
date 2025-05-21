<?php
// public/api/update_multiple.php

// 1) Error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 2) Read raw input
$rawInput = file_get_contents('php://input');

// 3) Log raw input for debugging (remove after testing)
file_put_contents(__DIR__ . '/debug_input.txt', "RAW INPUT:\n" . $rawInput);

// 4) Decode JSON
$data = json_decode($rawInput, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode([
        'error' => 'JSON decode error: ' . json_last_error_msg(),
        'raw'   => $rawInput
    ]);
    exit;
}

// 5) Normalize to array of items
if (isset($data['id'])) {
    $data = [$data];
} elseif (!is_array($data) || empty($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'Expected a JSON array or a single object with an "id" field.']);
    exit;
}

// 6) Connect to the database
require_once __DIR__ . '/../../config/db_connect.php';
if (!isset($conn) || !$conn) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection missing']);
    exit;
}

// 7) Prepare SQL update
$sql = "UPDATE check_log
        SET check_in_date   = \$2,
            project_id      = \$3,
            check_in_clock  = \$4,
            check_out_clock = \$5,
            verified        = \$6::boolean
        WHERE id            = \$1";

// 8) Execute updates
foreach ($data as $item) {
    $required = ['id', 'date', 'project_id', 'check_in', 'check_out', 'verified'];
    foreach ($required as $key) {
        if (!array_key_exists($key, $item)) {
            http_response_code(400);
            echo json_encode(['error' => "Missing field '$key' in one of the records"]);
            exit;
        }
    }

    $params = [
        (int)$item['id'],                         // $1
        $item['date'],                            // $2
        (int)$item['project_id'],                 // $3
        $item['check_in'],                        // $4
        $item['check_out'],                       // $5
        filter_var($item['verified'], FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false' // $6
    ];

    $res = pg_query_params($conn, $sql, $params);
    if (!$res) {
        http_response_code(500);
        echo json_encode([
            'error' => "Update failed for ID {$item['id']}: " . pg_last_error($conn)
        ]);
        exit;
    }
}

// 9) Return success
echo json_encode(['status' => 'OK']);
