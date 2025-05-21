<?php
// public/scheduler.php (show only existing schedules per week)

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/header.php';

// --- Only these week filters ---
$weekFilter = $_GET['filter'] ?? 'current';
$offsetMap  = [
    'current' => 0,
    'next'    => 1,
    'ahead2'  => 2,
];
$weekOffset = $offsetMap[$weekFilter] ?? 0;

// --- Calculate weekStart for both filtering and sync operations ---
$weekStart    = date('Y-m-d', strtotime("monday this week +{$weekOffset} week"));
$weekEnd      = date('Y-m-d', strtotime("sunday this week +{$weekOffset} week"));
$weekStartSQL = pg_escape_literal($conn, $weekStart);

// --- Handle sync updates from frontend (save/commit changes in unlocked mode) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['sync'])) {
    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        echo json_encode(['status' => 'error', 'msg' => 'Invalid data']);
        exit;
    }
    foreach ($payload as $row) {
        $projId  = intval($row['id']);
        $fields  = [];
        $updates = [];
        foreach (['mon','tue','wed','thu','fri','sat','sun'] as $day) {
            $ids      = $row['days'][$day] ?? [];
            $json     = pg_escape_literal($conn, json_encode($ids));
            $fields[] = "{$day}_user_id";
            $updates[] = "{$day}_user_id = EXCLUDED.{$day}_user_id";
        }
        // Build all_week_user_id from Mon–Fri union
        $allWeekIds = [];
        foreach (['mon','tue','wed','thu','fri'] as $d) {
            $allWeekIds = array_merge($allWeekIds, $row['days'][$d] ?? []);
        }
        $allWeekJson = pg_escape_literal($conn, json_encode(array_unique($allWeekIds)));
        $fields[]    = "all_week_user_id";
        $updates[]   = "all_week_user_id = EXCLUDED.all_week_user_id";

        $fieldsList  = implode(',', $fields);
        $updatesList = implode(', ', $updates);

        $sql = "
            INSERT INTO project_schedule
                (project_id, week_start, $fieldsList, created_at, updated_at)
            VALUES
                ($projId, $weekStartSQL, {$values = implode(',', array_map(fn($f)=>"EXCLUDED.$f",$fields))}, NOW(), NOW())
            ON CONFLICT (project_id, week_start) DO UPDATE
            SET
                $updatesList,
                updated_at = NOW()
        ";
        // Actually build VALUES list
        $vals = [];
        foreach ($fields as $f) {
            // f is like "mon_user_id", but we need the literal from EXCLUDED? 
            // Actually above we prepared $values in-line. Let's reconstruct properly:
        }
        // Simpler: inline exactly as before:
        $sql = "
            INSERT INTO project_schedule
                (project_id, week_start, " . implode(',', $fields) . ", created_at, updated_at)
            VALUES
                ($projId, $weekStartSQL, " . implode(',', array_map(function($d) use($conn,$row) {
                    $ids  = $row['days'][$d] ?? [];
                    return pg_escape_literal($conn, json_encode($ids));
                }, ['mon','tue','wed','thu','fri','sat','sun'])) . ", $allWeekJson, NOW(), NOW())
            ON CONFLICT (project_id, week_start) DO UPDATE SET
                " . $updatesList . ",
                updated_at = NOW()
        ";
        pg_query($conn, $sql);
    }
    echo json_encode(['status' => 'ok']);
    exit;
}

