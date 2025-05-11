<?php
// public/users/users.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/header.php';

// -----------------------------------------------------------------------------
// 1) Lookup data for Users tab
// -----------------------------------------------------------------------------

// a) Positions
$positionOptions = [];
if ($res = $mysqli->query("SELECT position_title FROM position_titles ORDER BY position_title")) {
    while ($r = $res->fetch_assoc()) {
        $positionOptions[] = $r['position_title'];
    }
    $res->free();
}

// b) Roles
$roleOptions = [];
$sql = "SELECT `id`,`name` FROM `roles` ORDER BY `name`";
if ($res = $mysqli->query($sql)) {
    while ($row = $res->fetch_assoc()) {
        // key = role id, value = role name
        $roleOptions[$row['id']] = $row['name'];
    }
    $res->free();
} else {
    // show any SQL error
    echo "<p style='color:red;'>Roles query error: " . htmlspecialchars($mysqli->error) . "</p>";
}



// -----------------------------------------------------------------------------
// 0) Fetch Trades for the dropdown
// -----------------------------------------------------------------------------
$tradeOptions = [];
if ($res = $mysqli->query("SELECT name FROM trades ORDER BY name")) {
    while ($r = $res->fetch_assoc()) {
        $tradeOptions[] = $r['name'];
    }
    $res->free();
}

// -----------------------------------------------------------------------------
// 2) Handle Companies tab filter & data
// -----------------------------------------------------------------------------
$companySearch = $_GET['company_q'] ?? '';
$tradeFilter   = $_GET['trade_filter'] ?? '';

// Build WHERE clauses
$w = [];
$p = [];
if ($companySearch !== '') {
    $w[] = "name LIKE ?";
    $p[] = "%{$companySearch}%";
}
if ($tradeFilter !== '') {
    $w[] = "trade = ?";
    $p[] = $tradeFilter;
}


// Build base query without ORDER BY
$sql = "
  SELECT
    id,
    name,
    trade,
    website,
    email,
    phone,
    full_address
  FROM companies
";

// Add WHERE clause if there are filters
if ($w) {
    $sql .= " WHERE " . implode(" AND ", $w);
}

// Append single ORDER BY
$sql .= " ORDER BY name";

$stmt = $mysqli->prepare($sql);
if ($p) {
    $stmt->bind_param(str_repeat('s', count($p)), ...$p);
}
$stmt->execute();
$companies = $stmt->get_result();
$stmt->close();

// -----------------------------------------------------------------------------
// 3) Fetch Users tab data
// -----------------------------------------------------------------------------
$search     = $_GET['q']               ?? '';
$posFilter  = $_GET['position_title'] ?? '';
$roleFilter = $_GET['role']           ?? '';

$where  = [];
$params = [];
if ($search !== '') {
    $where[]   = "(full_name LIKE ? OR email LIKE ? OR username LIKE ?)";
    $like      = "%{$search}%";
    array_push($params, $like, $like, $like);
}
if ($posFilter !== '') {
    $where[]  = "position_title = ?";
    $params[] = $posFilter;
}
if ($roleFilter !== '') {
    $where[]  = "role = ?";
    $params[] = $roleFilter;
}

$userSql = "SELECT id, full_name, username, email, phone, position_title, role FROM users";
if ($where) {
    $userSql .= " WHERE " . implode(" AND ", $where);
}
$userSql .= " ORDER BY full_name";

$stmt = $mysqli->prepare($userSql);
if ($params) {
    $stmt->bind_param(str_repeat('s', count($params)), ...$params);
}
$stmt->execute();
$users = $stmt->get_result();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Users & Companies – MMB Contractors Portal</title>
  <link rel="stylesheet" href="/MMB/assets/css/style.css" />
  <style>
    /* ==== COLUMN-RESIZER STYLES ==== */
    .styled-table th { position: relative; overflow: hidden; }
    .th-resizer {
      position: absolute; right: 0; top: 0;
      width: 5px; height: 100%; cursor: col-resize;
      user-select: none; background: rgba(0,0,0,0.1);
    }
  </style>
</head>
<body>

<div class="tabs">
<button type="button" class="tab-btn active" data-target="usersTab">
Users
</button>
<button type="button" class="tab-btn" data-target="companiesTab">
   Companies
</button>
</div>

