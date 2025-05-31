a// public/activity.js

// toggles the “verified” status via AJAX
function toggleVerify(logId, verified) {
  fetch('/api/verify_all.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id: logId, verified })
  })
  .then(r => r.json())
  .then(json => {
    if (!json.success) alert('Verify failed: ' + (json.error || ''));
  })
  .catch(err => {
    console.error(err);
    alert('Error toggling verify');
  });
}

document.addEventListener('DOMContentLoaded', () => {
  const tabs            = document.querySelectorAll('.tab-btn');
  const panelsContainer = document.getElementById('checkLogPanels');
  const search          = document.getElementById('activitySearch');
  const modal           = document.getElementById('recordModal');
  const closeBtn        = modal.querySelector('.modal-close');
  const toggleAll       = document.getElementById('modalToggleAll');
  const submitBtn       = document.getElementById('modalSubmit');
  const addressOptions  = JSON.parse('<?= $addrJson ?>');

  // — Tabs —
  const savedTab   = localStorage.getItem('activeTab');
  const initial    = window.location.hash.substring(1);
  const defaultTab = savedTab || initial || 'thisWeekTab';
  function activateTab(id) {
    tabs.forEach(b => b.classList.toggle('active', b.dataset.target === id));
    document.querySelectorAll('.tab-panel')
            .forEach(p => p.style.display = (p.id === id ? 'block' : 'none'));
    localStorage.setItem('activeTab', id);
    history.replaceState(null, '', '#' + id);
    search.dispatchEvent(new Event('input'));
  }
  tabs.forEach(btn => btn.addEventListener('click', () => activateTab(btn.dataset.target)));
  activateTab(defaultTab);

  // — Search Filter —
  search.addEventListener('input', () => {
    const term   = search.value.trim().toLowerCase();
    const active = document.querySelector('.tab-btn.active').dataset.target;
    document.querySelectorAll(`#${active} tbody tr`)
      .forEach(tr => tr.style.display =
        tr.textContent.toLowerCase().includes(term) ? '' : 'none'
      );
  });

  // — Open “Modify” modal —
  panelsContainer.addEventListener('click', e => {
    if (!e.target.classList.contains('btn-modify')) return;
    const btn     = e.target;
    const records = JSON.parse(btn.dataset.records);
    const tbody   = modal.querySelector('#modalTable tbody');
    modal.querySelector('#modalTitle').textContent = btn.dataset.username;
    tbody.innerHTML = '';

    // build each row
    records.forEach(r => {
      const opts = addressOptions.map(o =>
        `<option value="${o.id}"${o.id==r.addrId?' selected':''}>${o.address_line1}</option>`
      ).join('');
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td><input type="date"   class="modal-date"    data-id="${r.logId}"  value="${r.isoDate}"></td>
        <td><select class="modal-address" data-id="${r.logId}">${opts}</select></td>
        <td><input type="time"   class="modal-in"      data-id="${r.logId}"  value="${r.checkIn}"></td>
        <td><input type="time"   class="modal-out"     data-id="${r.logId}"  value="${r.checkOut}"></td>
        <td>${r.duration}</td>
        <td><input type="checkbox" class="modal-verify" data-id="${r.logId}" ${r.ok==='true'?'checked':''}></td>
        <td style="white-space:nowrap;">
          <button class="btn-secondary modal-row-lock" data-id="${r.logId}">🔒</button>
          <button class="btn-danger   modal-row-delete" data-id="${r.logId}">🗑️</button>
        </td>
      `;
      tbody.appendChild(tr);
    });

    // verify-all checkbox
    function updateToggleAll() {
      toggleAll.checked = Array.from(modal.querySelectorAll('.modal-verify')).every(cb => cb.checked);
    }
    updateToggleAll();
    toggleAll.onchange = () => modal.querySelectorAll('.modal-verify')
                                   .forEach(cb => cb.checked = toggleAll.checked);
    modal.querySelectorAll('.modal-verify').forEach(cb => cb.onchange = updateToggleAll);

    // lock/unlock rows
    modal.querySelectorAll('.modal-row-lock').forEach(btn => {
      const tr = btn.closest('tr');
      tr.querySelectorAll('input, select').forEach(el => el.disabled = true);
      const del = tr.querySelector('.modal-row-delete');
      del.disabled = true;
      btn.onclick = () => {
        const unlocking = btn.textContent === '🔒';
        if (unlocking && Array.from(modal.querySelectorAll('.modal-row-lock')).some(b => b.textContent==='🔓')) {
          return alert('Only one row can be unlocked at a time.');
        }
        tr.querySelectorAll('input, select').forEach(el => el.disabled = !unlocking);
        del.disabled = !unlocking;
        btn.textContent = unlocking ? '🔓' : '🔒';
      };
    });

    // delete rows
    modal.querySelectorAll('.modal-row-delete').forEach(btn => {
      btn.onclick = async () => {
        if (!confirm('Delete this record?')) return;
        const res  = await fetch(`/api/delete_activity.php?id=${btn.dataset.id}`, { method: 'DELETE' });
        const json = await res.json();
        if (json.success) btn.closest('tr').remove();
        else alert('Delete failed: ' + (json.error||'Unknown'));
      };
    });

    modal.style.display = 'flex';
  });

  // — Submit all edits —
  submitBtn.onclick = async () => {
    const payload = Array.from(modal.querySelectorAll('#modalTable tbody tr')).map(tr => ({
      id:         tr.querySelector('.modal-date').dataset.id,
      date:       tr.querySelector('.modal-date').value,
      project_id: tr.querySelector('.modal-address').value,
      check_in:   tr.querySelector('.modal-in').value,
      check_out:  tr.querySelector('.modal-out').value,
      verified:   tr.querySelector('.modal-verify').checked
    }));
    await fetch('/api/update_multiple.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify(payload)
    });
    modal.style.display = 'none';
    // refresh current tab only
    const active = document.querySelector('.tab-btn.active').dataset.target;
    const html   = await fetch(window.location.href).then(r => r.text());
    const doc    = new DOMParser().parseFromString(html, 'text/html');
    document.getElementById(active).innerHTML = doc.getElementById(active).innerHTML;
    search.dispatchEvent(new Event('input'));
  };

  // — Close modal if clicking outside or on “X” —
  closeBtn.onclick = () => modal.style.display = 'none';
  modal.onclick    = e => { if (!e.target.closest('.modal-content')) modal.style.display = 'none'; };

  // — “Add Activity” modal handlers (unchanged) —
  document.getElementById('openAddModal').onclick = () =>
    document.getElementById('addActivityModal').style.display = 'flex';
  window.addEventListener('click', e => {
    if (e.target.id==='addActivityModal') {
      document.getElementById('addActivityModal').style.display = 'none';
    }
  });
});
