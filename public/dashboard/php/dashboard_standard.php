<?php
declare(strict_types=1);
// public/dashboard/php/dashboard_standard.php

// 1) Bootstrapping
require_once __DIR__ . '/../../../config/db_connect.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('America/New_York');

// 2) Ensure user is logged in & define a single $userId
$userId = isset($_SESSION['id']) ? (int)$_SESSION['id'] : 0;
if ($userId <= 0) {
    header('Location: /login.php');
    exit;
}

// 3) Header Info
$display_name = htmlspecialchars($_SESSION['full_name'] ?? 'Visitor');
$date_today   = date('m/d/y');

// 4) Build OSHA‐Card URLs from filesystem
$uploadDir = __DIR__ . '/../../uploads/users/' . $userId . '/OSHA_Card';
$frontUrl = '';
$backUrl  = '';
if (is_dir($uploadDir)) {
    $all = array_values(array_diff(scandir($uploadDir), ['.', '..']));
    if (!empty($all[0])) {
        $frontUrl = '/uploads/users/' . $userId . '/OSHA_Card/' . rawurlencode($all[0]);
    }
    if (!empty($all[1])) {
        $backUrl = '/uploads/users/' . $userId . '/OSHA_Card/' . rawurlencode($all[1]);
    }
}

// 5) Handle Check-In POST
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['project_id'], $_POST['agreement'])
) {
    pg_query_params($conn, "
        INSERT INTO public.activities_log
          (user_id, project_id, check_in_date, check_in_clock, agreement)
        VALUES ($1, $2, CURRENT_DATE, CURRENT_TIME, TRUE)
    ", [
       $userId,
       (int)$_POST['project_id'],
    ]);
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// 6) Handle Check-Out POST

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['checkout_id'], $_POST['checkout_agreement'])
) {
    $checkoutId = (int)$_POST['checkout_id'];
    $comments   = trim($_POST['checkout_comments'] ?? '');

    pg_query_params($conn, "
      UPDATE public.activities_log
         SET check_out_date      = CURRENT_DATE,
             check_out_clock     = CURRENT_TIME,
             checkout_agreement  = TRUE,
             checkout_comments   = \$2
       WHERE id = \$1
    ", [
      $checkoutId,
      $comments
    ]);

    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}



// 7) Fetch any active check-in
$resActive = pg_query_params($conn, "
    SELECT al.id, al.check_in_date, al.check_in_clock, pa.project_name
      FROM public.activities_log al
      JOIN public.project_addresses pa
        ON pa.id = al.project_id
     WHERE al.user_id      = $1
       AND al.check_out_date IS NULL
     ORDER BY al.id DESC
     LIMIT 1
", [
  $userId
]);
$active     = pg_fetch_assoc($resActive) ?: null;
$start_time = $active
    ? strtotime($active['check_in_date'] . ' ' . $active['check_in_clock'])
    : null;

// 8) Fetch today's logs
$resLogsToday = pg_query_params($conn, "
    SELECT al.id,
           pa.project_name,
           pa.address_line1 AS address,
           al.check_in_clock,
           al.check_out_clock
      FROM public.activities_log al
      JOIN public.project_addresses pa
        ON pa.id = al.project_id
     WHERE al.user_id       = $1
       AND al.check_in_date = CURRENT_DATE
     ORDER BY al.check_in_clock
", [
  $userId
]);
$logsToday = $resLogsToday ? (pg_fetch_all($resLogsToday) ?: []) : [];

