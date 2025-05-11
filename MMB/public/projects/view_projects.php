<?php
// public/projects/view_projects.php

// 1) Session check
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// 2) Active nav
$current = 'projects';

// 3) Bootstrap
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/header.php';

// 4) Handle filter
$q = $_GET['q'] ?? '';
$where = [];
$params = [];
if ($q !== '') {
    $where[] = 'project_name LIKE ?';
    $params[] = "%{$q}%";
}

// 5) Build & execute query
// select all columns (including active)
$sql = "SELECT id, project_name, active, address_line1, address_line2,
              city, state, zip_code, country, created_at
       FROM project_addresses";
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY project_name';
$stmt = $mysqli->prepare($sql);
if ($params) {
    $stmt->bind_param(str_repeat('s', count($params)), ...$params);
}
$stmt->execute();
$projects = $stmt->get_result();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Projects – MMB Contractors Portal</title>
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

<div class="container edge-to-edge">
  <form id="filterForm" method="get" action="view_projects.php" class="filter-bar">
    <div class="filter-group search">
      <label for="q">Search</label>
      <input type="text"
             id="q" name="q"
             class="search-input"
             placeholder="Search projects…"
             value="<?= htmlspecialchars($q) ?>"
             oninput="this.form.submit()">
    </div>
    <div class="button-group">
      <button type="button" class="btn btn-primary" onclick="openAddProjectModal()">
        Add Project
      </button>
      <button type="button" class="btn btn-secondary"
              onclick="window.location='view_projects.php'">
        Reset
      </button>
    </div>
  </form>

  <div class="table-panel panel-wrapper">
    <table id="projectsTable" class="styled-table" data-sort-dir="asc">
      <thead>
        <tr>
          <th onclick="sortTable(0)">ID</th>
          <th onclick="sortTable(1)">Project Name</th>
          <th onclick="sortTable(2)">Address</th>
          <th onclick="sortTable(3)">City</th>
          <th onclick="sortTable(4)">State</th>
          <th onclick="sortTable(5)">ZIP</th>
          <th onclick="sortTable(6)">Country</th>
          <th onclick="sortTable(7)">Created</th>
                   <th onclick="sortTable(8)">Active</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($projects && $projects->num_rows): ?>
          <?php while ($row = $projects->fetch_assoc()): ?>
            <tr>
              <td><?= (int)$row['id'] ?></td>
              <td><?= htmlspecialchars($row['project_name']) ?></td>
              <td>
                <?= htmlspecialchars($row['address_line1']) ?><br>
                <?= htmlspecialchars($row['address_line2']) ?>
              </td>

              
              <td><?= htmlspecialchars($row['city']) ?></td>
              <td><?= htmlspecialchars($row['state']) ?></td>
              <td><?= htmlspecialchars($row['zip_code']) ?></td>
              <td><?= htmlspecialchars($row['country']) ?></td>
              <td><?= date('M d, Y', strtotime($row['created_at'])) ?></td>
                      <td>
                <select class="active-select">
                  <option value="active"   <?= $row['active'] === 'active'   ? 'selected' : '' ?>>Active</option>
                  <option value="inactive" <?= $row['active'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
              </td>
            </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr><td colspan="8">No projects found.</td></tr>
        <?php endif; ?>
      </tbody>
      
    </table>
  </div>
</div>

<!-- Add Project Modal -->
<div id="addProjectModal" class="modal-overlay">
  <div class="modal-content">
    <h3>Add New Project</h3>
    <form method="POST" action="add_project.php">
      <label>Project Name</label>
      <input type="text" name="project_name" class="modal-input" required>
      <label>Address Line 1</label>
      <input type="text" name="address_line1" class="modal-input" required>
      <label>Address Line 2</label>
      <input type="text" name="address_line2" class="modal-input">
      <label>City</label>
      <input type="text" name="city" class="modal-input" required>
      <label>State</label>
      <input type="text" name="state" class="modal-input" required>
      <label>ZIP Code</label>
      <input type="text" name="zip_code" class="modal-input" required>
      <label>Country</label>
      <input type="text" name="country" class="modal-input" required>
      <div style="display:flex;justify-content:flex-end;gap:0.5rem;margin-top:1rem;">
        <button type="button" class="btn btn-secondary" onclick="closeAddProjectModal()">Cancel</button>
        <button type="submit" class="btn btn-primary">Create</button>
      </div>

    </form>
  </div>
</div>

<script>
// open/close Add Project modal
function openAddProjectModal() {
  document.getElementById('addProjectModal').style.display = 'flex';
}
function closeAddProjectModal() {
  document.getElementById('addProjectModal').style.display = 'none';
}
window.addEventListener('click', e => {
  if (e.target.id === 'addProjectModal') closeAddProjectModal();
});

// auto-submit filter form on change
document.addEventListener('DOMContentLoaded', () => {
  const filterForm = document.getElementById('filterForm');
  filterForm.querySelectorAll('input').forEach(i =>
    i.addEventListener('change', () => filterForm.submit())
  );
});

// column sorter
function sortTable(col) {
  const tbl = document.getElementById('projectsTable');
  const asc = tbl.getAttribute('data-sort-dir') !== 'asc';
  const rows = Array.from(tbl.tBodies[0].rows);
  rows.sort((a, b) => {
    const x = a.cells[col].textContent.trim();
    const y = b.cells[col].textContent.trim();
    return asc ? x.localeCompare(y) : y.localeCompare(x);
  });
  rows.forEach(r => tbl.tBodies[0].appendChild(r));
  tbl.setAttribute('data-sort-dir', asc ? 'asc' : 'desc');
}

// column resizer
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.styled-table th').forEach(th => {
    const resizer = document.createElement('div');
    resizer.classList.add('th-resizer');
    th.appendChild(resizer);

    resizer.addEventListener('mousedown', e => {
      e.preventDefault();
      const startX = e.clientX, startW = th.offsetWidth;
      function onMove(e) {
        th.style.width = `${startW + e.clientX - startX}px`;
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
</script>





<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