<!-- USERS TAB -->
<div id="usersTab" class="tab-panel active">
  <div class="container edge-to-edge">
    <form method="get" action="users.php#usersTab" class="filter-bar">
      <div class="filter-group search">
        <label for="q">Search</label>
        <input type="text"
               id="q" name="q"
               class="search-input"
               placeholder="Search users…"
               value="<?=htmlspecialchars($search)?>"
               oninput="this.form.submit()">
      </div>
      <div class="filter-group">
        <label for="position_title">Position</label>
        <select id="position_title"
                name="position_title"
                class="filter-dropdown"
                onchange="this.form.submit()">
          <option value="">All Positions</option>
          <?php foreach($positionOptions as $pos): ?>
            <option <?= $posFilter=== $pos ? 'selected':''?>>
              <?=htmlspecialchars($pos)?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="filter-group">
<label for="roleSelect">Role</label>
<select id="roleSelect" name="role_id" class="filter-dropdown" required>
  <option value="">— Select a role —</option>

  <?php if (empty($roleOptions)): ?>
    <option disabled>No roles found in DB</option>
  <?php else: ?>
    <?php foreach ($roleOptions as $id => $roleName): ?>
      <option value="<?= $id ?>"
        <?= (isset($user['role_id']) && $user['role_id'] == $id) ? 'selected' : '' ?>>
        <?= htmlspecialchars($roleName) ?>
      </option>
    <?php endforeach; ?>
  <?php endif; ?>
</select>

      </div>
      <div class="button-group">
        <button type="button" class="btn btn-primary" onclick="openAddUserModal()">
          Add User
        </button>

              
      </div>
    </form>

    <div class="table-panel panel-wrapper">
      <table id="usersTable" class="styled-table" data-sort-dir="desc">
        <thead>
          <tr>
            <th onclick="sortTable(0)">Name</th>
            <th onclick="sortTable(1)">Username</th>
            <th onclick="sortTable(2)">Email</th>
            <th onclick="sortTable(3)">Phone</th>
            <th onclick="sortTable(4)">Position</th>
            <th onclick="sortTable(5)">Role</th>
            <th>Password</th>
            <th>Edit</th>
          </tr>
        </thead>
        <tbody>
          <?php while($u = $users->fetch_assoc()): ?>
            <tr>
              <td> <form method="POST" action="update_user.php">
          <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
          <input type="hidden" name="field" value="full_name">
          <input
            type="text"
            name="value"
            class="inline-input"
            value="<?=htmlspecialchars($u['full_name'])?>"
            onchange="this.form.submit()"
          >
        </form>
      </td>
              <td><form method="POST" action="update_user.php">
          <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
          <input type="hidden" name="field" value="username">
          <input
            type="text"
            name="value"
            class="inline-input"
            value="<?=htmlspecialchars($u['username'])?>"
            onchange="this.form.submit()"
          >
        </form></td>
              <td><form method="POST" action="update_user.php">
          <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
          <input type="hidden" name="field" value="email">
          <input
            type="email"
            name="value"
            class="inline-input"
            value="<?=htmlspecialchars($u['email'])?>"
            onchange="this.form.submit()"
          >
        </form></td>
              <td>        <form method="POST" action="update_user.php">
          <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
          <input type="hidden" name="field" value="phone">
          <input
            type="text"
            name="value"
            class="inline-input"
            value="<?=htmlspecialchars($u['phone'])?>"
            onchange="this.form.submit()"
          >
        </form></td>
              <td>
                <form method="POST" action="update_user_position.php">
                  <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                  <select name="position_title"
                          class="inline-select"
                          onchange="this.form.submit()">
                    <?php foreach($positionOptions as $opt): ?>
                      <option value="<?=htmlspecialchars($opt)?>"
                        <?= $opt === $u['position_title'] ? 'selected' : '' ?>>
                        <?=htmlspecialchars($opt)?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </form>
              </td>
<td>
  <form method="POST" action="update_user_role.php">
    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">

    <select
      name="role"
      class="inline-select"
      onchange="this.form.submit()"
    >
      <?php foreach($roleOptions as $id => $roleName): ?>
        <option
          value="<?= htmlspecialchars($roleName) ?>"
          <?= ($u['role'] === $roleName) ? 'selected' : '' ?>
        >
          <?= htmlspecialchars($roleName) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </form>
