<?php
declare(strict_types=1);

// debug block
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("POST DATA: " . print_r($_POST, true));
}

// TEMP: show all errors on screen
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// 1) PROJECT_ROOT = one level above /public
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));

// 2) PUBLIC_ROOT = this directory
define('PUBLIC_ROOT', __DIR__);

// 3) INCLUDES_ROOT = PROJECT_ROOT . '/includes'
define('INCLUDES_ROOT', PROJECT_ROOT . '/includes');

// 4) start session + DB
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ← here: point *into* config, not above it
require_once PROJECT_ROOT . '/config/db_connect.php';

// 5) auto-include header on all pages except login/logout
$current = basename($_SERVER['SCRIPT_NAME']);

if (! in_array($current, ['login.php', 'logout.php'], true)) {
    require_once INCLUDES_ROOT . '/header/php/header.php';
}