// 9) Fetch active projects for modal
$resP = pg_query($conn, "
    SELECT id, project_name, address_line1
      FROM public.project_addresses
     WHERE LOWER(status) = 'active'
");
$projects = pg_fetch_all($resP) ?: [];

// 10) Fetch safety note
$resNote    = pg_query_params($conn, "SELECT note FROM safety_notes WHERE id = 1", []);
$safetyNote = $resNote ? pg_fetch_result($resNote, 0, 'note') : '';

// right after loading the check-in note:
$resChkOut = pg_query_params(
  $conn,
  "SELECT note FROM safety_notes WHERE id = \$1",
  [2]
);
$checkoutNote = $resChkOut
  ? pg_fetch_result($resChkOut, 0, 'note')
  : '';








?>











<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Standard Dashboard</title>
  <link rel="stylesheet" href="/dashboard/styles/dashboard.css">
</head>
<body>



  <!-- OSHA Card Preview -->
  <div class="dashboard-osha-preview">
    <h3>OSHA Card Preview</h3>

    <?php if ($frontUrl || $backUrl): ?>
      <div class="slider-container">
        <div class="slider-track" id="oshaSlider">
          <?php if ($frontUrl): ?>
            <div class="slider-slide">
              <img
  class="preview"
  src="/osha-image.php?side=front&user_id=<?= $userId ?>"
  alt="OSHA Front"
  onclick="openLightbox(this.src)">

            </div>
          <?php endif; ?>
          <?php if ($backUrl): ?>
            <div class="slider-slide">
              <img
  class="preview"
  src="/osha-image.php?side=back&user_id=<?= $userId ?>"
  alt="OSHA Back"
  onclick="openLightbox(this.src)">

            </div>
          <?php endif; ?>
        </div>
        <div class="slider-nav">
          <button id="prevSlide" aria-label="Previous">&larr;</button>
          <button id="nextSlide" aria-label="Next">&rarr;</button>
        </div>
      </div>
    <?php else: ?>
      <div class="placeholder">No OSHA Card uploaded</div>
    <?php endif; ?>
  </div>

  <!-- Lightbox overlay -->
  <div id="lightbox" onclick="this.style.display='none'">
    <img src="" alt="Full View">
  </div>

  <!-- Header Info -->
  <h2><?= $display_name ?></h2>
  <p style="text-align:center; color:#666;"><?= $date_today ?></p>

    <!-- Check-In/Out Controls -->
  <div class="dashboard-controls">
    <?php if ($active): ?>
      <p>
        <strong><?= htmlspecialchars($active['project_name']) ?></strong><br>
        <span id="checkInTimer" style="font-weight:bold;">00:00:00</span>
      </p>
      <button id="checkOutBtn" class="btn-checkout">CHECK OUT</button>
    <?php else: ?>
      <button id="checkInBtn" class="btn-checkin">CHECK IN</button>
    <?php endif; ?>
  </div>
  
<!-- Check-Out Confirmation Modal -->
<div id="checkOutModal" class="modal">
  <div class="modal-box">
  <img src="/assets/images/safety_first.png" alt="Safety First" style="width:100%; margin-bottom:1rem;">           

    <!-- Read-only Check-Out Note -->
     <br>
    <label><strong>Disclaimer:</strong></label>
    <div class="disclaimer-text">
      <?= nl2br(htmlspecialchars($checkoutNote)) ?>

<br>
    <!-- Required acknowledgement -->
    <label class="modal-checkbox">
      <input type="checkbox"
             name="checkout_agreement"
             required>
      I confirm all details are correct.
    </label>
    </div>
    <!-- Actions -->
    <div class="modal-actions">
      <button type="submit" form="checkOutForm" class="btn-checkout">
        Check Out
      </button>

    </div>

    <!-- Hidden form (so that comments + agreement + checkout_id go together) -->
    <form id="checkOutForm" method="POST" style="display:none;">
      <input type="hidden" name="checkout_id" value="<?= (int)$active['id'] ?>">
      <input type="hidden" name="checkout_comments"    id="hidden_comments">
      <input type="hidden" name="checkout_agreement"   id="hidden_agreement">
    </form>
  </div>
</div>





  <!-- Today's Detail Logs -->
  <?php if ($logsToday): ?>
    <div class="today-details">
      <?php foreach ($logsToday as $log):
        $in  = substr($log['check_in_clock'], 0, 5);
        $out = $log['check_out_clock'] ? substr($log['check_out_clock'], 0, 5) : '';
      ?>
        <div class="assignment-tag">
          <div class="time-range"><?= $in ?> – <?= $out ?></div>
          <div class="project-info">
            <span class="project-name"><?= htmlspecialchars($log['project_name']) ?></span>
            <span class="project-address"><?= htmlspecialchars($log['address']) ?></span>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="assignment-none">No activity for today</div>
  <?php endif; ?>

  <!-- Safety Posters -->
  <div style="max-width:100%; margin:0 auto 1rem;">
    <img src="/assets/images/safety_guy.png" alt="Safety Guy" style="width:100%; border-radius:1rem;">
  </div>
  <div style="max-width:100%; margin:0 auto 2rem;">
    <img src="/assets/images/site_safety.png" alt="Site Safety Poster" style="width:100%; border-radius:1rem;">
  </div>


  <!-- Project Selection Modal -->
  <div id="addRecordModal">
    <div class="modal-box">
      <img src="/assets/images/safety_first.png" alt="Safety First" style="width:100%; margin-bottom:1rem;">
      <form method="POST" style="display:flex; flex-direction:column; gap:1rem;">
        <input type="hidden" name="timezone_offset" id="timezone_offset">
        <?php foreach ($projects as $p): ?>
          <label style="display:flex; align-items:center;">
            <input type="radio" name="project_id" value="<?= $p['id'] ?>" required style="margin-right:.5rem;
            ">
            <span><?= htmlspecialchars($p['project_name']) ?> — <?= htmlspecialchars($p['address_line1']) ?></span>
          </label>
        <?php endforeach; ?>
        <?php if (trim($safetyNote)): ?>
          <div>
            <strong>Disclaimer:</strong>
            <p><?= nl2br(htmlspecialchars($safetyNote)) ?></p>
          </div>
        <?php endif; ?>
        <label style="display:flex; align-items:center; font-weight:bold;">
          <input type="checkbox" name="agreement" required style="margin-right:.5rem;">
          I acknowledge the disclaimer
        </label>
        <div class="modal-actions">
          <button type="submit" class="btn-checkin">CHECK IN</button>
          <button type="button" onclick="closeModal()" class="btn-cancel">CANCEL</button>
        </div>
      </form>
    </div>
  </div>


  




  <?php require __DIR__ . '/../../../includes/footer.php'; ?>

  <!-- JS: lightbox opener -->
  <script>
  function openLightbox(src) {
    const lb = document.getElementById('lightbox');
    lb.querySelector('img').src = src;
    lb.style.display = 'flex';
  }
  </script>

  <!-- JS: slider behavior + modal -->
  <script>
  (function() {
    // slider
    const track = document.getElementById('oshaSlider');
    if (track) {
      let idx = 0;
      const slides = track.children;
      const show = i => {
        idx = (i + slides.length) % slides.length;
        track.style.transform = `translateX(-${idx*100}%)`;
      };
      document.getElementById('prevSlide').onclick = () => show(idx - 1);
      document.getElementById('nextSlide').onclick = () => show(idx + 1);
      show(0);
    }

    // modal toggle
    const modal = document.getElementById('addRecordModal');
    document.getElementById('checkInBtn')?.addEventListener('click', () => {
      modal.classList.add('active');
      document.body.classList.add('modal-open');
    });
    window.closeModal = () => {
      modal.classList.remove('active');
      document.body.classList.remove('modal-open');
    };
    modal.addEventListener('click', e => {
      if (e.target === modal) closeModal();
    });
    

document.getElementById('checkOutBtn')



  // open the checkout modal
document.getElementById('checkOutBtn')?.addEventListener('click', () => {
  document.getElementById('checkOutModal').classList.add('active');
  document.body.classList.add('modal-open');
});

// close it
function closeCheckOut() {
  document.getElementById('checkOutModal').classList.remove('active');
  document.body.classList.remove('modal-open');
}

// also allow clicking outside the box to close
document.getElementById('checkOutModal')?.addEventListener('click', e => {
  if (e.target.id === 'checkOutModal') closeCheckOut();
});











  


    // timezone offset
    document.getElementById('timezone_offset').value = new Date().getTimezoneOffset();

    // timer
    const timerEl = document.getElementById('checkInTimer');
    const start = <?php echo $start_time ? $start_time*1000 : 'null'; ?>;
    if (timerEl && start) {
      (function tick() {
        const d = Date.now() - start;
        const h = String(Math.floor(d/3600000)).padStart(2,'0');
        const m = String(Math.floor((d%3600000)/60000)).padStart(2,'0');
        const s = String(Math.floor((d%60000)/1000)).padStart(2,'0');
        timerEl.textContent = `${h}:${m}:${s}`;
        setTimeout(tick, 1000);
      })();
    }
  })();
  </script>

</body>
</html>
