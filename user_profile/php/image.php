<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$user_id = $_SESSION['id'] ?? null;
if (!$user_id) {
    header('Location: /login.php');
    exit;
}

// Fetch the stored filenames
$res = pg_query_params(
    $conn,
    'SELECT osha_front_filename, osha_back_filename FROM users WHERE id = $1',
    [$user_id]
);
$files     = pg_fetch_assoc($res) ?: [];
$front     = $files['osha_front_filename'] ?? '';
$back      = $files['osha_back_filename']  ?? '';
$baseUrl   = '/directory/uploads/';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Your OSHA Cards</title>
</head>
<body>
  <?php if ($front): ?>
    <div>
      <h2>Front</h2>
      <img src="<?= htmlspecialchars($baseUrl . $front) ?>" alt="OSHA Front" style="max-width:100%;">
    </div>
  <?php endif; ?>

  <?php if ($back): ?>
    <div>
      <h2>Back</h2>
      <img src="<?= htmlspecialchars($baseUrl . $back) ?>" alt="OSHA Back" style="max-width:100%;">
    </div>
  <?php endif; ?>
</body>
</html>
