<?php
// public/user_schedule_report.php

if (empty($_SESSION['user_id'])) {
  header('Location: /login.php');
  exit;
}
$currentUser = (int)$_SESSION['user_id'];

ini_set('display_errors', 1);
error_reporting(E_ALL);

// ── Week boundaries ──
$weekFilter = $_GET['filter'] ?? 'current';
$offsetMap  = [
  'current' => 0,
  'next'    => 1,
];
$weekOffset   = $offsetMap[$weekFilter] ?? 0;
$weekStart    = date('Y-m-d', strtotime("monday this week {$weekOffset} week"));
$weekStartSQL = pg_escape_literal($conn, $weekStart);

// choose label
$subheading = $weekFilter === 'next' ? 'Next Week' : 'This Week';

// ── Fetch user info ──
$userRes = pg_query_params($conn,
  "SELECT u.full_name, u.role, t.name AS trade
     FROM users u
LEFT JOIN trades t ON t.id = u.trade_id
    WHERE u.id = $1",
  [$currentUser]
);
$user = pg_fetch_assoc($userRes) ?: ['full_name'=>'','role'=>'','trade'=>''];

// ── Fetch schedule ──
$res = pg_query($conn,
  "SELECT
     COALESCE(mon_user_id,'[]') AS mon,
     COALESCE(tue_user_id,'[]') AS tue,
     COALESCE(wed_user_id,'[]') AS wed,
     COALESCE(thu_user_id,'[]') AS thu,
     COALESCE(fri_user_id,'[]') AS fri,
     COALESCE(sat_user_id,'[]') AS sat,
     COALESCE(sun_user_id,'[]') AS sun,
     pa.address_line1 AS address
   FROM project_schedule sa
   JOIN project_addresses pa ON pa.id = sa.project_id
  WHERE sa.week_start = {$weekStartSQL}
    AND (
         sa.mon_user_id  @> to_jsonb($currentUser::int)
      OR sa.tue_user_id  @> to_jsonb($currentUser::int)
      OR sa.wed_user_id  @> to_jsonb($currentUser::int)
      OR sa.thu_user_id  @> to_jsonb($currentUser::int)
      OR sa.fri_user_id  @> to_jsonb($currentUser::int)
      OR sa.sat_user_id  @> to_jsonb($currentUser::int)
      OR sa.sun_user_id  @> to_jsonb($currentUser::int)
    )
  ORDER BY pa.address_line1"
);
$assigns = pg_fetch_all($res) ?: [];

// ── Map days to assignments ──
$dayLabels = [
  'mon'=>'Monday','tue'=>'Tuesday','wed'=>'Wednesday',
  'thu'=>'Thursday','fri'=>'Friday','sat'=>'Saturday','sun'=>'Sunday'
];
$userSchedule = array_fill_keys(array_keys($dayLabels), []);
foreach ($assigns as $row) {
  foreach (array_keys($dayLabels) as $d) {
    $uids = json_decode($row[$d], true) ?: [];
    if (in_array($currentUser, $uids, true)) {
      $userSchedule[$d][] = $row['address'];
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Your Schedule — <?= htmlspecialchars($subheading) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/css/styles.css">
  <style>
    /* center the Schedule heading */
    h2.schedule-title {
      text-align: center;
      margin: 1rem 0;
    }

    /* tabs */
    .tabs {
      display: flex;
      justify-content: center;
      gap: 1rem;
      margin-bottom: 1rem;
    }
    .tab-btn {
      text-decoration: none;
      padding: 0.5rem 1rem;
      font-family: system-ui, sans-serif;
      font-weight: 600;
      color: #444;
      border-radius: 4px;
      border-bottom: 2px solid transparent;
    }
    .tab-btn.active {
      border-color: orange;
      color: #000;
    }

    /* week-range */
    .week-range {
      width: 100%;
      box-sizing: border-box;
      padding: 0.75rem 1rem;
      margin: 0.5rem 0;
      font-size: 1rem;
      line-height: 1.4;
      text-align: center;
      font-weight: bold;
    }

    /* assignment tags */
    .assignment-tag {
      width: 100%;
      box-sizing: border-box;
      padding: 0.75rem 1rem;
      margin: 0.5rem 0;
      font-size: 1rem;
      line-height: 1.4;
      text-align: right;
    }
    .assignment-none {
      font-size: 1.5rem;
      color: #999;
      text-align: center;
      margin: 0.5rem 0;
    }

    .schedule-day {
      margin: 1.5rem 0;
    }
    .day-label {
      font-weight: bold;
      margin-bottom: 0.5rem;
      font-size: 1.05rem;
    }


    
  </style>
</head>
<body>

  <h2 class="schedule-title">Schedule</h2>

  <!-- week tabs -->
  <div class="tabs">
    <a href="?filter=current" class="tab-btn <?= $weekFilter === 'current' ? 'active' : '' ?>">This Week</a>
    <a href="?filter=next"    class="tab-btn <?= $weekFilter === 'next'    ? 'active' : '' ?>">Next Week</a>
  </div>

  <!-- week range -->
  <div class="week-range">
    <?= date('m/d/y', strtotime($weekStart)) ?> – 
    <?= date('m/d/y', strtotime("{$weekStart} +6 days")) ?>
  </div>

<?php 
  // before your loop, initialize a counter
  $i = 0;
  foreach ($dayLabels as $key => $label): 
    // compute mm/dd for this day
    $dateLabel = date('m/d', strtotime("$weekStart +{$i} day"));
?>
  <div class="schedule-day">
    <div class="day-label">
      <?= htmlspecialchars($label) ?> (<?= $dateLabel ?>)
    </div>
    <?php if (! empty($userSchedule[$key])): ?>
      <?php foreach ($userSchedule[$key] as $addr): ?>
        <div class="assignment-tag"><?= htmlspecialchars($addr) ?></div>
      <?php endforeach; ?>
    <?php else: ?>
      <div class="assignment-none">–</div>
    <?php endif; ?>
  </div>
<?php 
    $i++; 
  endforeach; ?>
</body>
</html>
