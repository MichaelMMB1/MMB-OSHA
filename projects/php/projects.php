<?php
// public/projects.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

// 1) Include header & DB
require_once __DIR__ . '/../../../config/db_connect.php';

// 2) Fetch PM & SU lists for selects
$resPM   = pg_query($conn, "
    SELECT id, full_name
      FROM users
     WHERE LOWER(role) = 'project manager'
     ORDER BY full_name
");
$pmUsers = $resPM ? pg_fetch_all($resPM) : [];

$resSU   = pg_query($conn, "
    SELECT id, full_name
      FROM users
     WHERE LOWER(role) = 'superintendent'
     ORDER BY full_name
");
$suUsers = $resSU ? pg_fetch_all($resSU) : [];

// 3) JSON POST handler for modal
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
 && stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') === 0
) {
    header('Content-Type: application/json; charset=utf-8');
    ini_set('display_errors', 0);
    error_reporting(0);

    $in = json_decode(file_get_contents('php://input'), true) ?: [];

    if (
        empty($in['id'])
     && !empty($in['project_name'])
     && !empty($in['address_line1'])
    ) {
        $name    = pg_escape_literal($conn, $in['project_name']);
        $address = pg_escape_literal($conn, $in['address_line1']);
        $status  = pg_escape_literal($conn, $in['status'] ?? '');

        $pm = (strlen($in['project_manager_id'] ?? '') > 0)
            ? intval($in['project_manager_id']) : 'NULL';
        $su = (strlen($in['superintendent_id'] ?? '') > 0)
            ? intval($in['superintendent_id']) : 'NULL';

        $sql = "
          INSERT INTO project_addresses
            (project_name, address_line1, status, project_manager_id, superintendent_id)
          VALUES
            ($name, $address, $status, $pm, $su)
          RETURNING id
        ";
        $res = pg_query($conn, $sql);
        if ($res && ($row = pg_fetch_assoc($res))) {
            echo json_encode(['success' => true, 'id' => (int)$row['id']]);
        } else {
            echo json_encode(['success' => false, 'error' => pg_last_error($conn)]);
        }
    }
    exit;
}

// 4) Normal page load: grab existing projects
$resProjects = pg_query($conn, "
  SELECT
    pa.id,
    pa.project_name,
    pa.address_line1,
    pa.status,
    pa.project_manager_id,
    pa.superintendent_id,
    pm.full_name AS pm_name,
    su.full_name AS su_name
  FROM project_addresses pa
  LEFT JOIN users pm ON pa.project_manager_id = pm.id
  LEFT JOIN users su ON pa.superintendent_id  = su.id
  ORDER BY pa.project_name
");
$projects      = $resProjects ? pg_fetch_all($resProjects) : [];
$statusOptions = ['Active', 'Planning', 'On Hold', 'Completed'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Projects</title>
  <link rel="stylesheet" href="/public/style.css">
</head>
<body>
  <div class="page-content">
    <h1>Projects</h1>
    <button id="addProjectBtn" class="btn btn-primary">Add New Project</button>

    <table class="styled-table" id="projectsTable">
      <thead>
        <tr>
          <th>Name</th>
          <th>Address</th>
          <th>Status</th>
          <th>Project Manager</th>
          <th>Site Superintendent</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($projects as $p): ?>
        <tr data-project-id="<?= htmlspecialchars($p['id'], ENT_QUOTES) ?>">
          <td><?= htmlspecialchars($p['project_name'], ENT_QUOTES) ?></td>
          <td><?= htmlspecialchars($p['address_line1'], ENT_QUOTES) ?></td>
          <td>
            <select class="user-select">
              <?php foreach ($statusOptions as $opt): ?>
                <option value="<?= $opt ?>" <?= $p['status'] === $opt ? 'selected' : '' ?>>
                  <?= $opt ?>
                </option>
              <?php endforeach; ?>
            </select>
          </td>
          <td>
            <select class="user-select">
              <option value="">— Select PM —</option>
              <?php foreach ($pmUsers as $u): ?>
                <option value="<?= $u['id'] ?>" <?= $u['id'] == $p['project_manager_id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($u['full_name'], ENT_QUOTES) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </td>
          <td>
            <select class="user-select">
              <option value="">— Select Superintendent —</option>
              <?php foreach ($suUsers as $u): ?>
                <option value="<?= $u['id'] ?>" <?= $u['id'] == $p['superintendent_id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($u['full_name'], ENT_QUOTES) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- Add New Project Modal -->
  <div id="addProjectModal" class="modal-overlay">
    <div class="modal-box">
      <div class="modal-header">
        <h2>Add New Project</h2>
        <button id="closeAddModal" class="btn-cancel">×</button>
      </div>
      <form id="addProjectForm">
        <div class="modal-body">
          <div class="form-row">
            <label for="projectName">Project Name</label>
            <input id="projectName" name="project_name" type="text" required class="form-control"/>
          </div>
          <div class="form-row" style="margin-top:1rem;">
            <label for="projectAddress">Address</label>
            <input id="projectAddress" name="address_line1" type="text" required class="form-control"/>
          </div>
          <div class="form-row" style="margin-top:1rem;">
            <label for="projectStatus">Status</label>
            <select id="projectStatus" name="status" class="form-control">
              <?php foreach ($statusOptions as $opt): ?>
                <option value="<?= $opt ?>"><?= $opt ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-row" style="margin-top:1rem;">
            <label for="projectManager">Project Manager</label>
            <select type="button"
                    id="projectManager"
                    name="project_manager_id"
                    required
                    class="form-control">
              <option value="">— Select PM —</option>
              <?php foreach ($pmUsers as $u): ?>
                <option value="<?= $u['id'] ?>">
                  <?= htmlspecialchars($u['full_name'], ENT_QUOTES) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-row" style="margin-top:1rem;">
            <label for="siteSuper">Site Superintendent</label>
            <select type="button"
                    id="siteSuper"
                    name="superintendent_id"
                    required
                    class="form-control">
              <option value="">— Select Superintendent —</option>
              <?php foreach ($suUsers as $u): ?>
                <option value="<?= $u['id'] ?>">
                  <?= htmlspecialchars($u['full_name'], ENT_QUOTES) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="modal-actions" style="justify-content:flex-end; margin-top:1.5rem; border-top:1px solid #eee; padding-top:1rem;">
          <!-- Notice type="button" here so native form-submit never fires -->
          <button type="button" id="saveProjectBtn" class="btn-primary">Save</button>
          <button type="button" id="cancelProjectBtn" class="btn-cancel" style="margin-left:.5rem;">Cancel</button>
        </div>
      </form>
    </div>
  </div>

  <style>
    /* backdrop */
    #addProjectModal {
      position: fixed;
      top: 0; left: 0; width: 100%; height: 100%;
      background-color: rgba(0,0,0,0.6);
      display: none; align-items: center; justify-content: center;
      z-index: 10000;
    }
    /* show when needed */
    #addProjectModal.show { display: flex; }
    /* modal box */
    #addProjectModal .modal-box {
      background: #fff;
      border-radius: 8px;
      max-width: 600px; width: 90%;
      padding: 1.5rem;
      box-shadow: 0 8px 24px rgba(0,0,0,0.2);
    }
  </style>

  <script>
  document.addEventListener('DOMContentLoaded', () => {
    const modal    = document.getElementById('addProjectModal');
    const form     = document.getElementById('addProjectForm');
    const saveBtn  = document.getElementById('saveProjectBtn');
    const endpoint = window.location.href;

    function hideModal() {
      modal.classList.remove('show');
      modal.style.display = 'none';
    }

    document.getElementById('addProjectBtn')
      .addEventListener('click', () => {
        modal.style.display = 'flex';
        modal.classList.add('show');
      });

    document.getElementById('closeAddModal')
      .addEventListener('click', hideModal);

    document.getElementById('cancelProjectBtn')
      .addEventListener('click', hideModal);

    saveBtn.addEventListener('click', e => {
      e.preventDefault();
      if (!form.checkValidity()) return form.reportValidity();

      // close immediately
      hideModal();
      saveBtn.disabled = true; // prevent double clicks

      const payload = {
        project_name:       form.project_name.value,
        address_line1:      form.address_line1.value,
        status:             form.status.value,
        project_manager_id: form.project_manager_id.value,
        superintendent_id:  form.superintendent_id.value
      };

      fetch(endpoint, {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify(payload)
      })
      .then(r => r.json())
      .then(() => {
        // always reload so new project shows
        location.reload();
      })
      .catch(() => {
        // even on error, reload to sync state
        location.reload();
      });
    });
  });
  </script>

  <?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
</body>
</html>
