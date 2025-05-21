<?php
// public/dashboard/admin.php

session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit;
}

require_once __DIR__ . '/../../config/db_connect.php';

// Load your full site header (navbar, profile menu, logo)
require_once __DIR__ . '/../../includes/header.php';
?>

<link rel="stylesheet" href="/assets/css/style.css">


  <h1 style="text-align:center; margin:1.5rem 0; font-size:1.75rem; font-weight:bold;">
    TODAY
  </h1>

<div class="container page-content">


  <!-- Toolbar: search + Add button -->
  <div class="activity-toolbar">
    <input
      type="search"
      id="activitySearch"
      class="input-search"
      placeholder="Type here to search"
    />
    <button
      id="addActivity"
      class="btn-primary"
    >
      ADD NEW
    </button>
  </div>

  <?php
    $sql = "
      SELECT
        u.full_name      AS user_name,
        pa.address_line1 AS project_address,
        cl.check_in_date,
        cl.check_in_clock,
        cl.check_out_clock,
        cl.duration
      FROM check_log cl
      LEFT JOIN users u ON cl.user_id = u.id
      LEFT JOIN project_addresses pa ON cl.project_id = pa.id
      WHERE cl.check_in_date::date = current_date
        AND cl.project_id IS NOT NULL
      ORDER BY cl.check_in_clock DESC
      LIMIT 500
    ";

    $res  = pg_query($conn, $sql);
    $rows = $res ? pg_fetch_all($res) : [];
  ?>

  <table class="styled-table">
    <thead>
      <tr>
        <th>Name</th>
        <th>Project Address</th>
        <th>Date</th>
        <th>Check In</th>
        <th>Check Out</th>
        <th>Duration</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($rows): ?>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= htmlspecialchars($r['user_name']      ?? '') ?></td>
            <td><?= htmlspecialchars($r['project_address'] ?? '—') ?></td>
            <td><?= htmlspecialchars($r['check_in_date']   ?? '') ?></td>
            <td><?= htmlspecialchars($r['check_in_clock']  ?? '') ?></td>
            <td><?= htmlspecialchars($r['check_out_clock'] ?? '') ?></td>
            <td><?= htmlspecialchars($r['duration']        ?? '') ?></td>
          </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr>
          <td colspan="6" class="text-center">No records for today.</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
