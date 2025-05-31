<?php
// public/dashboard_admin.php

declare(strict_types=1);
require_once __DIR__ . '/../../bootstrap.php';   // session_start(), DB connect, etc.

$user_id = $_SESSION['id'] ?? null;
$role    = strtolower($_SESSION['role'] ?? '');
if (!$user_id || $role !== 'admin') {
    header('Location: /login.php');
    exit;
}

// — Fetch summary counts —
$resUsers    = pg_query($conn, "SELECT COUNT(*) AS cnt FROM users");
$resProjects = pg_query($conn, "SELECT COUNT(*) AS cnt FROM project_addresses");
$totalUsers    = (int) pg_fetch_result($resUsers, 0, 'cnt');
$totalProjects = (int) pg_fetch_result($resProjects, 0, 'cnt');

// — Fetch projects for modify form —
$resProjList = pg_query($conn, "SELECT id, project_name FROM public.project_addresses ORDER BY project_name");
$projectList = $resProjList ? pg_fetch_all($resProjList) : [];

// — Fetch today’s activity logs —
$resLogs = pg_query($conn, "
  SELECT
    al.id             AS log_id,
    al.project_id     AS project_id,
    al.check_in_clock,
    al.check_out_clock,
    u.id              AS user_id,
    u.full_name       AS user_name,
    pa.project_name,
    pm.full_name      AS project_manager,
    su.full_name      AS superintendent
  FROM public.activities_log al
  LEFT JOIN public.users u  ON u.id = al.user_id
  LEFT JOIN public.project_addresses pa ON pa.id = al.project_id
  LEFT JOIN public.users pm ON pm.id = pa.project_manager_id
  LEFT JOIN public.users su ON su.id = pa.superintendent_id
  WHERE al.check_in_date = CURRENT_DATE
  ORDER BY al.check_in_clock
");
$activityLogs = $resLogs ? pg_fetch_all($resLogs) : [];
?>

<div class="container edge-to-edge panel-wrapper">

  <div class="panel">
    <div class="panel__header">
      <h1>Admin Dashboard</h1>
      <p>Welcome, <?= htmlspecialchars($_SESSION['full_name'] ?? 'Admin') ?>. Here’s an overview of your system:</p>
      <ul class="dashboard-summary">
        <li><strong>Total Users:</strong> <?= $totalUsers ?></li>
        <li><strong>Total “Projects”:</strong> <?= $totalProjects ?></li>
        <li><strong>Permissions:</strong> <a href="/dashboard/admin/nav_permissions.php">Edit Nav Permissions</a></li>
      </ul>
    </div>
  </div>

  <div class="panel">
    <div class="panel__header">
      <h2>Today’s Activity Logs</h2>
    </div>
    <div class="panel__body">
      <table class="styled-table">
        <thead>
          <tr>
            <th>Activity</th>
            <th>Duration</th>
            <th>User</th>
            <th>Project</th>
            <th>Project Manager</th>
            <th>Superintendent</th>
            <th>OSHA Card</th>
            <th>Modify</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($activityLogs)): ?>
            <tr class="active-row">
              <td colspan="8" style="text-align:center;">No activity logged today.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($activityLogs as $log): ?>
              <?php
                // Initialize and format check-in/out and duration
                $inTime = $outTime = $duration = '';
                if (!empty($log['check_in_clock'])) {
                  $inDt   = new DateTime($log['check_in_clock']);
                  $inTime = $inDt->format('g:i a');
                  $outDt  = !empty($log['check_out_clock'])
                          ? new DateTime($log['check_out_clock'])
                          : new DateTime();
                  $outTime = $outDt->format('g:i a');
                  $diff    = $inDt->diff($outDt);
                  $duration = sprintf('%d:%02d', $diff->h + ($diff->d * 24), $diff->i);
                }

                // Gather OSHA card URLs
                $docRoot   = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
                $uploadDir = $docRoot . '/uploads/users/OSHA_Cards/' . $log['user_id'];
                $publicBase = '/uploads/users/OSHA_Cards/' . $log['user_id'] . '/';
                $urls = [];
                $thumb = '';

                if (is_dir($uploadDir)) {
                  foreach (array_diff(scandir($uploadDir), ['.', '..']) as $file) {
                    $urls[] = $publicBase . rawurlencode($file);
                  }
                  if (!empty($urls)) {
                    $thumb = $urls[0];
                  }
                }

                $jsonUrls = htmlspecialchars(json_encode($urls));
              ?>
              <tr>
                <td data-label="Activity"><?= htmlspecialchars($inTime . ($outTime ? ' – ' . $outTime : '')) ?></td>
                <td data-label="Duration"><?= htmlspecialchars($duration) ?></td>
                <td data-label="User"><?= htmlspecialchars($log['user_name'] ?: 'Visitor') ?></td>
                <td data-label="Project"><?= htmlspecialchars($log['project_name'] ?: '') ?></td>
                <td data-label="Project Manager"><?= htmlspecialchars($log['project_manager'] ?: '') ?></td>
                <td data-label="Superintendent"><?= htmlspecialchars($log['superintendent'] ?: '') ?></td>
                <td data-label="OSHA Card">
                  <?php if ($thumb): ?>
                    <a href="#" class="card-thumbnail" data-files='<?= $jsonUrls ?>'>
                      <img src="<?= htmlspecialchars($thumb) ?>" alt="OSHA Card"
                           style="width:40px;height:40px;object-fit:cover;border-radius:4px;cursor:pointer;" />
                    </a>
                  <?php else: ?>
                    &ndash;
                  <?php endif; ?>
                </td>
                <td data-label="Modify">
                  <button
                    class="open-modify-modal btn-modify"
                    data-log-id="<?= (int)$log['log_id'] ?>"
                    data-in="<?= (new DateTime($log['check_in_clock']))->format('H:i') ?>"
                    data-out="<?= (new DateTime($log['check_out_clock'] ?? 'now'))->format('H:i') ?>"
                    data-project-id="<?= (int)$log['project_id'] ?>"
                    data-return="/dashboard/php/dashboard_admin.php"
                  >
                    MODIFY
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modify Modal -->
<div id="modify-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;
      background:rgba(0,0,0,0.6);align-items:center;justify-content:center;z-index:1000;">
  <div style="background:#fff;padding:1.5rem;border-radius:8px;max-width:400px;width:90%;position:relative;">
    <span id="modify-close" style="position:absolute;top:8px;right:12px;font-size:1.5rem;cursor:pointer;">
      &times;
    </span>
    <h3>Modify Activity</h3>
    <form id="modify-form" method="POST" action="">
      <label>Check-In</label>
      <input type="time" id="mod-check-in" name="check_in_clock" required style="width:100%;margin:0.5rem 0;"/>
      <label>Check-Out</label>
      <input type="time" id="mod-check-out" name="check_out_clock" style="width:100%;margin:0.5rem 0;"/>
      <label for="mod-project">Project</label>
      <select id="mod-project" name="project_id" required style="width:100%;margin:0.5rem 0;">
        <option value="">— Select project —</option>
        <?php foreach ($projectList as $p): ?>
          <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['project_name'], ENT_QUOTES) ?></option>
        <?php endforeach; ?>
      </select>
      <div style="text-align:right;margin-top:1rem;">
        <button type="submit" class="btn-primary">Save</button>
        <button type="button" id="modify-cancel" class="btn-link">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Preview Modal -->
