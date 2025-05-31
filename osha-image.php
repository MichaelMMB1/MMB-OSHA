<?php
declare(strict_types=1);

// Get request input
$userId = (int)($_GET['user_id'] ?? 0);
$side   = $_GET['side'] ?? '';

if (!$userId || !in_array($side, ['front', 'back'], true)) {
    http_response_code(400);
    exit('Invalid request');
}

// Build file path
$basePath = "D:/MMB-OSHA/Uploads/{$userId}/OSHA/";
$extensions = ['jpg', 'jpeg', 'png'];

foreach ($extensions as $ext) {
    $file = $basePath . "{$side}.{$ext}";
    if (file_exists($file)) {
        // Ensure clean output
        while (ob_get_level()) {
            ob_end_clean();
        }

        $mime = match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png'        => 'image/png',
            default      => 'application/octet-stream',
        };

        header("Content-Type: $mime");
        header("Content-Length: " . filesize($file));
        header("Cache-Control: no-cache, must-revalidate");

        readfile($file);
        exit;
    }
}

http_response_code(404);
echo "Image not found.";
