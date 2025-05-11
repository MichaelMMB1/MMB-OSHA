<?php
// dashboard.php – Main Dashboard for MMB Contractors Portal

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

$role = strtolower($_SESSION['role'] ?? '');

require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/header.php';

// Fetch distinct roles for admin filter dropdown
$rolesList = [];
if ($res = $mysqli->query("SELECT DISTINCT role FROM users ORDER BY role")) {
    while ($r = $res->fetch_assoc()) {
        $rolesList[] = $r['role'];
    }
    $res->free();
}

// Fetch all projects
$projects = [];
$stmt = $mysqli->prepare("SELECT id, project_name FROM project_addresses ORDER BY project_name ASC");
$stmt->execute();
$stmt->bind_result($projId, $projName);
while ($stmt->fetch()) {
    $projects[] = ['id' => $projId, 'name' => $projName];
}
$stmt->close();

// Check for open check‑in
$user_id          = $_SESSION['user_id'];
$has_open_checkin = false;
$current_location = '';
$checkin_id       = null;
$check_in_date    = null;
$check_in_clock   = null;
$stmt = $mysqli->prepare(
    "SELECT id, location, check_in_date, check_in_clock
       FROM check_log
      WHERE user_id = ? AND check_out_date IS NULL AND check_out_clock IS NULL
      ORDER BY check_in_date DESC, check_in_clock DESC
      LIMIT 1"
);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows) {
    $has_open_checkin = true;
    $stmt->bind_result($checkin_id, $current_location, $check_in_date, $check_in_clock);
    $stmt->fetch();
}
$stmt->close();

// Build filters
$filterToday = (isset($_GET['filter_today']) && $_GET['filter_today'] === '1');
$filterRole  = $_GET['role_filter'] ?? '';
$filterStart = $_GET['start_date']  ?? '';
$filterEnd   = $_GET['end_date']    ?? '';

$where      = [];
$params     = [];
$paramTypes = '';

if ($filterToday) {
    $where[] = "cl.check_in_date = CURDATE()";
}
if ($filterRole !== '') {
    $where[]      = "u.role = ?";
    $params[]     = $filterRole;
    $paramTypes  .= 's';
}
if ($filterStart !== '') {
    $where[]      = "cl.check_in_date >= ?";
    $params[]     = $filterStart;
    $paramTypes  .= 's';
}
if ($filterEnd !== '') {
    $where[]      = "cl.check_in_date <= ?";
    $params[]     = $filterEnd;
    $paramTypes  .= 's';
}

// Assemble SQL
$sql = "SELECT u.full_name,
               cl.location        AS project,
               cl.check_in_date   AS in_date,
               cl.check_in_clock  AS in_time,
               cl.check_out_date  AS out_date,
               cl.check_out_clock AS out_time,
               IF(cl.check_out_date IS NOT NULL,
                  TIMESTAMPDIFF(SECOND, TIMESTAMP(cl.check_in_date,cl.check_in_clock),
                                           TIMESTAMP(cl.check_out_date,cl.check_out_clock)),
                  TIMESTAMPDIFF(SECOND, TIMESTAMP(cl.check_in_date,cl.check_in_clock), NOW())
               ) AS time_spent,
               (cl.check_out_date IS NULL) AS is_active
          FROM check_log cl
          JOIN users u ON cl.user_id = u.id";

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY cl.check_in_date DESC, cl.check_in_clock DESC";

$stmt = $mysqli->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($paramTypes, ...$params);
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
  <title>Dashboard – MMB Contractors Portal</title>
  <link rel="stylesheet" href="/MMB/assets/css/style.css"/>
</head>
<body>

<div style="padding:1rem; background:#f1f1f1; text-align:center;">
  Current Role: <strong><?= htmlspecialchars($role) ?></strong>
</div>














<?php if ($role === 'admin'): ?>
  <div class="container edge-to-edge panel-wrapper">
    <div class="panel panel--dress-code">
<form id="filterForm" method="get" class="filter-bar">
        <div class="filter-group search">
  <label for="searchInput">Search</label>
  <input
    type="text"
    id="searchInput"
    class="search-input"
    placeholder="Search records…"
    oninput="searchTable()"
  >
