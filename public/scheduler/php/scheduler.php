<?php
// public/scheduler.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../../config/db_connect.php';

// ─── Fetch PM & SU for the Project modal ───
$resPM   = pg_query($conn,
    "SELECT id, full_name
       FROM users
      WHERE LOWER(role) = 'project manager'
      ORDER BY full_name"
);
$pmUsers = $resPM  ? pg_fetch_all($resPM) : [];

$resSU   = pg_query($conn,
    "SELECT id, full_name
       FROM users
      WHERE LOWER(role) = 'superintendent'
      ORDER BY full_name"
);
$suUsers = $resSU  ? pg_fetch_all($resSU) : [];

// -----------------------------------------------------------------------------
// JSON CREATE DISPATCH: handle Add New Project modal POSTS
// -----------------------------------------------------------------------------
// ─── JSON CREATE DISPATCH: handle JSON posts ───
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST'
 && stripos($contentType, 'application/json') === 0
) {
  header('Content-Type: application/json; charset=utf-8');
  ini_set('display_errors', 0);
  error_reporting(0);

  $in = json_decode(file_get_contents('php://input'), true) ?: [];

  // 1) Determine $projectId
  if (!empty($in['project_id'])) {
    $projectId = (int)$in['project_id'];
  }
  elseif (!empty($in['project_name']) && !empty($in['address_line1'])) {
    $name    = pg_escape_literal($conn, $in['project_name']);
    $address = pg_escape_literal($conn, $in['address_line1']);
    $status  = pg_escape_literal($conn, $in['status'] ?? '');
    $pm      = isset($in['project_manager_id']) && $in['project_manager_id']!=='' 
               ? intval($in['project_manager_id']) : 'NULL';
    $su      = isset($in['superintendent_id']) && $in['superintendent_id']!==''
               ? intval($in['superintendent_id']) : 'NULL';

    $res = pg_query($conn, "
      INSERT INTO project_addresses
        (project_name,address_line1,status,project_manager_id,superintendent_id)
      VALUES
        ($name,$address,$status,$pm,$su)
      RETURNING id
    ");
    $row = pg_fetch_assoc($res) ?: [];
    $projectId = (int)($row['id'] ?? 0);
  }
  else {
    echo json_encode(['success'=>false,'error'=>'No project selected or provided.']);
    exit;
  }

  // 2) Now upsert the schedule
  $users    = array_map('intval', $in['users']    ?? []);
  $days     =            $in['weekdays']         ?? [];
  $daysOfWeek = ['mon','tue','wed','thu','fri','sat','sun'];

  // build empty arrays
  $sched = array_fill_keys($daysOfWeek, []);

  // expand “all_week”
  foreach ($days as $dk) {
    if ($dk === 'all_week') {
      foreach (['mon','tue','wed','thu','fri'] as $d) {
        $sched[$d] = array_merge($sched[$d], $users);
      }
    } else {
      // exact match on day string
      $key = strtolower($dk);
      if (isset($sched[$key])) {
        $sched[$key] = array_merge($sched[$key], $users);
      }
    }
  }

  // prepare columns
  $fields = $values = $updates = [];
  foreach ($daysOfWeek as $d) {
    $json = pg_escape_literal($conn, json_encode(array_values(array_unique($sched[$d]))));
    $fields[]  = "{$d}_user_id";
    $values[]  = $json;
    $updates[] = "{$d}_user_id = EXCLUDED.{$d}_user_id";
  }

  // also all_week_user_id = union mon–fri
  $all = [];
  foreach (['mon','tue','wed','thu','fri'] as $d) {
    $all = array_merge($all, $sched[$d]);
  }
  $allJson   = pg_escape_literal($conn, json_encode(array_values(array_unique($all))));
  $fields[]  = 'all_week_user_id';
  $values[]  = $allJson;
  $updates[] = 'all_week_user_id = EXCLUDED.all_week_user_id';

  // do the upsert
  $sql = sprintf("
    INSERT INTO project_schedule
      (project_id, week_start, %s, created_at, updated_at)
    VALUES
      (%d, %s, %s, NOW(), NOW())
    ON CONFLICT (project_id, week_start) DO UPDATE
      SET %s, updated_at = NOW()
  ",
    implode(',', $fields),
    $projectId,
    $weekStartSQL,
    implode(',', $values),
    implode(',', $updates)
  );
  pg_query($conn, $sql);

  // 3) respond success
  echo json_encode(['success'=>true,'project_id'=>$projectId]);
  exit;
}


// now $projectId holds the right ID—go on to upsert your schedule as before…


// -----------------------------------------------------------------------------
// 1) WEEK FILTER + BOUNDARIES
// -----------------------------------------------------------------------------
$weekFilter   = $_GET['filter'] ?? 'current';
$offsetMap    = ['current'=>0,'next'=>1,'ahead2'=>2];
$weekOffset   = $offsetMap[$weekFilter] ?? 0;
$weekStart    = date('Y-m-d', strtotime("monday this week +{$weekOffset} week"));
$weekEnd      = date('Y-m-d', strtotime("sunday this week +{$weekOffset} week"));
$weekStartSQL = pg_escape_literal($conn, $weekStart);

// -----------------------------------------------------------------------------
// 1a) Manager color map
// -----------------------------------------------------------------------------
$managerColorMap = [];
$res = pg_query($conn, "SELECT id, color FROM users WHERE LOWER(role) = 'project manager'");
while ($mgr = pg_fetch_assoc($res)) {
  $managerColorMap[(int)$mgr['id']] = $mgr['color'];
}

// -----------------------------------------------------------------------------
// 2) AJAX DELETE
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['delete'], $_GET['id'])) {
  header('Content-Type: application/json');
  $projId = (int)$_GET['id'];
  pg_query($conn,
    "DELETE FROM project_schedule
       WHERE project_id = {$projId}
         AND week_start  = {$weekStartSQL}"
  );
  echo json_encode(['status' => 'ok']);
  exit;
}

