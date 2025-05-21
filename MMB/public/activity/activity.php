
<?php
// public/activity.php

// 1) Error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 2) Database connection
require_once __DIR__ . '/../../config/db_connect.php';
if (!isset($conn) || !$conn) {
    die('<p style="color:red;">Database connection missing!</p>');
}

// 3) Fetch user list for Full Name dropdown
$userRes = pg_query($conn, "SELECT id, full_name FROM users ORDER BY full_name");
$users   = $userRes ? pg_fetch_all($userRes) : [];

// 4) Fetch project addresses for Project/Site dropdown
$addrRes     = pg_query($conn, "SELECT id, address_line1 FROM project_addresses ORDER BY address_line1");
$addressList = $addrRes ? pg_fetch_all($addrRes) : [];



// 3) Shared header
require_once __DIR__ . '/../../includes/header.php';

// 4) Define tab queries and labels
$tabQueries = [
    'thisWeekTab'     => <<<'SQL'
WHERE cl.check_in_date >= date_trunc('week', current_date)
  AND cl.check_in_date < date_trunc('week', current_date) + INTERVAL '1 week'
SQL
    ,
    'lastWeekTab'     => <<<'SQL'
WHERE cl.check_in_date >= date_trunc('week', current_date) - INTERVAL '1 week'
  AND cl.check_in_date < date_trunc('week', current_date)
SQL
    ,
    'pastTwoWeeksTab' => <<<'SQL'
WHERE cl.check_in_date >= current_date - INTERVAL '2 weeks'
  AND cl.check_in_date < current_date
SQL
    ,
    'twoWeeksAgoTab'  => <<<'SQL'
WHERE cl.check_in_date >= date_trunc('week', current_date) - INTERVAL '2 weeks'
  AND cl.check_in_date < date_trunc('week', current_date) - INTERVAL '1 week'
SQL
];
$tabLabels = [
    'thisWeekTab'     => 'This Week',
    'lastWeekTab'     => 'Last Week',
    'pastTwoWeeksTab' => 'Past Two Weeks',
    'twoWeeksAgoTab'  => 'Two Weeks Ago',
];

// 5) Fetch project addresses for dropdown
$addrRes     = pg_query($conn, "SELECT id, address_line1 FROM project_addresses ORDER BY address_line1");
$addressList = $addrRes ? pg_fetch_all($addrRes) : [];
$addrJson    = json_encode($addressList);
?>


<h1 style="text-align:center; margin:1.5rem 0; font-size:1.75rem; font-weight:bold;">ACTIVITY</h1>
<div class="container page-content">
  
  <!-- Tabs -->

  <div class="tabs top-tabs" style="display:flex;justify-content:center;gap:1rem;margin-bottom:1rem;">
    <?php $first = true; foreach ($tabQueries as $tabId => $_): ?>
      <button class="tab-btn<?= $first ? ' active' : '' ?>" data-target="<?= $tabId ?>">
        <?= htmlspecialchars($tabLabels[$tabId]) ?>
      </button>
    <?php $first = false; endforeach; ?>
  </div>

  <!-- Toolbar -->

  <div class="activity-toolbar" style="display:flex;justify-content:space-between;align-items:center;margin:1rem 0;">
    <input type="search" id="activitySearch" placeholder="Type here to search"
           style="flex:1;padding:0.5rem;margin-right:1rem;border:1px solid #ccc;border-radius:4px;" />
    <button id="openAddModal" class="btn btn-primary">+ Add Activity</button>
  </div>

  <!-- Panels -->

  <div id="checkLogPanels">
    <?php foreach ($tabQueries as $tabId => $where):
      // Build SQL
      $sql = <<<'SQL'
SELECT
  u.id           AS user_id,
  u.full_name    AS user_name,
  to_char(
    make_interval(
      secs => SUM(
        CASE WHEN cl.verified THEN extract(epoch FROM cl.duration::interval) ELSE 0 END
      )
    ), 'HH24:MI'
  ) AS verified_sum,
  string_agg(
    cl.id || '|' || to_char(cl.check_in_date,'YYYY-MM-DD') || '|' ||
    to_char(cl.check_in_date,'MM/DD/YY') || '|' || coalesce(pa.address_line1,'—') || '|' ||
    pa.id || '|' || cl.check_in_clock || '–' || cl.check_out_clock || '|' ||
    cl.duration || '|' || cl.verified,
    E'\n' ORDER BY cl.check_in_date DESC, cl.check_in_clock DESC
  ) AS activity_entries
