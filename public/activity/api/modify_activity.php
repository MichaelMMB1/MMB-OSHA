<?php
declare(strict_types=1);

// 1) Bootstrap
require_once rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/bootstrap.php'; // session_start(), $conn, etc.

// 2) Auth check
$user_id = $_SESSION['id'] ?? null;
$role    = strtolower($_SESSION['role'] ?? '');
if (!$user_id || $role !== 'admin') {
    header('Location: /login.php');
    exit;
}

// 3) Pull in parameters
$logId    = isset($_GET['id'])     ? (int)$_GET['id'] : 0;
$returnTo = $_GET['return']        ?? '/dashboard/php/dashboard_admin.php';

// 4) No ID? bounce back immediately
if (!$logId) {
    header("Location: {$returnTo}");
    exit;
}

// 5) Load the existing log record
$resLog = pg_query_params($conn, "
    SELECT *
      FROM public.activities_log
     WHERE id = $1
", [ $logId ]);
$log = $resLog ? pg_fetch_assoc($resLog) : null;
if (!$log) {
    header("Location: {$returnTo}");
    exit;
}

// 6) Handle POST (update)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inTime    = $_POST['check_in_clock']  ?? null;
    $outTime   = $_POST['check_out_clock'] ?? null;
    $projectId = $_POST['project_id']      ?? null;

    $res = pg_query_params($conn, "
        UPDATE public.activities_log
           SET check_in_clock  = $1,
               check_out_clock = $2,
               project_id      = $3
         WHERE id              = $4
    ", [ $inTime, $outTime, $projectId, $logId ]);

    if ($res) {
        header("Location: {$returnTo}");
        exit;
    } else {
        $error = 'Failed to update record.';
    }
}

// 7) Get project list for dropdown
$resProjects = pg_query($conn, "
    SELECT id, project_name
      FROM public.project_addresses
  ORDER BY project_name
");
$projects = $resProjects ? pg_fetch_all($resProjects) : [];
?>
<div class="container edge-to-edge panel-wrapper">
  <div class="panel">
    <div class="panel__header">
      <h1>Modify Activity Log</h1>
    </div>
    <div class="panel__body">
      <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form
        method="POST"
        action="/activity/api/modify_activity.php?id=<?= $logId ?>&return=<?= urlencode($returnTo) ?>"
      >
        <div class="form-group">
          <label for="check_in_clock">Check-In Time</label>
          <input
            type="time"
            id="check_in_clock"
            name="check_in_clock"
            value="<?= htmlspecialchars(substr($log['check_in_clock'], 0, 5)) ?>"
            required
          />
        </div>

        <div class="form-group">
          <label for="check_out_clock">Check-Out Time</label>
          <input
            type="time"
            id="check_out_clock"
            name="check_out_clock"
            value="<?= htmlspecialchars($log['check_out_clock'] ? substr($log['check_out_clock'], 0, 5) : '') ?>"
          />
        </div>

        <div class="form-group">
          <label for="project_id">Project</label>
          <select id="project_id" name="project_id" required>
            <?php foreach ($projects as $proj): ?>
              <option
                value="<?= $proj['id'] ?>"
                <?= $proj['id'] === (int)$log['project_id'] ? 'selected' : '' ?>
              >
                <?= htmlspecialchars($proj['project_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-actions">
          <button type="submit" class="btn-primary">Save Changes</button>
          <a href="<?= htmlspecialchars($returnTo) ?>" class="btn-link">Cancel</a>
        </div>
      </form>
    </div>
  </div>
</div>