</div>

        <div class="filter-group">
          <label for="start_date">Start Date</label>
          <input type="date" name="start_date" id="start_date" class="filter-dropdown" value="<?=htmlspecialchars($filterStart)?>">
        </div>
        <div class="filter-group">
          <label for="end_date">End Date</label>
          <input type="date" name="end_date" id="end_date" class="filter-dropdown" value="<?=htmlspecialchars($filterEnd)?>">
        </div>
        <div class="filter-group">
          <label for="role_filter">Role</label>
          <select name="role_filter" id="role_filter" class="filter-dropdown">
            <option value="">All Roles</option>
            <?php foreach($rolesList as $r): ?>
              <option value="<?=htmlspecialchars($r)?>" <?= $filterRole===$r?'selected':''?>><?=htmlspecialchars($r)?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button  type="submit"   name="filter_today"  value="1"   class="btn btn-primary">  Today  </button>
          <button type="button" class="btn btn-secondary" onclick="window.location='dashboard.php'">Reset</button>
        </div>
      </form>

      <div class="table-panel panel-wrapper">
        <table id="checkInTable" class="styled-table" data-sort-dir="desc">
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
            <?php if ($checkLog->num_rows): ?>
              <?php while ($r = $checkLog->fetch_assoc()): ?>
                <tr class="<?= $r['is_active'] ? 'highlight-row' : '' ?>">
                  <td><?=htmlspecialchars($r['full_name'])?></td>
                  <td><?=htmlspecialchars($r['project'])?></td>
                  <td><?=htmlspecialchars($r['in_date'])?></td>
                  <td><?=htmlspecialchars($r['in_time'])?></td>
                  <td><?=htmlspecialchars($r['out_date']?:'—')?></td>
                  <td><?=htmlspecialchars($r['out_time']?:'—')?></td>
                  <td><?=sprintf("%02dh %02dm", floor($r['time_spent']/3600), floor(($r['time_spent']%3600)/60))?></td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr><td colspan="7" style="text-align:center;">No records found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
<?php else: ?>
  <div class="container edge-to-edge panel-wrapper">
    <div class="panel--checkin checkin-panel">
      <p class="location"><strong><?=htmlspecialchars($current_location?:'No Active Check‑in')?></strong></p>
      <p class="safety-text">Safety First! Always wear your PPE.</p>
      <?php if ($has_open_checkin): ?>
        <p id="checkin-timer" class="timer-display" data-start="<?=$check_in_date?>T<?=$check_in_clock?>"></p>
        <button class="checkout-btn" onclick="openCheckoutModal()">Check Out</button>
      <?php else: ?>
        <button class="btn btn-primary" onclick="openCheckinModal()">Check In</button>
      <?php endif; ?>
      <img src="/MMB/assets/images/safety_guy.png" class="safety-icon" alt="Safety Mascot"/>
    </div>
  </div>
<?php endif; ?>

<div id="checkinModal" class="modal-overlay">
  <div class="modal-content">
    <img src="/MMB/assets/images/safety_first.png" alt="Safety First" class="modal-image modal-image--top"/>
    <h3>Select Project to Check In</h3>
    <form id="checkinForm" method="POST" action="activity/checklog/process_checkin.php">
      <div class="radio-group">
        <?php if (!empty($projects)): ?>
          <?php foreach ($projects as $p): ?>
            <label><input type="radio" name="project_id" value="<?=htmlspecialchars($p['id'])?>" required> <?=htmlspecialchars($p['name'])?></label><br>
          <?php endforeach; ?>
        <?php else: ?>
            <p>No projects available to check in.</p>
        <?php endif; ?>
      </div>
      <div class="button-group" style="margin-top:1rem;">
        <button type="button" class="btn btn-secondary" onclick="closeCheckinModal()">Cancel</button>
        <button type="submit" class="btn btn-primary" <?= empty($projects) ? 'disabled' : '' ?>>Confirm</button>
      </div>
    </form>
  </div>
</div>

<div id="checkoutModal" class="modal-overlay">
  <div class="modal-content">
    <h3>Confirm Check Out</h3>
    <p>Are you sure you want to check out from <strong><br><?=htmlspecialchars($current_location)?></strong>?</p>
    <form id="checkoutForm" method="POST" action="activity/checklog/process_checkout.php">
      <input type="hidden" name="checkin_id" value="<?=$checkin_id?>">
      <div class="button-group" style="margin-top:1rem;">
        <button type="button" class="btn btn-secondary" onclick="closeCheckoutModal()">Cancel</button>
        <button type="submit" class="btn btn-primary">Confirm</button>
      </div>
    </form>
  </div>