<div id="preview-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;
      background:rgba(0,0,0,0.8);align-items:center;justify-content:center;z-index:1000;">
  <div style="position:relative;display:flex;align-items:center;justify-content:center;">
    <span id="modal-close" style="position:absolute;top:8px;right:12px;color:#fff;font-size:1.5rem;cursor:pointer;">
      &times;
    </span>
    <span class="nav-btn prev-btn" style="position:absolute;left:12px;color:#fff;font-size:2rem;cursor:pointer;">
      &#10094;
    </span>
    <img id="modal-img" src="" alt="Preview"
         style="max-width:90vw;max-height:80vh;border-radius:4px;" />
    <span class="nav-btn next-btn" style="position:absolute;right:12px;color:#fff;font-size:2rem;cursor:pointer;">
      &#10095;
    </span>
  </div>
</div>

<script>
// Modify modal handlers
const modifyModal = document.getElementById('modify-modal');
const modifyForm  = document.getElementById('modify-form');

document.querySelectorAll('.open-modify-modal').forEach(btn => {
  btn.addEventListener('click', () => {
    document.getElementById('mod-check-in').value  = btn.dataset.in;
    document.getElementById('mod-check-out').value = btn.dataset.out;
    document.getElementById('mod-project').value   = btn.dataset.projectId;

    const id   = btn.dataset.logId;
    const back = encodeURIComponent(btn.dataset.return);
    modifyForm.action = `/activity/api/modify_activity.php?id=${id}&return=${back}`;
    modifyModal.style.display = 'flex';
  });
});

// Close handlers
document.getElementById('modify-close').onclick =
document.getElementById('modify-cancel').onclick = () => {
  modifyModal.style.display = 'none';
};

// Preview modal handlers
let files = [], idx = 0;
document.querySelectorAll('.card-thumbnail').forEach(el => {
  el.addEventListener('click', e => {
    e.preventDefault();
    files = JSON.parse(el.getAttribute('data-files'));
    idx = 0;
    document.getElementById('modal-img').src = files[idx] || '';
    document.getElementById('preview-modal').style.display = 'flex';
  });
});
document.getElementById('modal-close').onclick = () =>
  document.getElementById('preview-modal').style.display = 'none';
document.querySelector('.prev-btn').onclick = () => {
  idx = (idx - 1 + files.length) % files.length;
  document.getElementById('modal-img').src = files[idx];
};
document.querySelector('.next-btn').onclick = () => {
  idx = (idx + 1) % files.length;
  document.getElementById('modal-img').src = files[idx];
};
</script>
