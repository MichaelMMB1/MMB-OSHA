<?php
declare(strict_types=1);
require_once __DIR__ . '/../bootstrap.php'; // session_start(), $conn

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$userId = (int)($_GET['id'] ?? $_SESSION['id'] ?? 0);
if ($userId <= 0) {
    header('HTTP/1.1 404 Not Found');
    exit;
}

$res = pg_query_params(
    $conn,
    'SELECT avatar_data, avatar_mime FROM users WHERE id = $1',
    [$userId]
);

$row = $res ? pg_fetch_assoc($res) : null;
if (! $row || $row['avatar_data'] === null) {
    header('HTTP/1.1 404 Not Found');
    exit;
}

header('Content-Type: ' . $row['avatar_mime']);
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

echo pg_unescape_bytea($row['avatar_data']);
exit;
