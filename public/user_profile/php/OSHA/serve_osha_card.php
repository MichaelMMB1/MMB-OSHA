<?php
declare(strict_types=1);

// public/user_profile/serve_osha_card.php
require_once __DIR__ . '/../bootstrap.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

$userId = $_SESSION['id'] ?? null;
if (!$userId) {
    http_response_code(403);
    exit;
}

$res = pg_query_params($conn, "
    SELECT osha_card_data, osha_card_mime
      FROM users
     WHERE id = $1
", [$userId]);

$row = pg_fetch_assoc($res);
if (
    ! $row ||
    empty($row['osha_card_data']) ||
    empty($row['osha_card_mime'])
) {
    http_response_code(404);
    exit;
}

header('Content-Type: ' . $row['osha_card_mime']);
echo pg_unescape_bytea($row['osha_card_data']);
exit;
