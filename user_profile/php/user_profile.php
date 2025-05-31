<?php
declare(strict_types=1);

// 1) Bootstrap & Session
require_once __DIR__ . '/../../bootstrap.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// 2) Must be logged in
$userId = (int)($_SESSION['id'] ?? 0);
if ($userId <= 0) {
    header('Location: /login.php');
    exit;
}

// 3) Handle profile-text update
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['full_name']) &&
    empty($_FILES)
) {
    $res = pg_query_params($conn, "
        UPDATE users
           SET full_name = \$1,
               email     = \$2,
               phone     = \$3
         WHERE id = \$4
    ", [
        trim($_POST['full_name']),
        trim($_POST['email']),
        trim($_POST['phone']),
        $userId,
    ]);
    $_SESSION['profile_message'] = $res
        ? '✅ Profile updated.'
        : '❌ Error saving profile.';
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// 4) Fetch user info
$res = pg_query_params($conn, "
    SELECT full_name,
           email,
           phone
      FROM users
     WHERE id = $1
", [$userId]);

if (! $res) {
    die('DB error: ' . pg_last_error($conn));
}
$user = pg_fetch_assoc($res) ?: [];

// 5) OSHA file logic
$oshaDir = "D:/MMB-OSHA/Uploads/{$userId}/OSHA/";

function findImage(string $dir, string $side): ?string {
    foreach (['jpg', 'jpeg', 'png'] as $ext) {
        $filename = "{$side}.{$ext}";
        if (file_exists($dir . $filename)) {
            return $filename;
        }
    }
    return null;
}

$frontFile = findImage($oshaDir, 'front');
$backFile  = findImage($oshaDir, 'back');

$hasFront = !empty($frontFile);
$hasBack  = !empty($backFile);

$frontUrl = $hasFront ? "/uploads/users/{$userId}/OSHA/front.jpg" . filemtime($oshaDir . $frontFile) : '';
$backUrl  = $hasBack  ? "/uploads/users/{$userId}/OSHA/back.jpg"  . filemtime($oshaDir . $backFile)  : '';


error_log("Front OSHA path: " . $oshaDir . $frontFile);
error_log("Back OSHA path: " . $oshaDir . $backFile);

// 6) Flash messages
$profileMsg = $_SESSION['profile_message'] ?? '';
unset($_SESSION['profile_message']);
$cardMsg = $_SESSION['card_message'] ?? '';
unset($_SESSION['card_message']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Profile — <?= htmlspecialchars($user['full_name'] ?? 'User') ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="/css/style.css">
  <link rel="stylesheet" href="../styles/user_profile.css">
</head>
<body>

<h1><?= htmlspecialchars($user['full_name'] ?? 'User') ?></h1>

<?php if ($profileMsg): ?><div class="alert"><?= htmlspecialchars($profileMsg) ?></div><?php endif; ?>
<?php if ($cardMsg): ?><div class="alert"><?= htmlspecialchars($cardMsg) ?></div><?php endif; ?>

<div class="cards-container">
  <h2>OSHA Card</h2>

 <div class="carousel">

  <!-- Front -->
  <div class="carousel-item">
    <div class="img-wrap">
      <?php if ($hasFront): ?>
        <img
           class="preview"
  src="/osha-image.php?side=front&user_id=<?= $userId ?>"
  alt="OSHA Card Front"
  data-side="front"
  onclick="openLightbox(this.src, this.dataset.side)">
      <?php else: ?>
        <div class="placeholder">No Front</div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Back -->
  <div class="carousel-item">
    <div class="img-wrap">
      <?php if ($hasBack): ?>
<img
  class="preview"
  src="/osha-image.php?side=back&user_id=<?= $userId ?>"
  alt="OSHA Card Back"
  data-side="back"
  onclick="openLightbox(this.src, this.dataset.side)">

        <div class="placeholder">No Back</div>
      <?php endif; ?>
    </div>
  </div>

</div>



  <div class="upload-buttons">
    <!-- Front -->
    <form method="POST" action="OSHA/upload_osha_card.php" enctype="multipart/form-data">
      <input type="hidden" name="side" value="front">
      <input type="file" id="front_input" name="osha_card" accept="image/*" style="display:none" onchange="this.form.submit()">
      <button type="button" onclick="document.getElementById('front_input').click()">
        <?= $hasFront ? 'Replace Front' : 'Upload Front' ?>
      </button>
    </form>

    <!-- Back -->
    <form method="POST" action="OSHA/upload_osha_card.php" enctype="multipart/form-data">
      <input type="hidden" name="side" value="back">
      <input type="file" id="back_input" name="osha_card" accept="image/*" style="display:none" onchange="this.form.submit()">
      <button type="button" onclick="document.getElementById('back_input').click()">
        <?= $hasBack ? 'Replace Back' : 'Upload Back' ?>
      </button>
    </form>
  </div>
</div>

<!-- Profile Info Form -->
<form class="profile-form" method="POST">
  <label>Full Name<br>
    <input type="text" name="full_name" value="<?= htmlspecialchars($user['full_name'] ?? '') ?>">
  </label><br>

  <label>Email<br>
    <input type="text" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>">
  </label><br>

  <label>Phone<br>
    <input type="text" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
  </label><br>

  <br>
  <button type="submit">Save Profile</button>
</form>

<hr>

<!-- Lightbox -->
<div id="lightbox" onclick="closeLightbox(event)">
  <button id="lightbox-delete-btn" style="display:none;">&times; Delete</button>
  <img src="" alt="Full View">
</div>

<script>
  let currentSide = null;

  function openLightbox(src, side) {
    currentSide = side;
    const lb = document.getElementById('lightbox');
    lb.querySelector('img').src = src;
    document.getElementById('lightbox-delete-btn').style.display = 'block';
    lb.style.display = 'flex';
  }

  function closeLightbox(event) {
    if (event.target.id === 'lightbox') {
      document.getElementById('lightbox').style.display = 'none';
    }
  }

  document.getElementById('lightbox-delete-btn').addEventListener('click', function (e) {
    e.stopPropagation();
    if (confirm('Delete this ' + currentSide + ' card?')) {
      window.location.href = 'OSHA/delete_osha_card.php?side=' + currentSide;
    }
  });
</script>

</body>
</html>
