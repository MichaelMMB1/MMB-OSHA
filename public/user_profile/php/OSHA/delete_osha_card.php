<?php
declare(strict_types=1);

// public/user_profile/delete_osha_card.php
require_once __DIR__ . '/../../../bootstrap.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

$userId = $_SESSION['id'] ?? null;
if (!$userId) {
    header('Location: /login.php');
    exit;
}

$side = $_GET['side'] ?? '';
if (!in_array($side, ['front', 'back'], true)) {
    header('Location: /user_profile/php/user_profile.php');
    exit;
}

// Build path to OSHA image file
$dir = "D:/MMB-OSHA/Uploads/{$userId}/OSHA/";
$extensions = ['jpg', 'jpeg', 'png'];

foreach ($extensions as $ext) {
    $file = $dir . "{$side}.{$ext}";
    if (file_exists($file)) {
        unlink($file);
        break;
    }
}

header('Location: /user_profile/php/user_profile.php');
exit;
