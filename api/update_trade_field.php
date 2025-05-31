<?php
ini_set('display_errors', 0);
error_reporting(0);
if (ob_get_level()) ob_end_clean();
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__.'/../../config/db_connect.php';

$body = file_get_contents('php://input');
$data = json_decode($body, true);
if(!is_array($data)||!isset($data['id'],$data['field'],$data['value'])){
  echo json_encode(['success'=>false,'error'=>'Invalid payload']);exit;
}
$allowed=['name','color'];
if(!in_array($data['field'],$allowed,true)){
  echo json_encode(['success'=>false,'error'=>'Invalid field']);exit;
}
$id=(int)$data['id'];
$field=pg_escape_identifier($conn,$data['field']);
$value=$data['value'];
$res=pg_query_params($conn,"UPDATE trades SET {$field}=$1 WHERE id=$2",[$value,$id]);
if($res) echo json_encode(['success'=>true]);
else     echo json_encode(['success'=>false,'error'=>pg_last_error($conn)]);
exit;
