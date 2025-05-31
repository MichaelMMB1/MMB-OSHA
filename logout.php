<?php
// public/logout.php

// Start session if needed
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Always clear session
$_SESSION = [];
session_destroy();

// If this was a beacon (POST/keepalive), just send 204 and exit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    http_response_code(204);
    exit;
}

// Otherwise (normal GET), redirect to login
header('Location: /login.php');
exit;