// --- Add schedule handling ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_schedule'])) {
    $projectId      = intval($_POST['project_id'] ?? 0);
    $userIds        = $_POST['user_ids']  ?? [];
    $selectedDays   = $_POST['days']      ?? [];
    $weekStartInput = $_POST['week_start'] ?? $weekStart;
    $applyMf        = isset($_POST['apply-mf']);

    if (empty($userIds) || (!$applyMf && empty($selectedDays))) {
        echo "<script>alert('Please select at least one user and one day.'); window.history.back();</script>";
        exit;
    }

    $empty     = pg_escape_literal(json_encode([]));
    $dayVals   = array_fill_keys(['mon','tue','wed','thu','fri','sat','sun'], $empty);
    $allWeek   = $empty;
    $uidsLit   = pg_escape_literal(json_encode(array_map('intval', $userIds)));

    if ($applyMf) {
        foreach (['mon','tue','wed','thu','fri'] as $d) {
            $dayVals[$d] = $uidsLit;
        }
        $allWeek = $uidsLit;
    } else {
        foreach ($selectedDays as $d) {
            if (isset($dayVals[$d])) {
                $dayVals[$d] = $uidsLit;
            }
        }
    }

    $q = "
      INSERT INTO project_schedule (
        project_id, week_start,
        mon_user_id, tue_user_id, wed_user_id, thu_user_id, fri_user_id, sat_user_id, sun_user_id, all_week_user_id,
        created_at, updated_at
      ) VALUES (
        $projectId, " . pg_escape_literal($conn, $weekStartInput) . ",
        {$dayVals['mon']}, {$dayVals['tue']}, {$dayVals['wed']}, {$dayVals['thu']}, {$dayVals['fri']}, {$dayVals['sat']}, {$dayVals['sun']}, $allWeek,
        NOW(), NOW()
      ) ON CONFLICT (project_id, week_start) DO UPDATE SET
        mon_user_id     = EXCLUDED.mon_user_id,
        tue_user_id     = EXCLUDED.tue_user_id,
        wed_user_id     = EXCLUDED.wed_user_id,
        thu_user_id     = EXCLUDED.thu_user_id,
        fri_user_id     = EXCLUDED.fri_user_id,
        sat_user_id     = EXCLUDED.sat_user_id,
        sun_user_id     = EXCLUDED.sun_user_id,
        all_week_user_id= EXCLUDED.all_week_user_id,
        updated_at      = NOW()
    ";
    $res = pg_query($conn, $q);
    if (!$res) {
        echo "<pre>SQL ERROR: " . pg_last_error($conn) . "\n\n" . htmlspecialchars($q) . "</pre>";
        exit;
    }
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}

// --- Fetch project addresses for modal ---
$addresses = pg_fetch_all(pg_query($conn,
    "SELECT id, address_line1 FROM project_addresses ORDER BY address_line1"
)) ?: [];