FROM check_log cl
JOIN users u ON cl.user_id = u.id
LEFT JOIN project_addresses pa ON cl.project_id = pa.id
SQL;
      $sql .= "\n{$where} AND cl.project_id IS NOT NULL GROUP BY u.id,u.full_name ORDER BY MAX(cl.check_in_date) DESC;";
      $res  = pg_query($conn, $sql);
      $rows = $res ? pg_fetch_all($res) : [];
    ?>
      <div id="<?= $tabId ?>" class="tab-panel" style="display:<?= ($tabId === 'thisWeekTab') ? 'block' : 'none' ?>;">
        <table class="styled-table">
          <thead>
            <tr>
              <th>Name</th><th>Verified</th><th>Activity</th><th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$rows): ?>
              <tr><td colspan="4" style="text-align:center;">No records.</td></tr>
            <?php else: foreach ($rows as $r):
  if (empty($r['activity_entries'])) continue;
  $entries = explode("\n", $r['activity_entries']);

              $records = [];
              foreach ($entries as $entry) {
                if (!trim($entry)) continue;
                list($logId,$isoDate,$dispDate,$address,$addrId,$timeRange,$duration,$ok) = explode('|',$entry,8);
                list($checkIn,$checkOut) = explode('–',$timeRange);
                $records[] = compact('logId','isoDate','dispDate','address','addrId','checkIn','checkOut','duration','ok');
              }
              $jsonRecs = htmlspecialchars(json_encode($records),ENT_QUOTES);
            ?>
              <tr>
                <td><?= htmlspecialchars($r['user_name']) ?></td>
                <td><?= htmlspecialchars($r['verified_sum']) ?></td>
                <td>
                  <?php foreach ($records as $c): ?>
                    <div style="display:flex;align-items:center;margin-bottom:0.4rem;">
                      <button type="button" class="verify-btn<?= $c['ok']==='true'?' active':'' ?>"
                              onclick="toggleVerify(<?= (int)$c['logId'] ?>, !this.classList.contains('active')); this.classList.toggle('active');"
                              style="background:none;border:none;font-size:1.2em;cursor:pointer;color:<?= $c['ok']==='true'?'#2ecc71':'#e74c3c' ?>;">
                        <?= $c['ok']==='true'?'✓':'✖' ?>
                      </button>
                      <span style="margin-left:0.5rem;">
                        <?= htmlspecialchars($c['dispDate']) ?> <?= htmlspecialchars($c['address']) ?> <?= htmlspecialchars($c['checkIn']) ?>–<?= htmlspecialchars($c['checkOut']) ?> (<?= htmlspecialchars($c['duration']) ?>)
                      </span>
                    </div>
                  <?php endforeach; ?>
                </td>
                <td style="white-space: nowrap;">
  <button class="btn-primary btn-modify" data-records='<?= $jsonRecs ?>' data-username='<?= htmlspecialchars($r['user_name'], ENT_QUOTES) ?>'>MODIFY</button>
</td>

              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- Activity Details Modal -->

  <div id="recordModal" class="modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);justify-content:center;align-items:center;">
    <div class="modal-content" style="background:#fff;border-radius:4px;width:auto;max-width:90%;max-height:80%;display:inline-block;margin:1rem;overflow:auto;">
      <div class="modal-header" style="display:flex;justify-content:space-between;align-items:center;padding:1rem;border-bottom:1px solid #ddd;">
        <h2 id="modalTitle">Activity Details</h2>
        <label style="margin-left:1rem;"><input type="checkbox" id="modalToggleAll"/> Verify All</label>
        <button id="modalSubmit" class="btn-primary" style="margin-left:1rem;">💾</button>
        <span class="modal-close" style="cursor:pointer;font-size:1.5rem;margin-left:1rem;">X</span>
      </div>
      <div class="modal-body" style="padding:1rem;">
        <table id="modalTable" class="styled-table" style="width:100%;border-collapse:collapse;">
          <thead>
            <tr>
              <th>Date</th><th>Address</th><th>Check-In</th><th>Check-Out</th><th>Duration</th><th>Verified</th><th>Lock</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Add Activity Modal -->

<div id="addActivityModal" class="modal-overlay">
  <div class="modal-box">
    <div class="modal-header">
      <h2 class="modal-title">Add New Activity</h2>
    </div>