</td>
              <td>
                <button class="btn btn-secondary reset-btn"
                        data-user-id="<?= $u['id']?>"
                        data-user-name="<?=htmlspecialchars($u['full_name'])?>">
                  Reset
                </button>
             <td>
  <button type="button"
          class="btn btn-secondary lock-btn"
          data-locked="false"
          title="Click to lock edits"
          onclick="toggleLock(this)">
    🔓
  </button>
</td>
      </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- COMPANIES TAB -->
<div id="companiesTab" class="tab-panel">
  <div class="container edge-to-edge">
    <!-- FILTER BAR -->
    <form method="get" action="users.php#companiesTab" class="filter-bar">
      <div class="filter-group search">
        <label for="company_q">Search</label>
        <input
          type="text"
          id="company_q"
          name="company_q"
          class="search-input"
          placeholder="Search companies…"
          value="<?= htmlspecialchars($companySearch) ?>"
          oninput="this.form.submit()">
      </div>
      <div class="button-group">
        <button
          type="button"
          class="btn btn-primary"
          onclick="openAddCompanyModal()">
          Add Company
        </button>


      </div>
      
    </form>

    <!-- TABLE PANEL -->
    <div class="table-panel panel-wrapper">
      <table id="companiesTable" class="styled-table" data-sort-dir="desc">
        <thead>
          <tr>
            <th onclick="sortTable(0)">Name</th>
            <th onclick="sortTable(9)">Trade</th>
            <th onclick="sortTable(1)">Website</th>
            <th onclick="sortTable(2)">Email</th>
            <th onclick="sortTable(3)">Phone</th>
            <th onclick="sortTable(4)">Address</th>


          </tr>
        </thead>
        <tbody>
          <?php while($c = $companies->fetch_assoc()): ?>
          <tr>
            <td><?= htmlspecialchars($c['name']) ?></td>
            <td>
              <form method="POST" action="update_company_trade.php">
                <input type="hidden" name="company_id" value="<?= $c['id'] ?>">
                <select
                  name="trade"
                  class="inline-select"
                  onchange="this.form.submit()">
                  <option value="">—</option>
                  <?php foreach($tradeOptions as $t): ?>
                  <option
                    value="<?= htmlspecialchars($t) ?>"
                    <?= $t === $c['trade'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($t) ?>
                  </option>
                  <?php endforeach; ?>
                </select>
              </form>
            </td>
            <td>
              <?php if($c['website']): ?>
              <a href="<?= htmlspecialchars($c['website']) ?>" target="_blank">
                <?= htmlspecialchars($c['website']) ?>
              </a>
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($c['email']) ?></td>
            <td><?= htmlspecialchars($c['phone']) ?></td>
            <td><?= htmlspecialchars($c['full_address']) ?></td>

          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ADD COMPANY MODAL (you’ll need to implement this) -->
<div id="addCompanyModal" class="modal-overlay">
  <div class="modal-content">
    <h3>Add New Company</h3>
    <form method="POST" action="process_add_company.php">
      <label>Name</label>
      <input type="text" name="name" class="modal-input" required>
      <label>Website</label>
      <input type="url"  name="website" class="modal-input">
      <label>Email</label>
      <input type="email" name="email" class="modal-input">
      <label>Phone</label>
      <input type="text" name="phone" class="modal-input">
      <label>Address Line 1</label>
      <input type="text" name="address_line1" class="modal-input">
      <label>Address Line 2</label>
      <input type="text" name="address_line2" class="modal-input">
      <label>City</label>
      <input type="text" name="city" class="modal-input">
      <label>State</label>
      <input type="text" name="state" class="modal-input">
      <label>Postal Code</label>
      <input type="text" name="postal_code" class="modal-input">
      <label>Country</label>
      <input type="text" name="country" class="modal-input">
      <div style="display:flex;justify-content:flex-end;gap:0.5rem;margin-top:1rem;">
        <button type="button" class="btn btn-secondary" onclick="closeAddCompanyModal()">Cancel</button>
        <button type="submit" class="btn btn-primary">Create</button>
      </div>
    </form>
  </div>
</div>

<script>
// ensure the tab button is type="button" so it doesn’t submit the form:
document.querySelectorAll('.tab-btn').forEach(btn=>{
  btn.setAttribute('type','button');
});

// open/close handlers for Add Company modal
function openAddCompanyModal() {
  document.getElementById('addCompanyModal').style.display = 'flex';
}
function closeAddCompanyModal() {
  document.getElementById('addCompanyModal').style.display = 'none';
}
window.addEventListener('click', e => {
  if (e.target.id === 'addCompanyModal') closeAddCompanyModal();
});
</script>


<script>
// Tab switching + hash persistence
document.addEventListener('DOMContentLoaded', () => {
  const tabs   = [...document.querySelectorAll('.tab-btn')],
        panels = [...document.querySelectorAll('.tab-panel')];
  function activate(id) {
    tabs.forEach(b => b.classList.toggle('active', b.dataset.target === id));
    panels.forEach(p => p.classList.toggle('active', p.id === id));
  }
  tabs.forEach(b => b.addEventListener('click', () => {
    activate(b.dataset.target);
    history.replaceState(null,'',`#${b.dataset.target}`);
  }));
  const initial = location.hash.slice(1);
  activate(panels.some(p => p.id === initial) ? initial : tabs[0].dataset.target);
});



// Simple column sorter for both tables
function sortTable(col) {
  ['usersTable','companiesTable'].forEach(id => {
    const tbl = document.getElementById(id);
    if (!tbl) return;
    const asc = tbl.getAttribute('data-sort-dir') !== 'asc';
    const rows = [...tbl.rows].slice(1);
    rows.sort((a,b) => {
      const x = a.cells[col]?.textContent.trim() || '';
      const y = b.cells[col]?.textContent.trim() || '';
      return asc ? x.localeCompare(y) : y.localeCompare(x);
    });
    rows.forEach(r => tbl.tBodies[0].appendChild(r));
    tbl.setAttribute('data-sort-dir', asc ? 'asc' : 'desc');
  });
}

// column-resizer hookup
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.styled-table').forEach(table => {
    table.querySelectorAll('th').forEach(th => {
      const resizer = document.createElement('div');
      resizer.classList.add('th-resizer');
      th.appendChild(resizer);

      resizer.addEventListener('mousedown', e => {
        e.preventDefault();
        const startX = e.clientX, startW = th.offsetWidth;
        function onMove(e) {
          th.style.width = (startW + (e.clientX - startX)) + 'px';
        }
        function onUp() {
          document.removeEventListener('mousemove', onMove);
          document.removeEventListener('mouseup', onUp);
        }
        document.addEventListener('mousemove', onMove);
        document.addEventListener('mouseup', onUp);
      });
    });
  });
});

