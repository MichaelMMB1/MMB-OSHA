<?php
// public/projects/projects.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/header.php';

// 1) Fetch all projects, joining users for both roles
$resProjects = pg_query($conn, "
  SELECT
    pa.id,
    pa.project_name,
    pa.address_line1,
    pa.project_manager_id,
    pm.full_name   AS project_manager_name,
    pa.site_superintendent,
    ss.full_name   AS site_superintendent_name
  FROM project_addresses pa
  LEFT JOIN users pm 
    ON pm.id = pa.project_manager_id
  LEFT JOIN users ss 
    ON ss.id = NULLIF(pa.site_superintendent, '')::integer
  ORDER BY pa.project_name
");
$projects = $resProjects ? pg_fetch_all($resProjects) : [];
?>

<h1 style="text-align:center; margin:1.5rem 0; font-size:1.75rem; font-weight:bold;">PROJECTS</h1>

<div class="container page-content">
  <button id="addProjectBtn" class="btn-primary" style="margin-bottom:1rem;">Add New Project</button>

  <table class="styled-table">
    <thead>
      <tr>
        <th>Name</th>
        <th>Address</th>
        <th>Project Manager</th>
        <th>Site Superintendent</th>
        <th>Edit</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($projects as $proj): ?>
      <tr
        data-id="<?= (int)$proj['id'] ?>"
        data-pm-id="<?= htmlspecialchars($proj['project_manager_id'] ?? '') ?>"
        data-ss-id="<?= htmlspecialchars($proj['site_superintendent'] ?? '') ?>"
      >
        <!-- Project Name -->
        <td contenteditable="false" class="editable" data-field="project_name">
          <?= htmlspecialchars($proj['project_name']) ?>
        </td>

        <!-- Address -->
        <td contenteditable="false" class="editable" data-field="address_line1">
          <?= htmlspecialchars($proj['address_line1']) ?>
        </td>

        <!-- Project Manager -->
        <td>
          <select class="pm-select" data-field="project_manager_id" disabled>
            <option value="">
              <?= $proj['project_manager_name']
                   ? htmlspecialchars($proj['project_manager_name'])
                   : '— Select PM —' ?>
            </option>
          </select>
        </td>

        <!-- Site Superintendent -->
        <td>
          <select class="ss-select" data-field="site_superintendent" disabled>
            <option value="">
              <?= $proj['site_superintendent_name']
                   ? htmlspecialchars($proj['site_superintendent_name'])
                   : '— Select Superintendent —' ?>
            </option>
          </select>
        </td>

        <td>
          <button class="lock-btn" title="Unlock Row">🔒</button>
          <button class="save-btn" style="display:none;" title="Save Row">💾</button>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  let pmList = [], ssList = [];

  // Fetch the current users in each role
  async function fetchLists() {
    try {
      const [pmRes, ssRes] = await Promise.all([
        fetch('/api/get_users_by_role.php?role=' + encodeURIComponent('Project Manager')),
        fetch('/api/get_users_by_role.php?role=' + encodeURIComponent('Superintendent'))
      ]);
      pmList = await pmRes.json();
      ssList = await ssRes.json();
    } catch (e) {
      console.error('Error loading role lists', e);
    }
  }

  // Helper to populate a select
  function populate(selectEl, list, current) {
    selectEl.innerHTML = '<option value="">— Select —</option>';
    list.forEach(u => {
      const o = document.createElement('option');
      o.value = u.id;
      o.textContent = u.full_name;
      if (String(u.id) === String(current)) o.selected = true;
      selectEl.append(o);
    });
  }

  // Wire up each row
  function wire() {
    document.querySelectorAll('tr[data-id]').forEach(row => {
      const pmSel = row.querySelector('.pm-select');
      const ssSel = row.querySelector('.ss-select');
      const origPm = row.dataset.pmId;
      const origSs = row.dataset.ssId;

      // Pre-fill dropdowns
      populate(pmSel, pmList, origPm);
      populate(ssSel, ssList, origSs);

      const lockBtn = row.querySelector('.lock-btn');
      const saveBtn = row.querySelector('.save-btn');
      const fields  = row.querySelectorAll('[contenteditable], select');

      lockBtn.addEventListener('click', () => {
        const open = lockBtn.textContent === '🔒';
        lockBtn.textContent = open ? '🔓' : '🔒';
        lockBtn.title = open ? 'Lock Row' : 'Unlock Row';
        fields.forEach(f => {
          if (f.hasAttribute('contenteditable')) f.setAttribute('contenteditable', open);
          else f.disabled = !open;
        });
        saveBtn.style.display = open ? 'inline-block' : 'none';
      });

      saveBtn.addEventListener('click', async () => {
        const id = row.dataset.id;
        const updates = [];

        // Collect text edits
        row.querySelectorAll('.editable').forEach(c => {
          updates.push({ field: c.dataset.field, value: c.textContent.trim() });
        });
        // Collect selects
        updates.push({ field: pmSel.dataset.field, value: pmSel.value });
        updates.push({ field: ssSel.dataset.field, value: ssSel.value });

        // Send each update
        for (const u of updates) {
          const res = await fetch('/api/update_project_field.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, field: u.field, value: u.value })
          });
          const json = await res.json();
          if (!json.success) return alert(`Error updating ${u.field}: ${json.error}`);
        }

        // Re‐lock
        fields.forEach(f => {
          if (f.hasAttribute('contenteditable')) f.setAttribute('contenteditable','false');
          else f.disabled = true;
        });
        lockBtn.textContent = '🔒';
        saveBtn.style.display = 'none';
        alert('Saved!');
      });
    });
  }

  // Initialize
  fetchLists().then(wire);
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