// -----------------------------------------------------------------------------
// 3) AJAX SYNC (auto-save on every change)
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['sync'])) {
  header('Content-Type: application/json');
  $data = json_decode(file_get_contents('php://input'), true);
  if (!is_array($data)) {
    echo json_encode(['status'=>'error','msg'=>'Invalid payload']);
    exit;
  }
  foreach ($data as $row) {
    $days   = is_array($row['days'] ?? []) ? $row['days'] : [];
    $projId = (int)$row['id'];
    $fields = $values = $updates = [];
    foreach (['mon','tue','wed','thu','fri','sat','sun'] as $d) {
      $j = pg_escape_literal($conn, json_encode($days[$d] ?? []));
      $fields[]  = "{$d}_user_id";
      $values[]  = $j;
      $updates[] = "{$d}_user_id = EXCLUDED.{$d}_user_id";
    }
    $all = [];
    foreach (['mon','tue','wed','thu','fri'] as $d) {
      $all = array_merge($all, $days[$d] ?? []);
    }
    $allJson   = pg_escape_literal($conn, json_encode(array_values(array_unique($all))));
    $fields[]  = 'all_week_user_id';
    $values[]  = $allJson;
    $updates[] = 'all_week_user_id = EXCLUDED.all_week_user_id';

    $sql = sprintf(
      'INSERT INTO project_schedule
         (project_id, week_start, %s, created_at, updated_at)
       VALUES (%d, %s, %s, NOW(), NOW())
       ON CONFLICT (project_id, week_start) DO UPDATE
         SET %s, updated_at = NOW()',
      implode(',', $fields),
      $projId,
      $weekStartSQL,
      implode(',', $values),
      implode(',', $updates)
    );
    pg_query($conn, $sql);
  }
  echo json_encode(['status' => 'ok']);
  exit;
}

// -----------------------------------------------------------------------------
// 4) ADD-NEW MODAL SUBMIT (merge, don’t overwrite)
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_schedule'])) {
  // 1) Determine projectId: existing vs new
  if (!empty($_POST['project_id'])) {
    // use an existing project
    $projectId = (int) $_POST['project_id'];
  }
  elseif (!empty($_POST['project_name']) && !empty($_POST['address_line1'])) {
    // insert a brand-new project
    $nm  = pg_escape_literal($conn, trim($_POST['project_name']));
    $ad  = pg_escape_literal($conn, trim($_POST['address_line1']));
    $st  = pg_escape_literal($conn, $_POST['status'] ?? '');
    $pm  = !empty($_POST['project_manager_id'])
          ? intval($_POST['project_manager_id']) : 'NULL';
    $su  = !empty($_POST['superintendent_id'])
          ? intval($_POST['superintendent_id']) : 'NULL';

    $res = pg_query($conn, "
      INSERT INTO project_addresses
        (project_name, address_line1, status, project_manager_id, superintendent_id)
      VALUES
        ($nm, $ad, $st, $pm, $su)
      RETURNING id
    ");
    if (! $res || !($row = pg_fetch_assoc($res))) {
      echo "<script>alert('Error creating project: ".pg_last_error($conn)."');history.back();</script>";
      exit;
    }
    $projectId = (int) $row['id'];
  }
  else {
    echo "<script>alert('Please either select an existing project or fill in the New Project fields.');history.back();</script>";
    exit;
  }

  // 2) Now build the schedule exactly as before
  $newUsers = array_map('intval', $_POST['user_ids'] ?? []);
  $selDays  = $_POST['days'] ?? [];
  $applyMf  = isset($_POST['apply-mf']);

  // load any existing assignments for this project/week
  $daysOfWeek = ['mon','tue','wed','thu','fri','sat','sun'];
  $existing = array_fill_keys($daysOfWeek, []);
  $res = pg_query($conn,
    "SELECT ".implode(',', array_map(fn($d) => "{$d}_user_id", $daysOfWeek))."
       FROM project_schedule
      WHERE project_id = {$projectId}
        AND week_start  = ".pg_escape_literal($conn, $weekStart)."
    "
  );
  if ($res && pg_num_rows($res)) {
    $row = pg_fetch_assoc($res);
    foreach ($daysOfWeek as $d) {
      $decoded = json_decode($row["{$d}_user_id"], true);
      if (is_array($decoded)) {
        $existing[$d] = $decoded;
      }
    }
  }

  // merge in the newly selected users/days
  foreach ($newUsers as $uid) {
    foreach ($daysOfWeek as $d) {
      $shouldApply = 
           ($applyMf && in_array($d, ['mon','tue','wed','thu','fri'], true))
        || in_array($d, $selDays, true);

      if ($shouldApply && !in_array($uid, $existing[$d], true)) {
        $existing[$d][] = $uid;
      }
    }
  }

  // prepare columns for INSERT … ON CONFLICT
  $cols = [];
  foreach ($daysOfWeek as $d) {
    $cols[$d] = pg_escape_literal($conn, json_encode(array_values($existing[$d])));
  }
  // build all_week_user_id from mon–fri union
  $all = [];
  foreach (['mon','tue','wed','thu','fri'] as $d) {
    $all = array_merge($all, $existing[$d]);
  }
  $allJson = pg_escape_literal($conn, json_encode(array_values(array_unique($all))));

  $sql = sprintf(
    "INSERT INTO project_schedule
       (project_id, week_start,
        mon_user_id,tue_user_id,wed_user_id,
        thu_user_id,fri_user_id,
        sat_user_id,sun_user_id,
        all_week_user_id,
        created_at,updated_at)
     VALUES
       (%d, %s, %s,%s,%s,%s,%s,%s,%s,%s,NOW(),NOW())
     ON CONFLICT (project_id, week_start) DO UPDATE SET
       mon_user_id      = EXCLUDED.mon_user_id,
       tue_user_id      = EXCLUDED.tue_user_id,
       wed_user_id      = EXCLUDED.wed_user_id,
       thu_user_id      = EXCLUDED.thu_user_id,
       fri_user_id      = EXCLUDED.fri_user_id,
       sat_user_id      = EXCLUDED.sat_user_id,
       sun_user_id      = EXCLUDED.sun_user_id,
       all_week_user_id = EXCLUDED.all_week_user_id,
       updated_at       = NOW()",
    $projectId,
    pg_escape_literal($conn, $weekStart),
    $cols['mon'],$cols['tue'],$cols['wed'],
    $cols['thu'],$cols['fri'],$cols['sat'],
    $cols['sun'],$allJson
  );
  pg_query($conn, $sql);

  // finally, reload so your table shows the new project & schedule
  header('Location: '.$_SERVER['REQUEST_URI']);
  exit;
}