// Add User modal
function openAddUserModal() { document.getElementById('addUserModal').style.display = 'flex'; }
function closeAddUserModal() { document.getElementById('addUserModal').style.display = 'none'; }
window.addEventListener('click', e => {
  if (e.target.id === 'addUserModal') closeAddUserModal();
});

// Reset Password modal
document.addEventListener('DOMContentLoaded', () => {
  const modal     = document.getElementById('resetPasswordModal'),
        nameSpan  = document.getElementById('reset-user-name'),
        idInput   = document.getElementById('reset-user-id'),
        cancelBtn = document.getElementById('resetCancelBtn');

  document.querySelectorAll('.reset-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      idInput.value        = btn.dataset.userId;
      nameSpan.textContent = btn.dataset.userName;
      modal.style.display  = 'flex';
    });
  });
  cancelBtn.addEventListener('click', () => modal.style.display = 'none');
  modal.addEventListener('click', e => {
    if (e.target === modal) modal.style.display = 'none';
  });
});
</script>

<!-- Add User Modal -->
<div id="addUserModal" class="modal-overlay">
  <div class="modal-content">
    <h3>Add New User</h3>
    <form method="POST" action="process_add_user.php">
      <label>Username</label>
      <input type="text" name="username" class="modal-input" required>
      <label>Password</label>
      <input type="password" name="password" class="modal-input" required>
      <label>Full Name</label>
      <input type="text" name="full_name" class="modal-input" required>
      <label>Email</label>
      <input type="email" name="email" class="modal-input" required>
      <label>Phone</label>
      <input type="text" name="phone" class="modal-input">
      <label>Position</label>
      <select name="position_title" class="modal-input" required>
        <option value="">Select Position</option>
        <?php foreach($positionOptions as $opt): ?>
          <option value="<?=htmlspecialchars($opt)?>"><?=htmlspecialchars($opt)?></option>
        <?php endforeach; ?>
      </select>
