<?php
declare(strict_types=1);

// public/user_profile/php/OSHA/upload_osha_card.php

// 1) Bootstrap & Session
require_once dirname(__DIR__, 3) . '/bootstrap.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// 2) Must be logged in
$userId = (int)($_SESSION['id'] ?? 0);
if ($userId <= 0) {
    header('Location: /login.php');
    exit;
}

// 3) Only POST + valid side
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../user_profile.php');
    exit;
}
$side = $_POST['side'] ?? '';
if (!in_array($side, ['front', 'back'], true)) {
    header('Location: ../user_profile.php');
    exit;
}

// 4) Check upload
if (empty($_FILES['osha_card']) || $_FILES['osha_card']['error'] !== UPLOAD_ERR_OK) {
    header('Location: ../user_profile.php');
    exit;
}

// 5) Determine paths
$uploadsDir = "D:/MMB-OSHA/Uploads/{$userId}/OSHA/";
$filename   = $side . '.jpg'; // overwrite with fixed filename
$dest       = $uploadsDir . $filename;
$urlPath    = "users/{$userId}/OSHA/{$filename}"; // for browser access

// 6) Ensure target directory exists
if (!is_dir($uploadsDir) && !mkdir($uploadsDir, 0755, true)) {
    die('Failed to create upload directory: ' . htmlspecialchars($uploadsDir));
}

// 7) Move uploaded file into place (overwrite)
$tmp = $_FILES['osha_card']['tmp_name'];
if (move_uploaded_file($tmp, $dest)) {
    // 8) Update DB with relative path for browser
    $col = $side === 'front' ? 'osha_front_filename' : 'osha_back_filename';
    pg_query_params($conn, "UPDATE users SET {$col} = $1 WHERE id = $2", [$urlPath, $userId]);
}

// 9) Redirect
header('Location: ../user_profile.php?upload=success');
exit;