// -----------------------------------------------------------------------------
// 5) DATA FETCH + PAGE HEADER
// -----------------------------------------------------------------------------


$addresses = pg_fetch_all(pg_query($conn,
  'SELECT id,address_line1 FROM project_addresses ORDER BY address_line1'
)) ?: [];

$users = pg_fetch_all(pg_query($conn,
  "SELECT u.id,u.full_name,u.role,t.name AS trade,t.color AS trade_color
     FROM users u
     LEFT JOIN trades t ON t.id=u.trade_id
    ORDER BY u.full_name"
)) ?: [];

$userMap = $colorMap = $roleMap = $tradeMap = [];
foreach ($users as $u) {
  $userMap[$u['id']]  = $u['full_name'];
  $colorMap[$u['id']] = $u['trade_color'] ?: '#ccc';
  $roleMap[$u['id']]  = $u['role'];
  $tradeMap[$u['id']] = $u['trade'];
}

$sqlAssign = "
  SELECT sa.project_id        AS id,
         pa.address_line1     AS address,
         pa.project_manager_id,
         COALESCE(sa.mon_user_id,'[]') AS mon_user_id,
         COALESCE(sa.tue_user_id,'[]') AS tue_user_id,
         COALESCE(sa.wed_user_id,'[]') AS wed_user_id,
         COALESCE(sa.thu_user_id,'[]') AS thu_user_id,
         COALESCE(sa.fri_user_id,'[]') AS fri_user_id,
         COALESCE(sa.sat_user_id,'[]') AS sat_user_id,
         COALESCE(sa.sun_user_id,'[]') AS sun_user_id
    FROM project_schedule sa
    JOIN project_addresses pa
      ON pa.id = sa.project_id
     AND sa.week_start = {$weekStartSQL}
 ORDER BY pa.address_line1
";
$assigns     = pg_fetch_all(pg_query($conn, $sqlAssign)) ?: [];
$dayLabels   = [
  'mon'=>'Monday','tue'=>'Tuesday','wed'=>'Wednesday',
  'thu'=>'Thursday','fri'=>'Friday','sat'=>'Saturday','sun'=>'Sunday'
];
$weekdayKeys = array_keys($dayLabels);


// grab an incoming ?user_id=… parameter
$filterUser = isset($_GET['user_id']) 
    ? (int) $_GET['user_id'] 
    : null;

// now figure out which view to show
$activeView = $_GET['view'] ?? 'project';
if ($filterUser) {
    $activeView = 'user';
}


$activeView  = $_GET['view'] ?? 'project';

$takenIds = [];
foreach ($assigns as $r) {
  foreach ($weekdayKeys as $d) {
    foreach (json_decode($r["{$d}_user_id"], true) as $uid) {
      $takenIds[$uid] = true;
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Scheduler</title>
  <link rel="stylesheet" href="../css/styles.css">
  <style>
  /* full-screen, centered overlay */
  .modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.6);
    display: none;
    justify-content: center;
    align-items: center;
    z-index: 10000;
  }
  .modal-overlay.show {
    display: flex;
  }

  .modal-box {
    background: #fff;
    border-radius: 8px;
    width: 50%;
    max-width: 95%;
    box-shadow: 0 8px 24px rgba(0,0,0,0.2);
    padding: 1.5rem;      /* ← add this */
  }
  /* header */
  .modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid #eee;
  }
  .modal-header h2 {
    margin: 0;
  }
  .modal-actions {
    display: flex;
    gap: 0.5rem;
  }
  /* body */
  /* body for Schedule modal only */
