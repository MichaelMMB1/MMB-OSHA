<?php
// public/activity.php

// 1) Error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 2) Database connection
require_once __DIR__ . '/../../../config/db_connect.php';
if (!isset($conn) || !$conn) {
    die('<p style="color:red;">Database connection missing!</p>');
}

// fetch all project_addresses with their PM colors
$pmColorMap = [];
$res = pg_query($conn, "
  SELECT pa.id AS project_id,
         u.color      AS pm_color
    FROM project_addresses pa
    JOIN users u
      ON u.id = pa.project_manager_id
");
while ($row = pg_fetch_assoc($res)) {
  $pmColorMap[(int)$row['project_id']] = $row['pm_color'] ?: '#ccc';
}


/**
 * Returns black or white text for best contrast on $hex background.
 */
function contrastColor(string $hex): string {
    $h = ltrim($hex, '#');
    $r = hexdec(substr($h, 0, 2));
    $g = hexdec(substr($h, 2, 2));
    $b = hexdec(substr($h, 4, 2));
    // brightness per YIQ formula
    $yiq = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;
    return ($yiq >= 128) ? '#000' : '#fff';
}




// 3) Determine which log table to use
$logTable = 'activities_log';

// 4) Fetch user list for Full Name dropdown
$userRes = pg_query($conn, "SELECT id, full_name FROM users ORDER BY full_name");
$users   = $userRes ? pg_fetch_all($userRes) : [];

// 5) Fetch project addresses for Project/Site dropdown
$addrRes     = pg_query($conn, "SELECT id, address_line1 FROM project_addresses ORDER BY address_line1");
$addressList = $addrRes ? pg_fetch_all($addrRes) : [];



// 7) Define tab queries / labels
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

// 8) Prepare address JSON
$addrJson = json_encode($addressList);
?>

<h1 style="text-align:center; margin:1.5rem 0; font-size:1.75rem; font-weight:bold;">ACTIVITY</h1>
<div class="page-content">
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
           style="flex:1;padding:0.5rem;margin-right:1rem;border:1px solid #1C262B;border-radius:4px; w" />
    <button id="openAddModal" class="btn btn-primary">Add Activity</button>
  </div>

  <!-- Panels -->
  <div id="checkLogPanels">
    <?php foreach ($tabQueries as $tabId => $where):
      $sql = "SELECT
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
FROM " . pg_escape_identifier($conn, $logTable) . " cl
JOIN users u ON cl.user_id = u.id
LEFT JOIN project_addresses pa ON cl.project_id = pa.id
{$where} AND cl.project_id IS NOT NULL
GROUP BY u.id, u.full_name
ORDER BY MAX(cl.check_in_date) DESC;";
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
              $raw = $r['activity_entries'] ?? '';
$entries = explode("\n", $raw);

              $records = [];
              foreach ($entries as $entry) {
                if (!trim($entry)) continue;

                // explode out the raw pieces
                list(
                  $logId,
                  $isoDate,
                  $dispDate,
                  $address,
                  $addrId,
                  $timeRange,
                  $rawDuration,
                  $ok
                ) = explode('|', $entry, 8);

                // split check-in/out and drop fractional seconds
                list($rawIn, $rawOut) = explode('–', $timeRange);
                $checkIn  = substr($rawIn,  0, 8);  // e.g. "14:58:05"
                $checkOut = substr($rawOut, 0, 8);  // e.g. "15:09:14"

                // drop fractional seconds from duration too
                list($duration,) = explode('.', $rawDuration);

                $records[] = compact(
                  'logId','isoDate','dispDate','address','addrId',
                  'checkIn','checkOut','duration','ok'
                );
              }

              $jsonRecs = htmlspecialchars(json_encode($records),ENT_QUOTES);
            ?>
              <tr>
                <td><?= htmlspecialchars($r['user_name']) ?></td>
                <td><?= htmlspecialchars($r['verified_sum']) ?></td>
                <td>
                  <?php foreach ($records as $c): ?>
    <div style="display:flex;align-items:center;margin-bottom:0.4rem;">
<button type="button"
        class="verify-btn<?= $c['ok']==='true' ? ' active' : '' ?>">
  <?= $c['ok']==='true' ? '✓' : '✖' ?>
</button>

      <?php
        // lookup PM color
        $bg       = $pmColorMap[(int)$c['addrId']] ?? '#ccc';
        // pick black or white text
        $fg       = contrastColor($bg);
        // your label
        $text     = sprintf(
          '%s %s %s–%s (%s)',
          $c['dispDate'],
          $c['address'],
          $c['checkIn'],
          $c['checkOut'],
          $c['duration']
        );
      ?>

      <span class="tag"
            style="
              background-color: <?= htmlspecialchars($bg, ENT_QUOTES) ?>;
              color:            <?= htmlspecialchars($fg, ENT_QUOTES) ?>;
              margin-left:      0.5rem;
            ">
        <?= htmlspecialchars($text, ENT_QUOTES) ?>
      </span>
    </div>
  <?php endforeach; ?>

                </td>
                <td style="white-space: nowrap;">
<button type="button" class="btn-primary btn-modify"
        data-records='<?= $jsonRecs ?>'
        data-username='<?= htmlspecialchars($r['user_name'], ENT_QUOTES) ?>'>
  MODIFY
</button>

                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- Activity Details Modal -->
  <div id="recordModal" class="modal" style="display:none;position:fixed;inset:0;
       background:rgba(0,0,0,0.5);justify-content:center;align-items:center;">
    <div class="modal-content" style="background:#fff;border-radius:4px;
         width:auto;max-width:90%;max-height:80%;display:inline-block;margin:1rem;overflow:auto;">
      <div class="modal-header" style="display:flex;justify-content:space-between;
           align-items:center;padding:1rem;border-bottom:1px solid #ddd;">
        <h2 id="modalTitle">Activity Details</h2>
        <label style="margin-left:1rem;">
          <input type="checkbox" id="modalToggleAll"/> Verify All
        </label>
        <button id="modalSubmit" class="btn-primary" style="margin-left:1rem;">💾</button>
      </div>
      <div class="modal-body" style="padding:1rem;">
        <table id="modalTable" class="styled-table" style="width:100%;border-collapse:collapse;">
          <thead>
            <tr>
              <th>Date</th><th>Address</th><th>Check-In</th><th>Check-Out</th>
              <th>Duration</th><th>Verified</th><th>Lock</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Add Activity Modal -->
  <div id="addActivityModal" class="modal-overlay" style="display:none;
       justify-content:center;align-items:center;position:fixed;inset:0;
       background:rgba(0,0,0,0.5);z-index:1000;">
    <div class="modal-box" style="background:white;padding:1.5rem;
         border-radius:8px;box-shadow:0 10px 30px rgba(0,0,0,0.3);
         width:100%;max-width:600px;">
      <div class="modal-header" style="display:flex;justify-content:space-between;
           align-items:center;">
        <h2 style="margin:0;font-size:1.5rem;">Add New Activity</h2>
        
              <div class="modal-footer" style="display:flex;justify-content:flex-end;

           padding-top:1rem;">
        <button id="saveActivityBtn" class="btn btn-success">Save</button>
        <button class="btn btn-secondary" style="margin-left:0.5rem;"
                onclick="closeAddModal()">Cancel</button>
      </div>
      </div>

      <div class="modal-body" style="margin-top:1rem;">
        <!-- dynamic week range -->
        <div id="activityDateRange" style="margin-bottom:1rem;color:#555;
             font-size:0.95rem;"></div>

        <!-- Check-In / Check-Out -->
        <div style="display:flex;gap:1rem;">
          <div style="flex:1;">
            <label for="addCheckIn">Check-In Time</label>
            <input type="time" id="addCheckIn" class="form-control" />
          </div>
          <div style="flex:1;">
            <label for="addCheckOut">Check-Out Time</label>
            <input type="time" id="addCheckOut" class="form-control" />
          </div>
        </div>

        <!-- Project & User selects -->
        <div style="display:flex;gap:1rem;margin-top:1.5rem;">
          <div style="flex:1;">
            <label for="addProjectSelect">Project</label>
            <select id="addProjectSelect" class="form-control">
              <option value="">-- Select project --</option>
              <?php foreach ($addressList as $addr): ?>
                <option value="<?= htmlspecialchars($addr['id']) ?>">
                  <?= htmlspecialchars($addr['address_line1']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          <div style="flex:1;">
            <label for="modal-user-select">Users</label>
            <select id="modal-user-select" class="user-select">
              <option value="">-- Select users --</option>
              <?php foreach ($users as $u): ?>
                <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['full_name']) ?></option>
              <?php endforeach; ?>
            </select>
            <div id="modal-tag-container" class="tag-container" style="margin-top:0.5rem;"></div>
          </div>
        </div>

        <!-- Day-of-week checkboxes -->
        <div style="display:flex;gap:1rem;margin-top:1.5rem;">
          <div style="flex:1;background:#e8f4fd;padding:1rem;border-radius:4px;">
            <strong>Weekdays</strong><br/>
            <label><input type="checkbox" id="chkWeekdaysAll"> Mon – Fri</label><br/>
            <label><input type="checkbox" class="chkDay" data-day="1"> Monday</label><br/>
            <label><input type="checkbox" class="chkDay" data-day="2"> Tuesday</label><br/>
            <label><input type="checkbox" class="chkDay" data-day="3"> Wednesday</label><br/>
            <label><input type="checkbox" class="chkDay" data-day="4"> Thursday</label><br/>
            <label><input type="checkbox" class="chkDay" data-day="5"> Friday</label>

          <div style="flex:1;background:#fff7e6;padding:1rem;border-radius:4px;">
            <strong>Weekend</strong><br/>
            <label><input type="checkbox" class="chkDay" data-day="6"> Saturday</label><br/>
            <label><input type="checkbox" class="chkDay" data-day="0"> Sunday</label>
          </div>
        </div>
      </div>


    </div>
  </div>
</div>




<script>
document.addEventListener('DOMContentLoaded', () => {
  // Group‐toggle checkboxes
  const chkWeekdaysAll = document.getElementById('chkWeekdaysAll');
  const chkWeekendAll  = document.getElementById('chkWeekendAll');

  // All individual day checkboxes
  const allDays = Array.from(document.querySelectorAll('.chkDay'));

  // Helper: filter by data-day
  const byDayNums = nums => allDays.filter(cb => nums.includes(parseInt(cb.dataset.day, 10)));

  const weekdays = byDayNums([1,2,3,4,5]);
  const weekend  = byDayNums([6,0]);

  // 1) Master → children
  chkWeekdaysAll.addEventListener('change', () => {
    weekdays.forEach(cb => cb.checked = chkWeekdaysAll.checked);
  });
  chkWeekendAll.addEventListener('change', () => {
    weekend.forEach(cb => cb.checked = chkWeekendAll.checked);
  });

  

  // 2) Children → master (break chain if any unchecked)
  weekdays.forEach(cb => {
    cb.addEventListener('change', () => {
      chkWeekdaysAll.checked = weekdays.every(c => c.checked);
    });
  });
  weekend.forEach(cb => {
    cb.addEventListener('change', () => {
      chkWeekendAll.checked = weekend.every(c => c.checked);
    });
  });
});
</script>










<script>
  
(function(){
  const tabs     = document.querySelectorAll('.tab-btn');
  const panels   = document.getElementById('checkLogPanels');
  const search   = document.getElementById('activitySearch');
  const modal    = document.getElementById('recordModal');
  const tbody    = modal.querySelector('tbody');
  const saveAll  = document.getElementById('modalSubmit');
  const addrOpt  = JSON.parse('<?= $addrJson ?>');
  const toggleAll= document.getElementById('modalToggleAll');

  // ── Tabs ──
  const start = window.location.hash.slice(1) || localStorage.getItem('activeTab') || 'thisWeekTab';
  function activate(id) {
    tabs.forEach(b=>b.classList.toggle('active', b.dataset.target===id));
    document.querySelectorAll('.tab-panel').forEach(p=>p.style.display=p.id===id?'block':'none');
    localStorage.setItem('activeTab', id);
    history.replaceState(null, '', '#'+id);
    search.dispatchEvent(new Event('input'));
  }
  tabs.forEach(b=>b.onclick=()=>activate(b.dataset.target)); activate(start);

  // ── Search filter ──
  search.oninput = ()=>{
    const term = search.value.trim().toLowerCase();
    const activeId = document.querySelector('.tab-btn.active').dataset.target;
    document.querySelectorAll(`#${activeId} tbody tr`).forEach(r=>{
      r.style.display = r.textContent.toLowerCase().includes(term)?'':'none';
    });
  };

  // ── Populate detail modal ──
  panels.onclick = e => {
    if (!e.target.classList.contains('btn-modify')) return;
    const recs = JSON.parse(e.target.dataset.records);
    tbody.innerHTML = '';
    document.getElementById('modalTitle').textContent = e.target.dataset.username;
    recs.forEach(r=>{
      const opts = addrOpt.map(o=>
        `<option value="${o.id}"${o.id==r.addrId?' selected':''}>${o.address_line1}</option>`
      ).join('');
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td><input type="date" class="modal-date" data-id="${r.logId}" value="${r.isoDate}"></td>
        <td><select class="modal-address" data-id="${r.logId}">${opts}</select></td>
        <td><input type="time" class="modal-in" data-id="${r.logId}" value="${r.checkIn}"></td>
        <td><input type="time" class="modal-out" data-id="${r.logId}" value="${r.checkOut}"></td>
        <td>${r.duration}</td>
        <td><input type="checkbox" class="modal-verify" data-id="${r.logId}" ${r.ok==='true'?'checked':''}></td>
        <td><button class="modal-row-delete" data-id="${r.logId}">🗑️</button></td>
      `;
      tbody.append(tr);
    });
    toggleAll.onchange = ()=>modal.querySelectorAll('.modal-verify').forEach(cb=>cb.checked=toggleAll.checked);
    modal.querySelectorAll('.modal-verify').forEach(cb=>cb.onchange=_=>toggleAll.checked=
      Array.from(modal.querySelectorAll('.modal-verify')).every(c=>c.checked)
    );
    modal.style.display = 'flex';
  };

  // ── Mark for delete ──
  tbody.onclick = e => {
    if (!e.target.classList.contains('modal-row-delete')) return;
    e.target.closest('tr').classList.toggle('to-delete');
  };

  // ── Save All: confirm, updates, deletes ──
 // ── Save All: confirm, updates, deletes ──
  saveAll.onclick = async () => {
  if (!confirm('Are you sure you want to save these changes?')) return;

  // 1) collect edits
  const changes = Array.from(tbody.querySelectorAll('tr')).map(tr => ({
    id:         tr.querySelector('.modal-date').dataset.id,
    date:       tr.querySelector('.modal-date').value,
    project_id: tr.querySelector('.modal-address').value,
    check_in:   tr.querySelector('.modal-in').value,
    check_out:  tr.querySelector('.modal-out').value,
    verified:   tr.querySelector('.modal-verify').checked
  }));

  // 2) send updates
  await fetch('/api/update_multiple.php', {
    method: 'POST',
    headers: { 'Content-Type':'application/json' },
    body: JSON.stringify(changes)
  });

  
  // 3) gather marked-for-delete IDs
  const toDelete = Array.from(
    tbody.querySelectorAll('tr.to-delete')
  ).map(tr => tr.querySelector('.modal-row-delete').dataset.id);

  // 4) perform deletes with error handling & POST fallback
  await Promise.all(toDelete.map(id =>
    fetch(`/api/delete_activity.php?id=${id}`, { method: 'DELETE' })
      .then(r => r.json())
      .then(json => {
        if (!json.success) {
          console.warn('DELETE failed for', id, json);
          // fallback to POST if your endpoint only reads POST bodies
          return fetch(`/api/delete_activity.php?id=${id}`, {
            method: 'POST',
            headers:{ 'Content-Type':'application/json' },
            body: JSON.stringify({ id })
          })
          .then(r2 => r2.json())
          .then(fb => {
            if (!fb.success) throw new Error(`Fallback delete failed for ${id}`);
          });
        }
      })
      .catch(err => console.error('Error deleting', id, err))
  ));

  // 5) confirmation
  alert('Changes saved successfully.');

  // 6) close and reload
  modal.style.display = 'none';
  window.location.reload();
};



  // ── Close detail modal on outside click ──
  modal.onclick = e => {
    if (!e.target.closest('.modal-content')) {
      modal.style.display = 'none';
    }
  };

  // ── Add-Activity modal wiring ──
  const openAdd   = document.getElementById('openAddModal');
  const addModal  = document.getElementById('addActivityModal');
  const saveAdd   = document.getElementById('saveActivityBtn');
  const chkAll    = document.getElementById('chkWeekdaysAll');

  openAdd.onclick = () => addModal.style.display = 'flex';
  window.onclick = e => { if (e.target === addModal) addModal.style.display = 'none'; };

  // weekday/all toggle
  chkAll.onchange = () => {
    document.querySelectorAll('.chkDay')
            .forEach(cb => { if (['1','2','3','4','5'].includes(cb.dataset.day)) cb.checked = chkAll.checked; });
  };

  saveAdd.onclick = async () => {
    const checkIn  = document.getElementById('addCheckIn').value;
    const checkOut = document.getElementById('addCheckOut').value;
    const projectId= document.getElementById('addProjectSelect').value;
    const userIds  = Array.from(document.querySelectorAll('#modal-tag-container input[name="user_ids[]"]'))
                         .map(i => i.value);
    const days     = Array.from(document.querySelectorAll('.chkDay:checked'))
                         .map(cb => cb.dataset.day);
    if (!checkIn || !checkOut || !projectId || !userIds.length || !days.length) {
      return alert('Please fill all fields, select users and days.');
    }
    // build payload
    const payload = [];
    userIds.forEach(uid => days.forEach(d => payload.push({
      user_id: uid,
      project_id: projectId,
      check_in: checkIn,
      check_out: checkOut,
      day_of_week: d
    })));
    // send add
    const res = await fetch('/api/add_multiple_records.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const json = await res.json();
    if (json.success) {
      addModal.style.display = 'none';
      document.querySelector('.tab-btn.active').click();
    } else {
      alert('Add failed: ' + (json.error||'Unknown'));
    }
  };
})();
</script>


<style>
  /* Red line across entire row for pending delete */
  #modalTable tbody tr.to-delete td { position: relative; }
  #modalTable tbody tr.to-delete td::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 0;
    right: 0;
    border-top: 2px solid red;
    pointer-events: none;
  }

  .activity-toolbar {
  /* make it as wide as the viewport */
  max-width: 100%;
  /* pull its origin to the true center of the viewport */
  position: relative;
  left: 50%;
  transform: translateX(-50%);
  /* your spacing & layout */

  box-sizing: border-box;
  display: flex;
  justify-content: space-between;
  align-items: center;

}

</style>