</div>



<!-- ====== ORIGINAL TIMER SCRIPT ====== -->
<script>
// check-in timer (IIFE)
(function(){
  const timerEl = document.getElementById('checkin-timer');
  // only run if there’s a data-start on that element
  if (!timerEl || !timerEl.dataset.start) return;

  // parse a Date from the ISO string in data-start
  const start = new Date(timerEl.dataset.start);
  if (isNaN(start.getTime())) {
    timerEl.textContent = "Error: Invalid start time";
    return;
  }

  function pad(n){ return n.toString().padStart(2,'0'); }

  function updateTimer(){
    const now  = new Date();
    const diff = now.getTime() - start.getTime();

    if (diff < 0) {
      timerEl.textContent = "00:00:00";
      return;
    }

    const hr  = Math.floor(diff / 3600000);
    const min = Math.floor((diff % 3600000) / 60000);
    const sec = Math.floor((diff % 60000) / 1000);

    timerEl.textContent = `${pad(hr)}:${pad(min)}:${pad(sec)}`;
  }

  updateTimer();                      // first draw
  setInterval(updateTimer, 1000);     // tick every second
})();
</script>


<script>
// AJAX submission for check-in and check-out forms
;['checkinForm','checkoutForm'].forEach(formId => {
  const form = document.getElementById(formId);
  if (!form) return;
  form.addEventListener('submit', e => {
    e.preventDefault();
    // Basic validation for check-in form (ensure a project is selected)
    if (formId === 'checkinForm') {
        const selectedProject = form.querySelector('input[name="project_id"]:checked');
        if (!selectedProject) {
            alert('Please select a project to check in.'); // Consider replacing alert with a styled message
            return;
        }
    }

    fetch(form.action, {
      method: form.method,
      body: new FormData(form),
      credentials: 'same-origin' // Important for sessions/cookies
    })
    .then(response => {
      if (!response.ok) {
        // Try to get error message from server if available
        return response.text().then(text => { throw new Error(text || 'Network response was not ok.') });
      }
      return response.text(); // Or response.json() if your PHP script returns JSON
    })
    .then(data => {
      // console.log('Success:', data); // For debugging
      // Close the appropriate modal
      if (formId === 'checkinForm') {
        closeCheckinModal();
      } else {
        closeCheckoutModal();
      }
      location.reload(); // Reload the page to reflect changes
    })
    .catch(error => {
      console.error('Error:', error);
      // Display a user-friendly error message
      // This could be a styled div on the page instead of an alert
      alert('An error occurred: ' + error.message);
    });
  });
});

// Modal open/close functions
function openCheckinModal() {
  document.getElementById('checkinModal').style.display = 'flex';
}
function closeCheckinModal() {
  document.getElementById('checkinModal').style.display = 'none';
}
function openCheckoutModal() {
  // Only open if there's an active check-in
  <?php if ($has_open_checkin): ?>
    document.getElementById('checkoutModal').style.display = 'flex';
  <?php else: ?>
    // Optionally, inform the user they are not checked in
    // alert("You are not currently checked in."); 
  <?php endif; ?>
}
function closeCheckoutModal() {
  document.getElementById('checkoutModal').style.display = 'none';
}

// Close modals when clicking outside of the modal content
window.addEventListener('click', e => {
  if (e.target.id === 'checkinModal')   closeCheckinModal();
  if (e.target.id === 'checkoutModal')  closeCheckoutModal();
});

// Table sorting function
function sortTable(col) {
  const tbl = document.getElementById('checkInTable');
  if (!tbl) return;
  const tBody = tbl.tBodies[0];
  if (!tBody) return;
  
  const asc = tbl.getAttribute('data-sort-dir') !== 'asc';
  const rows = Array.from(tBody.rows);

  rows.sort((a, b) => {
    let valA = a.cells[col].textContent.trim();
    let valB = b.cells[col].textContent.trim();

    // Attempt to convert to number for numeric sorting, otherwise localeCompare
    const numA = parseFloat(valA.replace(/[^0-9.-]+/g,"")); // More robust number extraction
    const numB = parseFloat(valB.replace(/[^0-9.-]+/g,""));

    if (!isNaN(numA) && !isNaN(numB)) {
        return asc ? numA - numB : numB - numA;
    }
    return asc
      ? valA.localeCompare(valB, undefined, {numeric: true, sensitivity: 'base'})
      : valB.localeCompare(valA, undefined, {numeric: true, sensitivity: 'base'});
  });

  rows.forEach(r => tBody.appendChild(r));
  tbl.setAttribute('data-sort-dir', asc ? 'asc' : 'desc');
}