#addScheduleModal .modal-body {
    display: flex;
    padding: 1.5rem;
    gap: 2rem;
    /* allow weekend panel to extend and be clickable */
    overflow: visible;
}

/* Project modal remains block layout */
#addProjectModal .modal-body {
    display: block;
    padding: 1.5rem;
}

/* common body children */

  /* make each field row in Project modal flex to fill width */
  #addProjectModal .modal-body > .form-row {
    flex: 1;
  }
  .modal-body .form-left {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 1rem;
  }
  .modal-body .form-left .form-row {
    display: flex;
    flex-direction: column;
  }
  .modal-body .form-right {
    width: 200px;
    padding: 1rem;
    background: #f0f7ff;
    border-radius: 4px;
    font-size: 0.95rem;
  }
  .modal-body .form-right .weekend {
    margin-top: 1rem;
    padding: 0.5rem;
    background: #fff7e6;
    border-radius: 4px;
  }
  /* footer (hidden, since buttons are in header) */
  .modal-box form {
    display: block;
  }
#addScheduleModal .modal-body .form-left {
  display: flex;
  flex-wrap: wrap;
  gap: 1rem; /* horizontal + vertical gutter */
}

#addScheduleModal .modal-body .form-left .form-row {
  flex: 0.5 1 45%;        /* try two per row, at least 45% each */
  min-width: 300px;     /* but never squish below 200px */
  box-sizing: border-box;
}

#addScheduleModal .modal-body .form-left .form-row input,
#addScheduleModal .modal-body .form-left .form-row select {
  width: 100%;

}

#addScheduleModal .modal-box {
  width: 700px;
  max-width: 95%;
  transition: width 0.2s ease;
}

/* when .wide is toggled on the box, make it larger */
#addScheduleModal .modal-box.wide {
  width: 900px;   /* or whatever wider size you prefer */
}
/* make only that row left-align */
#addScheduleModal .modal-body .form-left .toggle-row {
  justify-content: flex-start;
  text-align: left;
}

/* ensure flex children stick to the left edge */
#addScheduleModal .modal-body .form-left .toggle-row label {
  margin: 0;
}
/* make only that row span 100% and align its contents to the left */
.form-row.toggle-row {
  display: flex;
  justify-content: flex-start;
  align-items: center;
  width: 100%;
  margin-bottom: 1rem; /* match your other form‐row spacing */
}

/* tighten up the label so it doesn’t center itself */
.form-row.toggle-row label {
  margin: 0;
  text-align: left;
}
/* make the toggle only as big as its checkbox + label */
.toggle-row {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;      /* space between box + text */
  margin-bottom: 1rem; /* match the gap to the next row */
}

/* prevent any checkbox from taking 100% width */
input[type="checkbox"] {
  width: auto !important;
  height: auto !important;
  margin: 0;
  padding: 0;
  appearance: auto;
}

/* if you want to target only your toggle: */
#psNewProjectToggle {
  width: auto !important;
  margin-right: .5rem;
}


/* prevent any browser-default range track from sneaking back */
input[type="range"] {
  display: none;
}

/* ensure only a small checkbox shows */
input[type="checkbox"] {
  width: auto;
  height: auto;
  margin: 0;
  padding: 0;
  appearance: auto;
}



</style>
</head>
<body>
  <h1 style="text-align:center;margin:1.5rem;font-size:1.75rem;font-weight:bold">
    SCHEDULER
  </h1>

  <!-- week & view tabs -->
  <div class="tabs week-tabs" style="margin-bottom:1rem">
    <?php foreach (['current'=>'This Week','next'=>'Next Week','ahead2'=>'Two Weeks Ahead'] as $k=>$l): ?>
      <a href="?view=project&filter=<?=$k?>" class="tab-btn<?= $weekFilter===$k?' active':'' ?>">
        <?=$l?>
      </a>
    <?php endforeach; ?>
  </div>
  <div class="tabs" style="display:flex;justify-content:center;margin-bottom:1rem">
    <a href="?view=project&filter=<?=$weekFilter?>" class="tab-btn<?= $activeView==='project'?' active':'' ?>">
      Project Schedule
    </a>
    <a href="?view=user&filter=<?=$weekFilter?>" class="tab-btn<?= $activeView==='user'?' active':'' ?>">
      User Schedule
    </a>
  </div>

  

  <!-- week range -->
  <div class="week-range" style="text-align:center;margin-bottom:1rem;font-weight:bold">
    <?=date('M j, Y',strtotime($weekStart))?> – <?=date('M j, Y',strtotime($weekEnd))?>
  </div>

  <div class="page-content">
    <div class="toolbar" style="display:flex; align-items:center; margin-bottom:1rem;">
  <?php if ($activeView==='project'): ?>
    <input type="text" id="search-input" placeholder="Search…" style="flex:1;" />
    <button id="add-new" class="btn-primary" style="margin-left:1rem">ADD NEW SCHEDULE</button>


  <?php endif; ?>
