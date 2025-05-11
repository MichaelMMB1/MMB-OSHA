<?php
// public/activity/activity.php

// 1) Session & auth
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

// 2) Shared header/nav
require_once(__DIR__ . '/../../includes/header.php');
// 3) DB connection
require_once(__DIR__ . '/../../config/db_connect.php');

// 4) Read filters
$filterToday = isset($_GET['filter_today']) && $_GET['filter_today'] === '1';
$where   = [];
$params  = [];

// “Today” filter
if ($filterToday) {
    $where[] = "cl.check_in_date = CURDATE()";
}

// Week-range filter (tabs)
$wr = isset($_GET['week_range']) && in_array($_GET['week_range'], ['0','1','2','4'], true)
    ? $_GET['week_range']
    : '0';

if ($wr !== '0') {
    $interval = $wr === '1' ? '1 WEEK'
              : ($wr === '2' ? '2 WEEK' : '4 WEEK');
    $where[] = "cl.check_in_date >= DATE_SUB(CURDATE(), INTERVAL $interval)";
}

// 5) Build & run query
$sql = "
  SELECT
    u.full_name,
    cl.location       AS project,
    cl.check_in_date  AS in_date,
    cl.check_in_clock AS in_time,
    cl.check_out_date AS out_date,
    cl.check_out_clock AS out_time,
    IF(
      cl.check_out_date IS NOT NULL,
      TIMESTAMPDIFF(
        SECOND,
        TIMESTAMP(cl.check_in_date,cl.check_in_clock),
        TIMESTAMP(cl.check_out_date,cl.check_out_clock)
      ),
      TIMESTAMPDIFF(
        SECOND,
        TIMESTAMP(cl.check_in_date,cl.check_in_clock),
        NOW()
      )
    ) AS time_spent,
    (cl.check_out_date IS NULL) AS is_active
  FROM check_log cl
  JOIN users u ON cl.user_id = u.id
";
if ($where) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY cl.check_in_date DESC, cl.check_in_clock DESC";

$stmt = $mysqli->prepare($sql);
if ($params) {
    $stmt->bind_param(str_repeat('s', count($params)), ...$params);
}
$stmt->execute();
$checkLog = $stmt->get_result();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Activity – Admin Dashboard</title>
  <link rel="stylesheet" href="/MMB/assets/css/style.css"/>
</head>
<body>

  <div class="container edge-to-edge panel-wrapper">

    <!-- FILTER BAR (moved above tabs) -->
    <form method="get" class="filter-bar" id="filterForm">
      <!-- preserve tab state -->
      <input type="hidden" name="week_range" id="week_range" value="<?=htmlspecialchars($wr)?>">

      <!-- Search -->
      <div class="filter-group search">
        <input 
          type="text"
          id="searchInput"
          class="search-input"
          placeholder="Search records…"
          oninput="searchTable()">
      </div>

      <!-- New Activity button in the 160px column -->
      <div class="filter-group">
        <button 
          type="button" 
          class="btn btn-primary filter-dropdown"
          onclick="openNewActivity()">
          New Activity
        </button>
      </div>

      <!-- Refresh button in the next 160px column -->
      <div class="filter-group">
        <button 
          type="button" 
          class="btn btn-secondary filter-dropdown"
          onclick="location.reload()">
          Refresh
        </button>
      </div>

      <!-- the final auto column is left empty -->
    </form>

    <!-- TABS -->
    <div class="tabs">
      <button type="button" class="tab-btn <?= $wr==='1'?'active':''?>" data-week="1">Last Week</button>
      <button type="button" class="tab-btn <?= $wr==='0'?'active':''?>" data-week="0">This Week</button>
      <button type="button" class="tab-btn <?= $wr==='2'?'active':''?>" data-week="2">Two Weeks Ago</button>
      <button type="button" class="tab-btn <?= $wr==='4'?'active':''?>" data-week="4">Past Two Weeks</button>


    </div>

    <div class="table-panel">
      <table id="activityTable" class="styled-table" data-sort-dir="desc">
        <thead>
          <tr>
            <th onclick="sortTable(0)">User<div class="th-resizer"></div></th>
            <th onclick="sortTable(1)">Location<div class="th-resizer"></div></th>
            <th onclick="sortTable(2)">In Date<div class="th-resizer"></div></th>
            <th onclick="sortTable(3)">In Time<div class="th-resizer"></div></th>
            <th onclick="sortTable(4)">Out Date<div class="th-resizer"></div></th>
            <th onclick="sortTable(5)">Out Time<div class="th-resizer"></div></th>
            <th onclick="sortTable(6)">Time Spent<div class="th-resizer"></div></th>
          </tr>
        </thead>
        <tbody>
          <?php while($r = $checkLog->fetch_assoc()): ?>
            <tr class="<?= $r['is_active'] ? 'highlight-row' : '' ?>">
              <td><?=htmlspecialchars($r['full_name'])?></td>
              <td><?=htmlspecialchars($r['project'])?></td>
              <td><?=htmlspecialchars($r['in_date'])?></td>
              <td><?=htmlspecialchars($r['in_time'])?></td>
              <td><?=htmlspecialchars($r['out_date'] ?: '—')?></td>
              <td><?=htmlspecialchars($r['out_time'] ?: '—')?></td>
              <td><?=sprintf('%02dh %02dm', floor($r['time_spent']/3600), floor(($r['time_spent']%3600)/60))?></td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php require_once(__DIR__ . '/../../includes/footer.php'); ?>

  <script src="/MMB/assets/js/common.js"></script>
  <script>
    // sortTable and searchTable...
    function sortTable(col){ /*…*/ }
    function searchTable(){ /*…*/ }

    // tabs behavior
    document.addEventListener('DOMContentLoaded', ()=>{
      document.querySelectorAll('.tabs .tab-btn')
        .forEach(btn=>{
          btn.addEventListener('click', ()=>{
            document.querySelectorAll('.tabs .tab-btn')
              .forEach(b=>b.classList.remove('active'));
            btn.classList.add('active');
            document.getElementById('week_range').value = btn.dataset.week;
            document.getElementById('filterForm').submit();
          });
        });
    });

    // placeholder for New Activity
    function openNewActivity(){
      // TODO: implement your new-activity logic
      alert('Open New Activity modal');
    }
  </script>
</body>
</html>