// --- Fetch users and mappings ---
$users = pg_fetch_all(pg_query($conn, "
    SELECT u.id, u.full_name, u.role, t.name AS trade, t.color AS trade_color
      FROM users u
      LEFT JOIN trades t ON t.id = u.trade_id
     ORDER BY u.full_name
")) ?: [];

$userMap  = [];
$colorMap = [];
$roleMap  = [];
$tradeMap = [];
foreach ($users as $u) {
    $userMap[$u['id']]  = $u['full_name'];
    $colorMap[$u['id']] = $u['trade_color'] ?: '#ccc';
    $roleMap[$u['id']]  = $u['role'];
    $tradeMap[$u['id']] = $u['trade'];
}

// --- Fetch assignments for the currently selected week (only existing schedule rows) ---
$assigns = pg_fetch_all(pg_query($conn,
    "SELECT
        sa.project_id      AS id,
        pa.address_line1   AS address,
        COALESCE(sa.all_week_user_id, '[]') AS all_week_user_id,
        COALESCE(sa.mon_user_id,      '[]') AS mon_user_id,
        COALESCE(sa.tue_user_id,      '[]') AS tue_user_id,
        COALESCE(sa.wed_user_id,      '[]') AS wed_user_id,
        COALESCE(sa.thu_user_id,      '[]') AS thu_user_id,
        COALESCE(sa.fri_user_id,      '[]') AS fri_user_id,
        COALESCE(sa.sat_user_id,      '[]') AS sat_user_id,
        COALESCE(sa.sun_user_id,      '[]') AS sun_user_id
     FROM project_schedule sa
     JOIN project_addresses pa
       ON pa.id = sa.project_id
    WHERE sa.week_start = $weekStartSQL
 ORDER BY pa.address_line1"
)) ?: [];

$dayLabels   = [
    'all_week' => 'All Week',
    'mon'      => 'Monday',
    'tue'      => 'Tuesday',
    'wed'      => 'Wednesday',
    'thu'      => 'Thursday',
    'fri'      => 'Friday',
    'sat'      => 'Saturday',
    'sun'      => 'Sunday',
];
$weekdayKeys = array_filter(array_keys($dayLabels), fn($k) => $k !== 'all_week');

$activeView = $_GET['view'] ?? 'project';
?>
<h1 style="text-align:center; margin:1.5rem 0; font-size:1.75rem; font-weight:bold;">
  SCHEDULER
</h1>

<!-- Week filter tabs -->
<div class="tabs week-tabs" style="margin-bottom:1rem;">
  <?php foreach ([
    'current' => 'This Week',
    'next'    => 'Next Week',
    'ahead2'  => 'Two Weeks Ahead'
  ] as $key => $label): ?>
    <a
      href="?view=<?= $activeView ?>&filter=<?= $key ?>"
      class="tab-btn<?= ($weekFilter === $key ? ' active' : '') ?>"
      style="margin-right:0.5rem;"
    >
      <?= htmlspecialchars($label) ?>
    </a>
  <?php endforeach; ?>
</div>

<div class="container page-content">
  <div class="tabs" style="display:flex; justify-content:center;">
    <a href="?view=project&filter=<?= $weekFilter ?>"
       class="tab-btn<?= $activeView==='project' ? ' active':'' ?>">Project Schedule</a>
    <a href="?view=user&filter=<?= $weekFilter ?>"
       class="tab-btn<?= $activeView==='user' ? ' active':'' ?>">User Schedule</a>
  </div>

  <!-- Week Range Display -->
  <div class="week-range" style="margin-bottom:1rem; font-weight:bold; text-align:center;">
    <?= date('M j, Y', strtotime($weekStart)) . ' – ' . date('M j, Y', strtotime($weekEnd)); ?>
  </div>

  <?php if ($activeView==='project'): ?>
    <div class="tab-panel active">
      <button id="lock-all" class="btn-modify">✏️ MODIFY</button>
      <button id="discard-changes" class="btn-cancel" style="display:none; margin-left:0.5rem;">DISCARD CHANGES</button>
      <button type="button" id="add-new" class="btn-primary" onclick="openScheduleModal()">ADD NEW</button>

      <table id="schedule-table" class="scheduler-table styled-table locked">
        <thead>
          <tr>
            <th>Address</th>
            <?php foreach ($dayLabels as $lbl): ?><th><?= htmlspecialchars($lbl) ?></th><?php endforeach; ?>
            <th>Clear</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($assigns as $row): ?>
            <tr data-id="<?= $row['id'] ?>">
              <td><?= htmlspecialchars($row['address']) ?></td>
              <?php foreach (array_keys($dayLabels) as $d): ?>
                <td class="tag-cell" data-day="<?= $d ?>">
                  <select class="user-select">
                    <option value="">-- Select User --</option>
                    <?php foreach ($users as $u): ?>
                      <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['full_name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <div class="tag-container">
                    <?php
                      $ids = json_decode($row["{$d}_user_id"] ?? '[]', true) ?: [];
                      foreach ($ids as $uid): ?>
                        <span class="tag" data-user-id="<?= $uid ?>" style="background-color:<?= $colorMap[$uid] ?>">
                          <?= htmlspecialchars($userMap[$uid] ?? '-') ?>
                          <span class="tag-remove">×</span>
                        </span>
                    <?php endforeach; ?>
                  </div>
                </td>
              <?php endforeach; ?>
              <td>
                <button class="btn-clear">🧹</button>
                <button class="btn-delete">🗑️</button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <div class="tab-panel active">
      <?php
        $userAssigns = [];
        foreach ($assigns as $r) {
          foreach (array_slice(array_keys($dayLabels), 1) as $d) {
            $ids = json_decode($r["{$d}_user_id"] ?? '[]', true) ?: [];
            foreach ($ids as $uid) {
              $userAssigns[$uid][$d][] = $r['address'];
            }
          }
        }
      ?>
      <table class="scheduler-table styled-table">
        <thead>
          <tr>
            <th>User</th><th>Role</th><th>Trade</th>
            <?php foreach ($weekdayKeys as $key): ?>
              <th><?= htmlspecialchars($dayLabels[$key]) ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($userAssigns as $uid => $daysArr): ?>
            <?php if (array_filter($daysArr)): ?>
              <tr>
                <td><?= htmlspecialchars($userMap[$uid] ?? '') ?></td>
                <td><?= htmlspecialchars($roleMap[$uid] ?? '') ?></td>
                <td><?= htmlspecialchars($tradeMap[$uid] ?? '') ?></td>
                <?php foreach ($weekdayKeys as $d): ?>
                  <td>
                    <div class="tag-container">
                      <?php foreach ($daysArr[$d] ?? [] as $addr): ?>
                        <span class="tag"><?= htmlspecialchars($addr) ?></span>
                      <?php endforeach; ?>
                    </div>
                  </td>
                <?php endforeach; ?>
              </tr>
            <?php endif; ?>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<!-- Add New Schedule Modal -->
<div id="addScheduleModal" class="modal-overlay">
  <div class="modal-box">
    <form id="addScheduleForm" method="POST" action="?view=project&filter=<?= htmlspecialchars($weekFilter) ?>" class="modal-content">
      <input type="hidden" name="add_schedule" value="1">
      <input type="hidden" name="week_start" value="<?= htmlspecialchars($weekStart) ?>">
      <div class="form-row">
        <label>Week Start</label>
        <input type="text" readonly value="<?= htmlspecialchars($weekStart) ?>" class="form-control">
      </div>
      <div class="form-row">
        <label>Select Project</label>
        <select name="project_id" required>
          <option value="">-- Select --</option>
          <?php foreach ($addresses as $p): ?>
            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['address_line1']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-row">
        <label>Available Users</label>
        <select id="modal-user-select" class="form-control">
          <option value="">-- Select User --</option>
          <?php foreach ($users as $u): ?>
            <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['full_name']) ?></option>
          <?php endforeach; ?>
        </select>
        <div id="modal-tag-container" class="tag-container"></div>
      </div>
      <div class="form-row">
        <label>Assign to Days</label>
        <div id="modal-days-checkboxes" style="display:flex;flex-wrap:wrap;gap:1rem;">
          <?php foreach (['mon'=>'Mon','tue'=>'Tue','wed'=>'Wed','thu'=>'Thu','fri'=>'Fri','sat'=>'Sat','sun'=>'Sun'] as $val => $lab): ?>
            <label style="display:flex;align-items:center;gap:0.3rem;">
              <input type="checkbox" name="days[]" value="<?= $val ?>"> <?= $lab ?>
            </label>
          <?php endforeach; ?>
        </div>
        <label style="margin-top:0.5rem;display:flex;align-items:center;gap:0.3rem;">
          <input type="checkbox" name="apply-mf" id="modal-apply-mf"> Mon–Fri
        </label>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn-primary">Save</button>
        <button type="button" class="btn-cancel" onclick="closeScheduleModal()">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
const userMap  = <?= json_encode($userMap) ?>;
const colorMap = <?= json_encode($colorMap) ?>;
const weekdays = ['mon','tue','wed','thu','fri','sat','sun'];

function createUserTag(id, container) {
  if (container.querySelector(`[data-user-id='${id}']`)) return;
  const select = container.closest('td')?.querySelector('select.user-select');
  const option = select?.querySelector(`option[value='${id}']`);
  if (option) option.remove();
  const tag = document.createElement('span');
  tag.className = 'tag'; tag.dataset.userId = id;
  tag.textContent = userMap[id] || 'User';
  const rem = document.createElement('span'); rem.className = 'tag-remove'; rem.textContent = '×';
  tag.appendChild(rem);
  tag.style.backgroundColor = colorMap[id] || '#ccc';
  container.appendChild(tag);
  attachDragHandlers(tag);
}

function attachDragHandlers(tag) {
  tag.draggable = true;
  tag.addEventListener('dragstart', e => {
    if (document.getElementById('schedule-table').classList.contains('locked')) { e.preventDefault(); return; }
    e.dataTransfer.setData('text/plain', tag.dataset.userId);
    tag.classList.add('dragging');
  });
  tag.addEventListener('dragend', () => tag.classList.remove('dragging'));
}

function initCellSelects() {
  document.querySelectorAll('select.user-select').forEach(sel => {
    sel.addEventListener('change', e => {
      const uid = e.target.value;
      if (!uid) return;
      const cell = e.target.closest('td');
      const container = cell.querySelector('.tag-container');
      createUserTag(uid, container);
      e.target.value = '';
    });
  });
}

function initDragDrop() {
  document.querySelectorAll('.tag').forEach(attachDragHandlers);
  document.querySelectorAll('.tag-cell').forEach(cell => {
    cell.addEventListener('dragover', e => e.preventDefault());
    cell.addEventListener('drop', e => {
      e.preventDefault();
      const dragged = document.querySelector('.tag.dragging');
      if (!dragged) return;
      const container = cell.querySelector('.tag-container');
      const id = dragged.dataset.userId;
      if (!container.querySelector(`[data-user-id='${id}']`)) {
        const sel = cell.querySelector('select.user-select');
        const opt = sel.querySelector(`option[value='${id}']`);
        if (opt) opt.remove();
        dragged.classList.remove('dragging');
        container.appendChild(dragged);
      }
    });
  });
}

function initGlobalClicks() {
  document.addEventListener('click', e => {
    if (e.target.classList.contains('btn-clear')) {
      e.target.closest('tr').querySelectorAll('.tag').forEach(t => t.remove());
    }
    if (e.target.classList.contains('btn-delete')) {
      if (confirm('Delete this schedule row?')) {
        const tr = e.target.closest('tr');
        fetch(`scheduler.php?delete=1&id=${tr.dataset.id}`, { method: 'POST' })
          .then(res => res.ok ? tr.remove() : alert('Delete failed'));
      }
    }
    if (e.target.classList.contains('tag-remove')) {
      const tag = e.target.closest('.tag');
      const id = tag.dataset.userId;
      const cell = tag.closest('td');
      const sel = cell.querySelector('select.user-select');
      if (sel && !sel.querySelector(`option[value='${id}']`)) {
        const opt = document.createElement('option'); opt.value = id; opt.textContent = userMap[id] || 'User';
        sel.appendChild(opt);
      }
      tag.remove();
    }
    if (e.target.id === 'addScheduleModal') closeScheduleModal();
  });
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeScheduleModal();
  });
}