</div>

    <?php if ($activeView==='project'): ?>
    <table id="schedule-table" class="styled-table">
      <thead>
        <tr>
          <th>Address</th>
          <th>Project Manager</th>
          <?php foreach ($dayLabels as $lbl): ?><th><?=$lbl?></th><?php endforeach; ?>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($assigns as $r): ?>
        <tr data-id="<?= (int)$r['id'] ?>">
          <td><?= htmlspecialchars($r['address'] ?? '', ENT_QUOTES) ?></td>
          <td>
            <?php
              $mid   = (int)$r['project_manager_id'];
              $mcol  = htmlspecialchars($managerColorMap[$mid] ?? '#ccc', ENT_QUOTES);
              $mname = htmlspecialchars($userMap[$mid] ?? '', ENT_QUOTES);
            ?>
            <span class="tag" style="background-color:<?=$mcol?>"><?=$mname?></span>
          </td>
          <?php foreach ($weekdayKeys as $d): ?>
          <td class="tag-cell" data-day="<?=$d?>">
            <select class="user-select">
              <option value="">-- Select --</option>
              <?php foreach ($users as $u): ?>
                <option value="<?=$u['id']?>"><?=htmlspecialchars($u['full_name'],ENT_QUOTES)?></option>
              <?php endforeach; ?>
            </select>
            <div class="tag-container">
              <?php foreach (json_decode($r["{$d}_user_id"],true)?:[] as $uid): ?>
                <span class="tag" data-user-id="<?=$uid?>" style="background:<?=htmlspecialchars($colorMap[$uid]??'#ccc',ENT_QUOTES)?>">
                  <?=htmlspecialchars($userMap[$uid]??'',ENT_QUOTES)?>
                  <span class="tag-remove">×</span>
                </span>
              <?php endforeach; ?>
            </div>
          </td>
          <?php endforeach; ?>
<td>
<button type="button" class="btn-clear">Clear</button>
<button class="btn-delete" title="Delete">DELETE</button>
</td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?>
    <table class="styled-table">
      <thead>
        <tr>
          <th>User</th><th>Role</th><th>Trade</th>
          <?php foreach ($weekdayKeys as $k): ?><th><?=$dayLabels[$k]?></th><?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php
          $ua = [];
          foreach ($assigns as $r) {
            $mgr = (int)$r['project_manager_id'];
            foreach ($weekdayKeys as $d) {
              foreach (json_decode($r["{$d}_user_id"],true)?:[] as $uid) {
                $ua[$uid][$d][] = ['addr'=>$r['address'],'mgr'=>$mgr];
              }
            }
          }
          foreach ($ua as $uid => $days) {
               if ($filterUser && $uid !== $filterUser) continue;
            echo '<tr>';
            echo '<td>' . htmlspecialchars($userMap[$uid]  ?? '', ENT_QUOTES) . '</td>';
            echo '<td>' . htmlspecialchars($roleMap[$uid]  ?? '', ENT_QUOTES) . '</td>';
            echo '<td>' . htmlspecialchars($tradeMap[$uid] ?? '', ENT_QUOTES) . '</td>';
            foreach ($weekdayKeys as $d) {
              echo '<td><div class="tag-container">';
              foreach ($days[$d] ?? [] as $e) {
                $bg   = htmlspecialchars($managerColorMap[$e['mgr']] ?? '#ccc', ENT_QUOTES);
                $addr = htmlspecialchars($e['addr']                 ?? '', ENT_QUOTES);
                echo "<span class=\"tag\" style=\"background-color:{$bg};color:#1C262B;\">{$addr}</span>";
              }
              echo '</div></td>';
            }
            echo '</tr>';
          }
        ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

  <!-- Add New Schedule Modal -->
  <!-- Add New Schedule + Project Modal -->
<div id="addScheduleModal" class="modal-overlay">
  <div class="modal-box">
    <div class="modal-header">
      <h2>Add New Schedule</h2>
      <div class="modal-actions">
        <button type="submit" form="addScheduleForm" class="btn-primary">Save</button>
        <button id="modal-cancel" class="btn-cancel">Cancel</button>
      </div>
    </div>

    <form id="addScheduleForm" method="POST" action="?view=project&filter=<?=$weekFilter?>">
      <input type="hidden" name="add_schedule" value="1">
      <input type="hidden" name="week_start"   value="<?=$weekStart?>">

      <div class="modal-body">
        <div class="form-left">
          <!-- 1) Existing or New -->
          <div class="form-row">
<!-- toggle to “Create New Project” -->
<div class="form-row">
<label class="toggle-row">
  <input type="checkbox" id="psNewProjectToggle">
  Create new project
</label>
</div>

<!-- existing‐project selector, hidden when checkbox checked -->
<div class="form-row" id="psExistingRow">
  <label for="project_id">Existing Project</label>
  <select id="project_id" name="project_id" class="form-control">
    <option value="">— Select project —</option>
    <?php foreach($addresses as $a): ?>
      <option value="<?=$a['id']?>"><?=htmlspecialchars($a['address_line1'],ENT_QUOTES)?></option>
    <?php endforeach; ?>
  </select>