<label for="roleSelect">Role</label>
<select id="roleSelect" name="role_id" class="modal-input" required>
  <option value="">— Select a role —</option>
  <?php foreach ($roleOptions as $id => $roleName): ?>
    <option value="<?= $id ?>"
      <?= (isset($user['role_id']) && $user['role_id'] == $id) ? 'selected' : '' ?>>
      <?= htmlspecialchars($roleName) ?>
    </option>
  <?php endforeach; ?>
</select>
      <div style="display:flex;justify-content:flex-end;gap:0.5rem;margin-top:1rem;">
        <button type="button" class="btn btn-secondary" onclick="closeAddUserModal()">Cancel</button>
        <button type="submit" class="btn btn-primary">Create</button>
      </div>
    </form>
  </div>
</div>

<!-- Reset Password Modal -->
<div id="resetPasswordModal" class="modal-overlay">
  <div class="modal-content">
    <h3>Reset Password for <span id="reset-user-name"></span></h3>
    <form method="POST" action="process_reset_password.php">
      <input type="hidden" name="user_id" id="reset-user-id">
      <label>New Password</label>
      <input type="password" name="new_password" class="modal-input" required>
      <label>Confirm Password</label>
      <input type="password" name="confirm_password" class="modal-input" required>
      <div style="display:flex;justify-content:flex-end;gap:0.5rem;margin-top:1rem;">
        <button type="button" class="btn btn-secondary" id="resetCancelBtn">Cancel</button>
        <button type="submit" class="btn btn-primary">Save</button>
      </div>
    </form>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const tabs   = document.querySelectorAll('.tab-btn'),
        panels = document.querySelectorAll('.tab-panel');

  function activateTab(targetId) {
    tabs.forEach(b => b.classList.toggle('active', b.dataset.target === targetId));
    panels.forEach(p => p.classList.toggle('active', p.id === targetId));
    history.replaceState(null, '', `#${targetId}`);
  }


document.addEventListener('DOMContentLoaded', () => {
  const filterForm = document.getElementById('filterForm');
  if (!filterForm) return;

  // whenever any select or text input in the form changes, submit
  filterForm.querySelectorAll('select, input').forEach(el => {
    el.addEventListener('change', () => filterForm.submit());
  });
});


  // wire up clicks
  tabs.forEach(btn => {
    btn.addEventListener('click', () => activateTab(btn.dataset.target));
  });

  // on load, pick the hash or default to the first tab
  const initial = location.hash.slice(1);
  activateTab(
    Array.from(panels).some(p => p.id === initial)
      ? initial
      : tabs[0].dataset.target
  );
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const lockBtns = Array.from(document.querySelectorAll('.lock-btn'));

  // Render a row according to its data-locked state
  function renderRow(btn) {
    const locked = btn.dataset.locked === 'true';
    const tr     = btn.closest('tr');

    // disable inputs when locked, enable when unlocked
    tr.querySelectorAll('input.inline-input, select.inline-select')
      .forEach(el => el.disabled = locked);

    // update icon, classes, tooltip
    btn.textContent = locked ? '🔒' : '🔓';
    btn.classList.toggle('btn-secondary', locked);
    btn.classList.toggle('btn-warning',  !locked);
    btn.title = locked
      ? 'Click to unlock edits'
      : 'Click to lock edits';
  }

  // Flip the locked flag and re-render the row
  function toggleRow(btn) {
    btn.dataset.locked = (btn.dataset.locked === 'true' ? 'false' : 'true');
    renderRow(btn);
  }

  // Initialize all rows to locked, render them, and wire up the click handler
  lockBtns.forEach(btn => {
    btn.dataset.locked = 'true';   // start locked
    renderRow(btn);                // apply locked state
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      toggleRow(btn);
    });
  });
});
</script>




<script>
document.addEventListener('DOMContentLoaded', function(){
  document.querySelectorAll('.toggle-active').forEach(function(cb){
    cb.addEventListener('change', function(){
      var row = cb.closest('tr');
      var projectId = row.dataset.id;
      var isActive  = cb.checked ? 1 : 0;

      fetch('toggle_project_active.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify({ id: projectId, active: isActive })
      })
      .then(r => r.json())
      .then(data => {
        if (!data.success) {
          alert('Update failed');
          cb.checked = !cb.checked; // revert
        }
      })
      .catch(err => {
        console.error(err);
        alert('Network error');
        cb.checked = !cb.checked; // revert
      });
    });
  });
});
</script>


<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
</body>
</html>
