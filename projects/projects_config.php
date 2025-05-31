<?php
// public/projects/projects.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/header.php';

// Fetch users for dropdowns
$pmRes = pg_query($conn, "SELECT id, full_name FROM users WHERE LOWER(role) = 'project manager' ORDER BY full_name");
$pmUsers = $pmRes ? pg_fetch_all($pmRes) : [];

$suRes = pg_query($conn, "SELECT id, full_name FROM users WHERE LOWER(role) = 'superintendent' ORDER BY full_name");
$suUsers = $suRes ? pg_fetch_all($suRes) : [];

// Fetch existing projects
$projRes = pg_query($conn, <<<SQL
SELECT p.id, p.name, p.address,
       p.project_manager_id, p.site_superintendent_id,
       upm.full_name AS pm_name, usu.full_name AS su_name
  FROM projects p
  LEFT JOIN users upm ON p.project_manager_id = upm.id
  LEFT JOIN users usu ON p.site_superintendent_id = usu.id
 ORDER BY p.name
SQL
);
$projects = $projRes ? pg_fetch_all($projRes) : [];
?>
<div class="container page-content">
  <h1>Projects</h1>
  <button id="addProjectBtn" class="btn btn-primary">Add New Project</button>
  <div class="table-responsive" style="margin-top:1rem;">
    <table class="styled-table">
      <thead>
        <tr>
          <th>Name</th>
          <th>Address</th>
          <th>Project Manager</th>
          <th>Site Superintendent</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($projects as $proj): ?>
        <tr>
          <td><?php echo htmlspecialchars($proj['name']); ?></td>
          <td><?php echo htmlspecialchars($proj['address']); ?></td>
          <td>
            <select data-project-id="<?php echo $proj['id']; ?>" class="pm-select form-control">
              <option value="">— Select —</option>
              <?php foreach ($pmUsers as $u): ?>
              <option value="<?php echo $u['id']; ?>" <?php if ($u['id'] == $proj['project_manager_id']) echo 'selected'; ?>><?php echo htmlspecialchars($u['full_name']); ?></option>
              <?php endforeach; ?>
            </select>
          </td>
          <td>
            <select data-project-id="<?php echo $proj['id']; ?>" class="su-select form-control">
              <option value="">— Select —</option>
              <?php foreach ($suUsers as $u): ?>
              <option value="<?php echo $u['id']; ?>" <?php if ($u['id'] == $proj['site_superintendent_id']) echo 'selected'; ?>><?php echo htmlspecialchars($u['full_name']); ?></option>
              <?php endforeach; ?>
            </select>
          </td>
          <td>
            <button class="lock-btn btn btn-light"><i class="icon-lock"></i></button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add Project Modal -->
<div id="addProjectModal" class="modal" style="display:none;">
  <div class="modal-content">
    <div class="modal-header">
      <h5 class="modal-title">Add New Project</h5>
      <button type="button" class="close" onclick="closeAddProjectModal()">&times;</button>
    </div>
    <div class="modal-body">
      <form id="addProjectForm">
        <div class="form-group">
          <label for="projectName">Project Name</label>
          <input type="text" id="projectName" name="name" class="form-control" required>
        </div>
        <div class="form-group">
          <label for="projectAddress">Address</label>
          <input type="text" id="projectAddress" name="address" class="form-control" required>
        </div>
        <div class="form-group">
          <label for="projectManagerSelect">Project Manager</label>
          <select id="projectManagerSelect" name="project_manager_id" class="form-control">
            <option value="">— Select —</option>
            <?php foreach ($pmUsers as $u): ?>
            <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['full_name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label for="siteSuperintendentSelect">Site Superintendent</label>
          <select id="siteSuperintendentSelect" name="site_superintendent_id" class="form-control">
            <option value="">— Select —</option>
            <?php foreach ($suUsers as $u): ?>
            <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['full_name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button id="saveNewProjectBtn" class="btn btn-success">Save</button>
      <button class="btn btn-secondary" onclick="closeAddProjectModal()">Cancel</button>
    </div>
  </div>
</div>

<script>
// Open modal
document.getElementById('addProjectBtn').addEventListener('click', () => {
  document.getElementById('addProjectModal').style.display = 'block';
});

// Close modal
function closeAddProjectModal() {
  document.getElementById('addProjectModal').style.display = 'none';
}

// Save new project
document.getElementById('saveNewProjectBtn').addEventListener('click', () => {
  const form = document.getElementById('addProjectForm');
  const data = new URLSearchParams(new FormData(form)).toString();

  fetch('/public/projects/add_project.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded'
    },
    body: data
  })
    .then(res => res.json())
    .then(res => {
      if (res.success) {
        closeAddProjectModal();
        location.reload();
      } else {
        alert('Error: ' + res.error);
      }
    });
});
</script>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>