</div>

                      <!-- 3) Users selector (unchanged) -->
          <div class="form-row" style="margin-top:1.5rem;">
            <label for="modal-user-select">Users</label>
            <div class="user-select-wrapper">
              <select id="modal-user-select">
                <option value="">-- Select users --</option>
                <?php foreach($users as $u): if(!isset($takenIds[$u['id']])): ?>
                  <option value="<?=$u['id']?>"><?=$u['full_name']?></option>
                <?php endif; endforeach; ?>
              </select>
              <div id="modal-tag-container" class="tag-container"></div>
              <div id="modal-hidden-inputs"></div>
            </div>
          </div>
        </div>
        
          </div>

        

          <!-- 2) NEW PROJECT FIELDS (hidden unless above is blank) -->
          <div id="newProjectFields" style="margin-top:1rem;">
            <div class="form-row">
              <label for="newProjectName">Project Name</label>
              <input id="newProjectName" name="project_name" type="text" class="form-control"/>
            </div>
            <div class="form-row" style="margin-top:1rem;">
              <label for="newProjectAddress">Address</label>
              <input id="newProjectAddress" name="address_line1" type="text" class="form-control"/>
            </div>
            <div class="form-row" style="margin-top:1rem;">
              <label for="newProjectStatus">Status</label>
              <select id="newProjectStatus" name="status" class="form-control">
                <?php foreach(['Active','Planning','On Hold','Completed'] as $opt): ?>
                  <option><?= $opt ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-row" style="margin-top:1rem;">
              <label for="newProjectManager">Project Manager</label>
              <select id="newProjectManager" name="project_manager_id" class="form-control">
                <option value="">— Select PM —</option>
                <?php foreach($pmUsers as $u): ?>
                  <option value="<?=$u['id']?>"><?=htmlspecialchars($u['full_name'],ENT_QUOTES)?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-row" style="margin-top:1rem;">
              <label for="newSiteSuper">Site Superintendent</label>
              <select id="newSiteSuper" name="superintendent_id" class="form-control">
                <option value="">— Select Superintendent —</option>
                <?php foreach($suUsers as $u): ?>
                  <option value="<?=$u['id']?>"><?=htmlspecialchars($u['full_name'],ENT_QUOTES)?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>



        <!-- 4) Weekday checkboxes (unchanged) -->
        <div class="form-right">
          <strong>Weekdays</strong><br>
          <label><input type="checkbox" id="modal-apply-mf" name="apply-mf"> Mon–Fri</label><br>
          <?php foreach(['mon','tue','wed','thu','fri'] as $d): ?>
            <label>
              <input class="day-checkbox" type="checkbox" name="days[]" value="<?=$d?>">
              <?=$dayLabels[$d]?>
            </label><br>
          <?php endforeach; ?>
          <div class="weekend">
            <strong>Weekend</strong><br>
            <?php foreach(['sat','sun'] as $d): ?>
              <label>
                <input type="checkbox" name="days[]" value="<?=$d?>">
                <?=$dayLabels[$d]?>
              </label><br>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </form>
  </div>
</div>


 


  <!-- Confirmation Modal -->
  <div id="confirmModal" class="modal-overlay">
    <div class="modal-box">
      <p id="confirmMessage" style="margin-bottom:1rem;"></p>
      <div class="form-actions" style="justify-content:flex-end;">
        <button id="confirmNo" class="btn-cancel" type="button" style="margin-right:.5rem">No</button>
        <button id="confirmYes" class="btn-primary" type="button">Yes</button>
      </div>
    </div>
  </div>
  

 <script>
  window.SCHEDULER = {
    userMap:  <?= json_encode($userMap,  JSON_UNESCAPED_SLASHES) ?>,
    colorMap: <?= json_encode($colorMap, JSON_UNESCAPED_SLASHES) ?>,
    weekdays: <?= json_encode($weekdayKeys)                   ?>
  };
</script>

<script>
'use strict';




