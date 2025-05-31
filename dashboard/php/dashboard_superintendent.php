<?php
// public/dashboard/superintendent.php

declare(strict_types=1);
// 1) Bootstrap: starts session, sets timezone, connects to $conn
require_once __DIR__ . '/../../bootstrap.php';

// 2) Ensure logged in
$userId = $_SESSION['id'] ?? null;
if (!$userId) {
    header('Location: /index.php');
    exit;
}

// 3) Only allow Superintendents
$roleSlug = strtolower(str_replace(' ', '_', trim($_SESSION['role'] ?? '')));
if ($roleSlug !== 'superintendent') {
    header('Location: /dashboard/project_manager.php');
    exit;
}

// 4) Fetch today’s activity logs
$sql = "
    SELECT
      cl.check_in_clock,
      cl.check_out_clock,
      u.full_name        AS user_name,
      pa.address_line1   AS project_address
    FROM public.activities_log cl
    JOIN public.users u  ON cl.user_id   = u.id
    JOIN public.project_addresses pa
      ON cl.project_id = pa.id
    WHERE cl.check_in_date = CURRENT_DATE
    ORDER BY cl.check_in_clock DESC
";
$res  = pg_query($conn, $sql);
$logs = $res ? pg_fetch_all($res) : [];
?>
<div class="container edge-to-edge panel-wrapper">

  <div class="panel">
    <div class="panel__header">
      <h1>Superintendent Dashboard</h1>
      <p>Welcome, <?= htmlspecialchars($_SESSION['full_name'] ?? 'Superintendent') ?>. Monitor your jobsite progress and compliance here.</p>
    </div>
    <div class="panel__body">
      <h2>Today’s Activity Logs</h2>
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
                        date('g:i A', strtotime($log['check_in_clock']))
                      ) ?>
                </td>
                <td>
                  <?= htmlspecialchars(
                        date('g:i A', strtotime($log['check_out_clock'] ?? 'now'))
                      ) ?>
                </td>
                <td><?= htmlspecialchars($log['user_name']) ?></td>
                <td><?= htmlspecialchars($log['project_address']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p><em>No activity logged today.</em></p>
      <?php endif; ?>
    </div>
  </div>

</div>


<script src="assets/js/sw-register.js"></script>