function initSchedulerSaveSync() {
  const lockBtn = document.getElementById('lock-all');
  const discard = document.getElementById('discard-changes');
  const tbl = document.getElementById('schedule-table');
  lockBtn.addEventListener('click', async () => {
    const locked = tbl.classList.contains('locked');
    console.log('lockBtn clicked; locked=', locked);
    if (locked) {
      tbl.classList.remove('locked');
      lockBtn.textContent='💾 SAVE'; lockBtn.className='btn-success'; discard.style.display='inline-block';
    } else {
      console.log('🔄 Sync start');
      const payload = Array.from(tbl.querySelectorAll('tr[data-id]')).map(r=>({
        id: +r.dataset.id,
        days: weekdays.reduce((a,d)=>{a[d]=Array.from(r.querySelectorAll(`[data-day="${d}"] .tag`)).map(t=>+t.dataset.userId);return a;},{})
      }));
      console.log('Payload',payload);
      const res = await fetch(`${location.pathname}?sync=1`,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
      console.log('Fetch response',res);
      if (res.ok) alert('Changes saved');
      tbl.classList.add('locked');
      lockBtn.textContent='✏️ MODIFY'; lockBtn.className='btn-modify'; discard.style.display='none';
    }
  });
  discard.addEventListener('click',()=>location.reload());
}




function openScheduleModal() { document.getElementById('addScheduleModal').style.display = 'flex'; }
function closeScheduleModal() { document.getElementById('addScheduleModal').style.display = 'none'; }

function initModal() {
document.getElementById('modal-user-select').addEventListener('change', e => {
  const id = e.target.value;
  if (!id) return;
  const container = document.getElementById('modal-tag-container');
  if (!container.querySelector(`[data-user-id='${id}']`)) {
    const tag = document.createElement('span'); tag.className='tag'; tag.dataset.userId=id; tag.textContent=userMap[id];
    const rem = document.createElement('span'); rem.className='tag-remove'; rem.textContent='×'; tag.appendChild(rem);
    container.appendChild(tag);
    // This input is what gets POSTed:
    const input = document.createElement('input'); input.type='hidden'; input.name='user_ids[]'; input.value=id;
    container.appendChild(input);
  }
  e.target.value='';
});

  document.getElementById('modal-apply-mf').addEventListener('change', e => {
    document.querySelectorAll('#modal-days-checkboxes input').forEach(cb => {
      if (['mon','tue','wed','thu','fri'].includes(cb.value)) cb.checked = e.target.checked;
    });
  });
  document.getElementById('modal-tag-container').addEventListener('click', e => {
    if (e.target.classList.contains('tag-remove')) {
      const tag = e.target.closest('span.tag'); tag.nextSibling.remove(); tag.remove();
    }
  });
}





// Initialize all handlers on DOM ready
document.addEventListener('DOMContentLoaded', () => {
  initSchedulerSaveSync();
  initCellSelects();
  initDragDrop();
  initGlobalClicks();
  initModal();
});
</script>
<style>
/* Center table header text */
.scheduler-table th { text-align: center; }
.modal-overlay {
  position:fixed; top:0; left:0; right:0; bottom:0;
  display:none; background:rgba(0,0,0,0.6);
  justify-content:center; align-items:center; z-index:999;
}
.modal-box {
  background:#fff; padding:1.5rem;
  border-radius:8px; max-width:90%; max-height:90vh; overflow:auto;
}
.tag-cell {
  position: relative; vertical-align: top !important;
  overflow: visible; padding-top: 0.5rem; text-align: center;
}
.user-select {
  width:100%; position:absolute; top:4px; left:4px; z-index:10;
}
.tag-container {
  display: flex; flex-wrap: wrap; gap: 0.4rem;
  justify-content: center; align-items: center;
}
#schedule-table:not(.locked) .tag-container {
  margin-top: 2.5rem;
}
#schedule-table.locked .tag-container {
  margin-top: 0; flex-direction: column; align-items: center;
}
.tag {
  padding:0.3rem 0.6rem; border-radius:999px;
  cursor:move; user-select:none; display:inline-flex; align-items:center;
}
.tag-remove { margin-left:0.5rem; cursor:pointer; }
#schedule-table { border-collapse: separate; }
#schedule-table td { vertical-align:top!important; overflow:visible; }
#schedule-table.locked .tag-remove { display: none !important; }
#schedule-table.locked .btn-clear { display: none !important; }
/* hide All Week column */
#schedule-table th:nth-child(2),
#schedule-table td:nth-child(2) {
  display: none;
}
</style>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