window.addEventListener('DOMContentLoaded', () => {
  const { userMap, colorMap, weekdays } = window.SCHEDULER;
  const $  = s => document.querySelector(s);
  const $$ = s => Array.from(document.querySelectorAll(s));


  // ── TOGGLE “New Project” FIELDS ──────────────────────────────────────────────
  const modalBox       = document.querySelector('#addScheduleModal .modal-box');
  const toggleNewCB    = document.getElementById('psNewProjectToggle');
  const existingRow    = document.getElementById('psExistingRow');
  const newFields      = document.getElementById('newProjectFields');
  const formLeft    = document.querySelector('#addScheduleModal .form-left');

  function adjustForm() {
    


    if (toggleNewCB.checked) {
      modalBox.classList.add('wide');
      formLeft.classList.add('no-gap');
      existingRow.style.display = 'none';
      newFields.style.display   = 'block';
    } else {
      modalBox.classList.remove('wide');
      formLeft.classList.remove('no-gap');
      existingRow.style.display = 'flex';
      newFields.style.display   = 'none';
    }
  }

  toggleNewCB.addEventListener('change', adjustForm);
  adjustForm();
  // ── CONFIRM DIALOG ─────────────────────────────────────────────────────────
  const confirmModal   = $('#confirmModal');
  const confirmMessage = $('#confirmMessage');
  let _confirmResolve;
  function showConfirm(msg) {
    confirmMessage.textContent = msg;
    confirmModal.classList.add('show');
    document.body.classList.add('modal-open');
    return new Promise(res => _confirmResolve = res);
  }
  function hideConfirm() {
    confirmModal.classList.remove('show');
    document.body.classList.remove('modal-open');
  }
  $('#confirmYes').onclick = () => { hideConfirm(); _confirmResolve(true); };
  $('#confirmNo').onclick  = () => { hideConfirm(); _confirmResolve(false); };

  // ── AUTO-SAVE ──────────────────────────────────────────────────────────────
  async function syncSchedule() {
    const rows = $$('tr[data-id]');
    const payload = rows.map(r => {
      const daysObj = {};
      weekdays.forEach(d => {
        daysObj[d] = Array.from(
          r.querySelectorAll(`[data-day="${d}"] .tag`)
        ).map(t => +t.dataset.userId);
      });
      return { id:+r.dataset.id, days: daysObj };
    });
    const url = new URL(window.location.href);
    url.searchParams.set('sync','1');
    await fetch(url.toString(), {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify(payload)
    });
  }

  // ── DRAG & DROP ────────────────────────────────────────────────────────────
  function attachDrag(tag) {
    tag.draggable = true;
    tag.ondragstart = e => {
      e.dataTransfer.setData('text/plain', tag.dataset.userId);
      tag.classList.add('dragging');
    };
    tag.ondragend = () => {
      tag.classList.remove('dragging');
      syncSchedule();
    };
  }
  $$('.tag-cell .tag').forEach(attachDrag);
  $$('.tag-cell').forEach(cell => {
    cell.ondragover = e => e.preventDefault();
    cell.ondrop = e => {
      e.preventDefault();
      const drag = document.querySelector('.tag.dragging');
      if (!drag) return;
      const cont = cell.querySelector('.tag-container');
      if (cont.querySelector(`[data-user-id="${drag.dataset.userId}"]`)) return;
      cont.appendChild(drag);
      syncSchedule();
    };
  });

  // ── CREATE TAG IN TABLE ────────────────────────────────────────────────────
  function createTag(uid, container) {
    if (container.querySelector(`[data-user-id="${uid}"]`)) return;
    const sel = container.closest('td').querySelector('select.user-select');
    sel.querySelector(`option[value="${uid}"]`)?.remove();
    const tag = document.createElement('span');
    tag.className      = 'tag';
    tag.dataset.userId = uid;
    tag.textContent    = userMap[uid];
    tag.style.backgroundColor = colorMap[uid];
    const rem = document.createElement('span');
    rem.className   = 'tag-remove';
    rem.textContent = '×';
    tag.append(rem);
    container.append(tag);
    attachDrag(tag);
    syncSchedule();
  }
  $$('.user-select').forEach(sel => {
    sel.onchange = e => {
      const uid = +e.target.value;
      if (!uid) return;
      createTag(uid, e.target.closest('td').querySelector('.tag-container'));
    };
  });

  // ── GLOBAL CLICK: clear, delete, remove-tag ─────────────────────────────────
// Make sure this runs *after* your showConfirm() is defined
  document.addEventListener('click', async e => {
    if (!e.target.matches('.btn-delete')) return;
    e.preventDefault();

    // 1) Show your custom confirm modal
    const ok = await showConfirm('Delete this entire schedule row?');
    if (!ok) return;

    

    // 2) Build a URL that preserves any existing filter/view params
    const url = new URL(window.location.href);
    url.searchParams.set('delete', '1');
    url.searchParams.set('id', e.target.closest('tr').dataset.id);

    // 3) Fire the delete POST, then always reload
    fetch(url.toString(), { method: 'POST' })
      .catch(err => console.error('Delete API error', err))
      .finally(() => {
        // this reloads the *original* page (without ?delete & ?id)
        window.location.reload();
      });
  });


    document.addEventListener('click', async e => {
    if (!e.target.matches('.btn-delete')) return;
    e.preventDefault();

    // 1) Show your custom confirm modal
    const ok = await showConfirm('Delete this entire schedule row?');
    if (!ok) return;

    

    // 2) Build a URL that preserves any existing filter/view params
    const url = new URL(window.location.href);
    url.searchParams.set('delete', '1');
    url.searchParams.set('id', e.target.closest('tr').dataset.id);

    // 3) Fire the delete POST, then always reload
    fetch(url.toString(), { method: 'POST' })
      .catch(err => console.error('Delete API error', err))
      .finally(() => {
        // this reloads the *original* page (without ?delete & ?id)
        window.location.reload();
      });
  });



  // ── ADD-NEW SCHEDULE MODAL ──────────────────────────────────────────────────
  function openScheduleModal() {
    $('#addScheduleModal').classList.add('show');
    document.body.classList.add('modal-open');
  }
  function closeScheduleModal() {
    $('#addScheduleModal').classList.remove('show');
    document.body.classList.remove('modal-open');
  }
  $('#add-new').onclick       = openScheduleModal;
  $('#modal-cancel').onclick  = closeScheduleModal;
  $('#addScheduleForm').onsubmit = closeScheduleModal;

  // ── SEARCH FILTER ──────────────────────────────────────────────────────────
  const searchInput = $('#search-input');
  if (searchInput) {
    searchInput.addEventListener('input', () => {
      const filter = searchInput.value.toLowerCase();
      $$('table.styled-table tbody tr').forEach(tr => {
        tr.style.display = tr.textContent.toLowerCase().includes(filter) ? '' : 'none';
      });
    });
  }

  // ── MULTI-SELECT FOR SCHEDULE MODAL ─────────────────────────────────────────
  const modalSelect    = $('#modal-user-select');
  const modalContainer = $('#modal-tag-container');
  const hiddenInputs   = $('#modal-hidden-inputs');
  if (modalSelect) {
    modalSelect.onchange = e => {
      const uid = +e.target.value; if (!uid) return;
      modalSelect.querySelector(`option[value="${uid}"]`)?.remove();
      const hi = document.createElement('input');
      hi.type = 'hidden'; hi.name = 'user_ids[]'; hi.value = uid;
      hiddenInputs.append(hi);
      const tag = document.createElement('span');
      tag.className      = 'tag';
      tag.dataset.userId = uid;
      tag.textContent    = userMap[uid];
      tag.style.backgroundColor = colorMap[uid];
      const rem = document.createElement('span');
      rem.className   = 'tag-remove';
      rem.textContent = '×';
      tag.append(rem);
      modalContainer.append(tag);
      e.target.value = '';
    };
  }
// REMOVE SINGLE TAG (X)
document.addEventListener('click', e => {
  if (!e.target.classList.contains('tag-remove')) return;

  const tag = e.target.closest('.tag');
  const uid = tag.dataset.userId;
  const td = tag.closest('td');
  const container = td.querySelector('.tag-container');
  const select = td.querySelector('select.user-select');

  if (uid && userMap[uid] && select) {
    const opt = document.createElement('option');
    opt.value = uid;
    opt.textContent = userMap[uid];
    select.appendChild(opt);
  }

  tag.remove();
  syncSchedule();
});

  // CLEAR ALL TAGS IN A SINGLE ROW
  document.addEventListener('click', async e => {
    if (!e.target.matches('.btn-clear')) return;
    e.preventDefault();

    const ok = await showConfirm('Clear all tags in this schedule row?');
    if (!ok) return;

    const row = e.target.closest('tr');
    if (!row) return;

    row.querySelectorAll('td').forEach(cell => {
      const container = cell.querySelector('.tag-container');
      const select = cell.querySelector('select.user-select');

      if (!container || !select) return;

      container.querySelectorAll('.tag').forEach(tag => {
        const uid = tag.dataset.userId;
        if (uid && userMap[uid]) {
          const opt = document.createElement('option');
          opt.value = uid;
          opt.textContent = userMap[uid];
          select.appendChild(opt);
        }
        tag.remove();
      });
    });


syncSchedule();


  if (!container || !select) return;

  container.querySelectorAll('.tag').forEach(tag => {
    const uid = tag.dataset.userId;

    if (uid && userMap[uid]) {
      const opt = document.createElement('option');
      opt.value = uid;
      opt.textContent = userMap[uid];
      select.appendChild(opt);
    }

    tag.remove();
  });

  syncSchedule();
});




  // MON–FRI toggle sets only weekdays; selecting any other day disables Mon–Fri
  const monFri = $('#modal-apply-mf');
  const dayCheckboxes = $$('.day-checkbox');
  const weekdayValues = ['mon', 'tue', 'wed', 'thu', 'fri'];

  if (monFri) {
    monFri.addEventListener('change', () => {
      const checked = monFri.checked;
      $$('input[name="days[]"]').forEach(cb => {
        if (weekdayValues.includes(cb.value)) {
          cb.checked = checked;
        }
      });
    });

    $$('input[name="days[]"]').forEach(cb => {
      cb.addEventListener('change', () => {
        // If any weekday is manually unchecked, also uncheck Mon–Fri
        if (weekdayValues.includes(cb.value) && !cb.checked) {
          monFri.checked = false;
        }

        // If all weekdays are checked manually, set Mon–Fri checked
        const allWeekdaysChecked = weekdayValues.every(val => {
          const el = $$('input[name="days[]"]').find(cb => cb.value === val);
          return el?.checked;
        });
        monFri.checked = allWeekdaysChecked;
      });
    });
  }

  // ── PROJECT-MODAL OPEN/CLOSE & SAVE ────────────────────────────────────────
  const openBtn        = $('#addProjectBtn');
  const projectModal   = $('#addProjectModal');
  const closeProject   = $('#closeAddModal');
  const cancelProject  = $('#cancelProjectBtn');
  const saveProject    = $('#saveProjectBtn');
  const projectForm    = $('#addProjectForm');
  const projectEndpoint= window.location.href;

  function hideProjectModal() {
    projectModal.classList.remove('show');
    projectModal.style.display = 'none';
  }

  openBtn && openBtn.addEventListener('click', () => {
    projectModal.style.display = 'flex';
    projectModal.classList.add('show');
  });
  closeProject && closeProject.addEventListener('click', hideProjectModal);
  cancelProject && cancelProject.addEventListener('click', hideProjectModal);

  saveProject && saveProject.addEventListener('click', e => {
    e.preventDefault();
    if (!projectForm.checkValidity()) return projectForm.reportValidity();

    hideProjectModal();
    saveProject.disabled = true;

    const payload = {
      project_name:      projectForm.project_name.value,
      address_line1:     projectForm.address_line1.value,
      status:            projectForm.status.value,
      project_manager_id: projectForm.project_manager_id.value,
      superintendent_id:  projectForm.superintendent_id.value
    };

    fetch(projectEndpoint, {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(() => location.reload())
    .catch(() => location.reload());
  });
});
</script>




</body>
</html>