<form id="addActivityForm" class="modal-content" method="POST" action="/api/add_activity.php">
  <div class="form-row">
    <label for="user_id">Full Name</label>
    <select name="user_id" class="form-control" required>
      <option value="">-- Select User --</option>
      <?php foreach ($users as $user): ?>
        <option value="<?= htmlspecialchars($user['id']) ?>"><?= htmlspecialchars($user['full_name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="form-row">
    <label for="project_id">Project/Site</label>
    <select name="project_id" class="form-control">
      <option value="">-- Select Project --</option>
      <?php foreach ($addressList as $addr): ?>
        <option value="<?= htmlspecialchars($addr['id']) ?>"><?= htmlspecialchars($addr['address_line1']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>



<div class="form-row" style="display: flex; gap: 1rem;">
  <div style="flex: 1;">
    <label for="check_in_time">Check-In Time</label>
    <input type="time" class="form-control" id="check_in_time" name="check_in_time">
  </div>
  <div style="flex: 1;">
    <label for="check_out_time">Check-Out Time</label>
    <input type="time" class="form-control" id="check_out_time" name="check_out_time">
  </div>
  <div class="form-row">
      <label for="date">Date</label>
      <input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>" required>
    </div>


</div>





   

 
<div class="form-row" style="display: flex; gap: 1rem;">
  <div style="flex: 1;">
 <label><input type="checkbox" name="verified" value="1"> Mark as Verified</label>
  </div>
  <div style="flex: 1;">
    <button class="btn btn-success" data-id="123">SAVE 💾</button>
  </div>
  <div class="form-row">
      <button type="button" class="btn btn-primary" onclick="closeAddModal()">CANCEL</button>
    </div>


</div>
 
  


  

  




   






</form>

  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', () => {
    const tabs         = document.querySelectorAll('.tab-btn');
    const panels       = document.querySelectorAll('.tab-panel');
    const panelsContainer = document.getElementById('checkLogPanels');
    const search       = document.getElementById('activitySearch');
    const modal        = document.getElementById('recordModal');
    const closeBtn     = modal.querySelector('.modal-close');
    const toggleAll    = document.getElementById('modalToggleAll');
    const submitBtn    = document.getElementById('modalSubmit');
    const addressOptions = JSON.parse('<?= $addrJson ?>');

    // Persist tab selection
    const savedTab   = localStorage.getItem('activeTab');
    const initial    = window.location.hash.substring(1);
    const defaultTab = savedTab || initial || 'thisWeekTab';
    function activateTab(id) {
      tabs.forEach(b => b.classList.toggle('active', b.dataset.target === id));
      panels.forEach(p => p.style.display = (p.id === id ? 'block' : 'none'));
      localStorage.setItem('activeTab', id);
      history.replaceState(null, '', '#' + id);
      search.dispatchEvent(new Event('input'));
    }
    tabs.forEach(btn => btn.addEventListener('click', () => activateTab(btn.dataset.target)));
    activateTab(defaultTab);

    // Search filter
    search.addEventListener('input', () => {
      const term = search.value.trim().toLowerCase();
      const active = document.querySelector('.tab-btn.active').dataset.target;
      document.querySelectorAll(`#${active} tbody tr`).forEach(tr => {
        tr.style.display = tr.textContent.toLowerCase().includes(term) ? '' : 'none';
      });
    });

    // Delegate open modal & populate to parent container
    panelsContainer.addEventListener('click', async (e) => {
      if (!e.target.classList.contains('btn-modify')) return;
      const btn       = e.target;
      const records   = JSON.parse(btn.dataset.records);
      const tbody     = modal.querySelector('#modalTable tbody');
      modal.querySelector('#modalTitle').textContent = btn.dataset.username;
      tbody.innerHTML = '';

      // Build rows
      records.forEach(r => {
  const addressSelect = `<select class="modal-address" data-id="${r.logId}">` +
    addressOptions.map(opt =>
      `<option value="${opt.id}" ${opt.id == r.addrId ? 'selected' : ''}>${opt.address_line1}</option>`
    ).join('') + `</select>`;
  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td><input type="date" class="modal-date" data-id="${r.logId}" value="${r.isoDate}"></td>
    <td>${addressSelect}</td>
    <td><input type="time" class="modal-in" data-id="${r.logId}" value="${r.checkIn}"></td>
    <td><input type="time" class="modal-out" data-id="${r.logId}" value="${r.checkOut}"></td>
    <td>${r.duration}</td>
    <td><input type="checkbox" class="modal-verify" data-id="${r.logId}" ${r.ok === 'true' ? 'checked' : ''}></td>
    <td style="white-space:nowrap;">
      <button class="btn-secondary modal-row-lock" data-id="${r.logId}" title="UnLock/Lock">
        🔒
      </button>
      <button class="btn-danger modal-row-delete" data-id="${r.logId}" title="Delete" style="margin-left:0.5rem;">
        🗑️
      </button>
    </td>
  `;
  tbody.appendChild(tr);
});

      // Verify All logic
      toggleAll.checked = Array.from(modal.querySelectorAll('.modal-verify')).every(cb => cb.checked);
      toggleAll.addEventListener('change', () => {
        modal.querySelectorAll('.modal-verify').forEach(cb => cb.checked = toggleAll.checked);
      });
      modal.querySelectorAll('.modal-verify').forEach(cb => {
        cb.addEventListener('change', () => {
          toggleAll.checked = Array.from(modal.querySelectorAll('.modal-verify')).every(inner => inner.checked);
        });
      });

      // Row Lock logic
modal.querySelectorAll('.modal-row-lock').forEach(btn => {
  const row = btn.closest('tr');

  // Initially lock everything
  row.querySelectorAll('input, select').forEach(el => el.disabled = true);
  const deleteBtn = row.querySelector('.modal-row-delete');
  if (deleteBtn) deleteBtn.disabled = true;
  

  btn.addEventListener('click', () => {
    const isLocked = btn.textContent.includes('🔒'); // Currently locked

    // If trying to unlock and another row is already unlocked, block it
    if (isLocked) {
      const anyUnlocked = Array.from(document.querySelectorAll('.modal-row-lock'))
        .some(b => b.textContent.includes('🔓'));
      if (anyUnlocked) {
        alert('You can only unlock one record at a time.');
        return;
      }
    }

    row.querySelectorAll('input, select').forEach(el => el.disabled = !isLocked);
    if (deleteBtn) deleteBtn.disabled = !isLocked;

    btn.innerHTML = isLocked
      ? '🔓'
      : '🔒';
  });
});




     modal.querySelectorAll('.modal-row-delete').forEach(btn => {
  btn.addEventListener('click', async () => {
    const id = btn.dataset.id;
    if (!confirm('Are you sure you want to delete this record?')) return;
    try {
      const res = await fetch(`/api/delete_activity.php?id=${id}`, { method: 'DELETE' });
      const json = await res.json();
      if (json.success) {
        btn.closest('tr').remove();
      } else {
        alert('Delete failed: ' + (json.error || 'Unknown error'));
      }
    } catch (err) {
      console.error('Delete error:', err);
    }
  });
});



      // Show modal
      modal.style.display = 'flex';
    });

    // Submit and refresh panel
    submitBtn.addEventListener('click', async () => {
      const payload = [];
      modal.querySelectorAll('#modalTable tbody tr').forEach(tr => {
        const id = tr.querySelector('.modal-date').dataset.id;
        payload.push({
          id,
          date:       tr.querySelector('.modal-date').value,
          project_id: tr.querySelector('.modal-address').value,
          check_in:   tr.querySelector('.modal-in').value,
          check_out:  tr.querySelector('.modal-out').value,
          verified:   tr.querySelector('.modal-verify').checked
        });
      });
      // Log payload for debugging
      console.log('Payload for update:', payload);
      try {
        const response = await fetch('/api/update_multiple.php', {
          method: 'POST',
          headers: {'Content-Type': 'application/json'},
          body: JSON.stringify(payload)
        });
        console.log('Fetch response status:', response.status);
        const json = await response.json();
        console.log('Fetch JSON:', json);
      } catch (err) {
        console.error('Fetch error:', err);
      }
      modal.style.display = 'none';
      // Refresh only active panel
      const activeTab = document.querySelector('.tab-btn.active').dataset.target;
      const html      = await fetch(window.location.href).then(r => r.text());
      const doc       = new DOMParser().parseFromString(html, 'text/html');
      document.getElementById(activeTab).innerHTML = doc.getElementById(activeTab).innerHTML;
      search.dispatchEvent(new Event('input'));
    });

    // Close modal

    closeBtn.addEventListener('click', () => modal.style.display = 'none');
    modal.addEventListener('click', e => { if (!e.target.closest('.modal-content')) modal.style.display = 'none'; });
  });



document.getElementById('openAddModal').addEventListener('click', () => {
  document.getElementById('addActivityModal').style.display = 'flex';
});

document.getElementById('closeAddModal').addEventListener('click', () => {
  document.getElementById('addActivityModal').style.display = 'none';
});

document.getElementById('cancelAddModal').addEventListener('click', () => {
  document.getElementById('addActivityModal').style.display = 'none';
});

window.addEventListener('click', function (e) {
  const modal = document.getElementById('addActivityModal');
  if (e.target === modal) {
    modal.style.display = 'none';
  }
});

document.getElementById('fullNameSelect').addEventListener('change', function () {
    const selectedUserId = this.value;
    document.getElementById('userIdInput').value = selectedUserId;
});







function closeAddModal() {
  document.getElementById('addActivityModal').style.display = 'none';
}








</script>

<style>
/* Fullscreen darkened overlay for modal */
#addActivityModal.modal-overlay {
  position: fixed;
  inset: 0;
  display: none;
  justify-content: center;
  align-items: center;
  background: rgba(0, 0, 0, 0.5);
  z-index: 99999 !important;
}

/* Modal box itself */
#addActivityModal .modal-box {
  background: white;
  padding: 1.5rem;
  border-radius: 8px;
  box-shadow: 0 10px 30px rgba(0,0,0,0.3);
  width: 100%;
  max-width: 500px;
  z-index: 100000 !important;
  display: flex;
  flex-direction: column;
}











</style>








<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
