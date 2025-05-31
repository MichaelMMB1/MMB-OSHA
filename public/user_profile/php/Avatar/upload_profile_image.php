<?php
declare(strict_types=1);

// 1) Bootstrap & Session
require_once __DIR__ . '/../bootstrap.php';  // session_start() + $conn
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// 2) Must be logged in
$userId = (int)($_SESSION['id'] ?? 0);
if ($userId <= 0) {
    header('Location: /login.php');
    exit;
}

// 3) Flash messages
$profileMsg = $_SESSION['profile_message'] ?? '';
unset($_SESSION['profile_message']);
$cardMsg    = $_SESSION['card_message'] ?? '';
unset($_SESSION['card_message']);

// 4) Handle profile‐text update only
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['full_name']) &&
    empty($_FILES)
) {
    $res = pg_query_params($conn, "
        UPDATE users
           SET full_name = \$1,
               email     = \$2,
               phone     = \$3,
               trade_id  = \$4
         WHERE id = \$5
    ", [
        trim($_POST['full_name']),
        trim($_POST['email']),
        trim($_POST['phone']),
        (int)($_POST['trade_id'] ?? 0),
        $userId,
    ]);
    $_SESSION['profile_message'] = $res
        ? '✅ Profile updated.'
        : '❌ Error saving profile.';
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// 5) Fetch filenames
$res = pg_query_params($conn, "
    SELECT full_name,
           email,
           phone,
           trade_id,
           osha_front_filename,
           osha_back_filename
      FROM users
     WHERE id = \$1
", [$userId]);
if (! $res) {
    die('DB error: ' . pg_last_error($conn));
}
$user = pg_fetch_assoc($res) ?: [];

// 6) Flags & paths
$frontPath = $user['osha_front_filename'] ?? '';
$backPath  = $user['osha_back_filename']  ?? '';
$hasFront  = $frontPath !== '';
$hasBack   = $backPath  !== '';

// base URL for serving: e.g. /directory/uploads/{userId}/OSHA_Card/{file}
$uploadBase = '/directory/uploads/';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Profile — <?= htmlspecialchars($user['full_name'] ?? 'User') ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="/css/style.css">
  <style>
    .cards-container { display:flex; gap:2rem; justify-content:center; margin-top:1rem; }
    .card-section { text-align:center; }
    .preview { width:80px; height:80px; object-fit:cover; border:1px solid #ccc; border-radius:4px; cursor:pointer; }
    .placeholder { width:80px; height:80px; display:flex; align-items:center; justify-content:center; border:1px solid #ccc; border-radius:4px; color:#888; }
    #lightbox { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.8); align-items:center; justify-content:center; }
    #lightbox img { max-width:90%; max-height:90%; }
  </style>
</head>
<body>

  <h1>Profile — <?= htmlspecialchars($user['full_name'] ?? 'User') ?></h1>

  <?php if ($profileMsg): ?><div class="alert"><?= htmlspecialchars($profileMsg) ?></div><?php endif; ?>
  <?php if ($cardMsg):    ?><div class="alert"><?= htmlspecialchars($cardMsg)    ?></div><?php endif; ?>

  <!-- Profile Form -->
  <form class="profile-form" method="POST" action="">
    <label>Full Name<br>
      <input type="text" name="full_name" value="<?= htmlspecialchars($user['full_name'] ?? '') ?>">
    </label><br>
    <label>Email<br>
      <input type="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>">
    </label><br>
    <label>Phone<br>
      <input type="text" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
    </label><br>
    <label>Trade<br>
      <select name="trade_id">
        <?php
          $tr = pg_query($conn, "SELECT id, name FROM trades ORDER BY name");
          while ($t = pg_fetch_assoc($tr)): ?>
            <option value="<?= $t['id'] ?>"
              <?= ((string)$t['id'] === (string)$user['trade_id']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($t['name']) ?>
            </option>
        <?php endwhile; ?>
      </select>
    </label><br><br>
    <button type="submit">Save Profile</button>
  </form>

  <hr>

  <div class="cards-container">
    <!-- OSHA Front -->
    <div class="card-section">
      <h2>OSHA Front</h2>
      <?php if ($hasFront): ?>
        <img id="preview-front" class="preview"
             src="<?= htmlspecialchars($uploadBase . $frontPath) ?>"
             alt="OSHA Front"
             onclick="openLightbox(this.src)">
      <?php else: ?>
        <div class="placeholder">No Front</div>
      <?php endif; ?>

      <form method="POST" action="/upload/upload_osha_card.php" enctype="multipart/form-data">
        <input type="hidden" name="side" value="front">
        <input type="file" id="front_input" name="osha_card" accept="image/*,application/pdf" style="display:none"
               onchange="this.form.submit();">
        <button type="button" onclick="document.getElementById('front_input').click()">
          <?= $hasFront ? 'Replace Front' : 'Upload Front' ?>
        </button>
      </form>
    </div>

    <!-- OSHA Back -->
    <div class="card-section">
      <h2>OSHA Back</h2>
      <?php if ($hasBack): ?>
        <img id="preview-back" class="preview"
             src="<?= htmlspecialchars($uploadBase . $backPath) ?>"
             alt="OSHA Back"
             onclick="openLightbox(this.src)">
      <?php else: ?>
        <div class="placeholder">No Back</div>
      <?php endif; ?>

      <form method="POST" action="/upload/upload_osha_card.php" enctype="multipart/form-data">
        <input type="hidden" name="side" value="back">
        <input type="file" id="back_input" name="osha_card" accept="image/*,application/pdf" style="display:none"
               onchange="this.form.submit();">
        <button type="button" onclick="document.getElementById('back_input').click()">
          <?= $hasBack ? 'Replace Back' : 'Upload Back' ?>
        </button>
      </form>
    </div>
  </div>

  <!-- Lightbox -->
  <div id="lightbox" onclick="this.style.display='none';">
    <img src="" alt="Full View">
  </div>
  <script>
    function openLightbox(src) {
      const lb = document.getElementById('lightbox');
      lb.querySelector('img').src = src;
      lb.style.display = 'flex';
    }
  </script>

</body>
</html>
