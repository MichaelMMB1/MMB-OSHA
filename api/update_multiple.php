<?php
// public/api/update_multiple.php
header('Content-Type: application/json');
$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    echo json_encode(['success'=>false,'error'=>'Invalid payload']);
    exit;
}

require_once __DIR__ . '/../../config/db_connect.php';

$errors = [];
foreach ($data as $rec) {
    $id        = (int)$rec['id'];
    $date      = $rec['date'];
    $proj      = (int)$rec['project_id'];
    $in        = $rec['check_in'];
    $out       = $rec['check_out'];
    $verified  = $rec['verified'] ? 'TRUE' : 'FALSE';

    $sql = <<<SQL
UPDATE activities_log
   SET check_in_date   = \$1
     , project_id      = \$2
     , check_in_clock  = \$3
     , check_out_clock = \$4
     , verified        = $verified
 WHERE id = \$5
SQL;
    $res = pg_query_params($conn, $sql, [ $date, $proj, $in, $out, $id ]);
    if (!$res) {
        $errors[] = "ID $id: " . pg_last_error($conn);
    }
}

if (empty($errors)) {
    echo json_encode(['success'=>true]);
} else {
    echo json_encode(['success'=>false, 'error'=>implode('; ', $errors)]);
}
