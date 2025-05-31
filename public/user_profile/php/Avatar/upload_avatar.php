<?php
declare(strict_types=1);
require_once __DIR__ . '/../bootstrap.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// 1) Must be logged in
$userId = (int) ($_SESSION['id'] ?? 0);
if ($userId <= 0) {
    die('❌ You must be logged in.');
}

// 2) Validate upload
if (
    $_SERVER['REQUEST_METHOD'] !== 'POST' ||
    ! isset($_FILES['avatar']) ||
    $_FILES['avatar']['error'] !== UPLOAD_ERR_OK
) {
    die('❌ Upload error code: ' . ($_FILES['avatar']['error'] ?? 'none'));
}

// 3) Read raw bytes + mime
$raw  = file_get_contents($_FILES['avatar']['tmp_name']);
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($_FILES['avatar']['tmp_name']);

// 4) Escape for bytea
$escapedData = pg_escape_bytea($conn, $raw);
$escapedMime = pg_escape_literal($conn, $mime);
// 5) Update
$sql = "
  UPDATE users
     SET avatar_data = '{$escapedData}',
         avatar_mime = {$escapedMime}
   WHERE id = {$userId}
";
pg_query($conn, $sql);
$res = pg_query($conn, $sql);
if (! $res) {
    die('❌ SQL Error: ' . pg_last_error($conn));
}
if (pg_affected_rows($res) !== 1) {
    die('❌ No rows updated for user ' . $userId);
}

// 6) Success
$_SESSION['avatar_message'] = '✅ Avatar uploaded.';
header('Location: user_profile.php');
exit;
