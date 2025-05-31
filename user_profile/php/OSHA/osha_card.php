<?php
declare(strict_types=1);

// public/user_profile/osha_card.php
require_once __DIR__ . '/../bootstrap.php';

$userId = (int)($_GET['id'] ?? $_SESSION['id'] ?? 0);
$side   = ($_GET['side'] === 'back') ? 'osha_back_data' : 'osha_front_data';
$mimeCol= ($_GET['side'] === 'back') ? 'osha_back_mime' : 'osha_front_mime';

$res = pg_query_params($conn, "
    SELECT {$side} AS data, {$mimeCol} AS mime
      FROM users
     WHERE id = $1
", [$userId]);

if (! $row = pg_fetch_assoc($res) || $row['data'] === null) {
    header('HTTP/1.0 404 Not Found');
    exit;
}

header('Content-Type: ' . $row['mime']);
echo pg_unescape_bytea($row['data']);
exit;
