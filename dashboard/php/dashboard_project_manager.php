<?php
// public/dashboard/project_manager.php

declare(strict_types=1);
// 1) Bootstrap (starts session, sets timezone, connects $conn)
require_once __DIR__ . '/../../bootstrap.php';

// 2) Ensure logged in
$userId = $_SESSION['id'] ?? null;
if (!$userId) {
    header('Location: /index.php');
    exit;
}

// 3) Only allow Project Manager role
$roleSlug = strtolower(str_replace(' ', '_', trim($_SESSION['role'] ?? '')));
if ($roleSlug !== 'project_manager') {
    header('Location: /dashboard_admin.php');
    exit;
}

// 4) Fetch this PM’s projects
$queryProjects = "
    SELECT 
      pa.id,
      pa.address_line1 AS project_name,
      pa.city,
      pa.state,
      pa.zip_code
    FROM project_addresses pa
    WHERE pa.project_manager_id = \$1
    ORDER BY pa.address_line1
";
$projRes  = pg_query_params($conn, $queryProjects, [$userId]);
$projects = $projRes ? pg_fetch_all($projRes) : [];

// 5) Fetch today’s activity logs for those projects
$queryLogs = "
    SELECT
      cl.check_in_date,
      cl.check_out_date,
      u.full_name      AS user_name,
      pa.address_line1 AS project_name
    FROM activities_log cl
    JOIN users u  ON cl.user_id   = u.id
    JOIN project_addresses pa
      ON cl.project_id = pa.id
    WHERE cl.check_in_date = CURRENT_DATE
      AND pa.project_manager_id = \$1
    ORDER BY cl.check_in_date DESC
";
$logRes = pg_query_params($conn, $queryLogs, [$userId]);
$logs   = $logRes ? pg_fetch_all($logRes) : [];
?>
<div class="container edge-to-edge panel-wrapper">

  <div class="panel">
    <div class="panel__header">
      <h1>Project Manager Dashboard</h1>
      <p>Welcome, <?= htmlspecialchars($_SESSION['full_name'] ?? '') ?>. Here are your assigned projects:</p>
    </div>
    <div class="panel__body">
      <?php if (!empty($projects)): ?>
        <table class="styled-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Project</th>
              <th>City</th>
              <th>State</th>
              <th>ZIP</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($projects as $proj): ?>
              <tr>
                <td><?= htmlspecialchars($proj['id']) ?></td>
                <td><?= htmlspecialchars($proj['project_name']) ?></td>
                <td><?= htmlspecialchars($proj['city']) ?></td>
                <td><?= htmlspecialchars($proj['state']) ?></td>
                <td><?= htmlspecialchars($proj['zip_code']) ?></td>
                <td>
                  <a href="/projects/view.php?id=<?= $proj['id'] ?>"
                     class="btn btn-sm btn-primary">
                    View
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p><em>No projects assigned to you yet.</em></p>
      <?php endif; ?>
    </div>
  </div>

  <div class="panel">
    <div class="panel__header">
      <h2>Today’s Activity Logs</h2>
    </div>
    <div class="panel__body">
      <?php if (!empty($logs)): ?>
        <table class="styled-table">
          <thead>
            <tr>
              <th>Check-In</th>
              <th>Check-Out</th>
              <th>User</th>
              <th>Project</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($logs as $log): ?>
              <tr>
                <td>
                  <?= htmlspecialchars(
                        date('g:i A', strtotime($log['check_in_date']))
                      ) ?>
                </td>
                <td>
                  <?= htmlspecialchars(
                        date('g:i A', strtotime($log['check_out_date']))
                      ) ?>
                </td>
                <td><?= htmlspecialchars($log['user_name']) ?></td>
                <td><?= htmlspecialchars($log['project_name']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p><em>No activity logged today for your projects.</em></p>
      <?php endif; ?>
    </div>
  </div>

</div>

<?php
// 6) Footer & service-worker registration
require_once __DIR__ . '/../../includes/footer.php';
?>
<script src="assets/js/sw-register.js"></script>