// Table search filter function
function searchTable() {
  const term = document.getElementById('searchInput').value.toLowerCase();
  const rows = document.querySelectorAll('#checkInTable tbody tr');
  rows.forEach(row => {
    row.style.display = row.textContent.toLowerCase().includes(term) ? '' : 'none';
  });
}


// ── Auto‑submit filter form on change ───────────────────────────────────────
const filterForm = document.getElementById('filterForm');
filterForm.querySelectorAll('select, input[type="date"]').forEach(el => {
  el.addEventListener('change', () => filterForm.submit());
});


// Column resizing and drag-and-drop (IIFE)
(function(){
  const table = document.getElementById('checkInTable');
  if (!table) return;

  // Resizing
  document.querySelectorAll('.th-resizer').forEach(handle => {
    let th = handle.parentElement;
    let startX, startW;

    handle.addEventListener('mousedown', e => {
      // Prevent text selection during drag
      e.preventDefault(); 
      startX = e.clientX;
      startW = th.offsetWidth;
      
      const mouseMoveHandler = e => {
        const width = startW + (e.clientX - startX);
        if (width > 40) { // Minimum column width
          th.style.width = width + 'px';
        }
      };
      
      const mouseUpHandler = () => {
        document.removeEventListener('mousemove', mouseMoveHandler);
        document.removeEventListener('mouseup', mouseUpHandler);
      };
      
      document.addEventListener('mousemove', mouseMoveHandler);
      document.addEventListener('mouseup', mouseUpHandler);
    });
  });

  // Drag and drop columns
  let dragSrcIndex;
  table.querySelectorAll('th').forEach((th, idx) => {
    // Ensure only table headers without resizers are draggable, or adjust logic
    if (th.querySelector('.th-resizer')) { 
        th.draggable = true;

        th.addEventListener('dragstart', e => {
            dragSrcIndex = idx;
            e.dataTransfer.effectAllowed = 'move';
            // Optional: style the dragged column header
            // e.target.style.opacity = '0.5'; 
        });

        th.addEventListener('dragover', e => {
            e.preventDefault(); // Necessary to allow dropping
            e.dataTransfer.dropEffect = 'move';
        });

        th.addEventListener('drop', e => {
            e.preventDefault();
            const targetTh = e.target.closest('th'); // Get the TH element even if dropped on resizer
            if (!targetTh) return;

            const toIndex = Array.from(targetTh.parentNode.children).indexOf(targetTh);

            if (dragSrcIndex !== toIndex) {
                Array.from(table.rows).forEach(row => {
                    const cellToMove = row.cells[dragSrcIndex];
                    const targetCell = row.cells[toIndex];
                    // Insert before the target cell, or append if it's the last
                    row.insertBefore(cellToMove, targetCell); 
                });
            }
            // Optional: reset opacity if changed on dragstart
            // e.target.style.opacity = '1'; 
        });
        
        // Optional: clear opacity if drag ends without drop
        // th.addEventListener('dragend', e => {
        //     e.target.style.opacity = '1';
        // });
    }
  });
})();



// Tab switcher logic
const tabs   = document.querySelectorAll('.tab-btn');
const panels = document.querySelectorAll('.tab-panel');

tabs.forEach(tab => {
  tab.addEventListener('click', () => {
    // Deactivate all buttons and panels
    tabs.forEach(t => t.classList.remove('active'));
    panels.forEach(p => p.classList.remove('active'));

    // Activate clicked button and its corresponding panel
    tab.classList.add('active');
    const targetPanelId = tab.dataset.target;
    const targetPanel = document.getElementById(targetPanelId);
    if (targetPanel) {
      targetPanel.classList.add('active');
    }
  });
});




</script>






<?php require_once(__DIR__ . '/../includes/footer.php'); ?>

</body>
</html>