document.addEventListener('DOMContentLoaded', () => {
  const tabs = document.querySelectorAll('.tab-btn');
  const panels = document.querySelectorAll('.tab-panel');
  const search = document.getElementById('activitySearch');
  const modal = document.getElementById('recordModal');
  const closeBtn = modal.querySelector('.modal-close');
  const toggleAll = document.getElementById('modalToggleAll');
  const lockBtn = document.getElementById('modalLock');
  const submitBtn = document.getElementById('modalSubmit');

  // Restore active tab
  const savedTab = localStorage.getItem('activeTab');
  const initialHash = window.location.hash.substring(1);
  const defaultTab = savedTab || initialHash || 'lastWeekTab';
  function activateTab(tabId) {
    tabs.forEach(b => b.classList.toggle('active', b.dataset.target === tabId));
    panels.forEach(p => p.style.display = p.id === tabId ? 'block' : 'none');
    localStorage.setItem('activeTab', tabId);
    history.replaceState(null, '', '#' + tabId);
    search.dispatchEvent(new Event('input'));
  }
  tabs.forEach(btn => btn.addEventListener('click', () => activateTab(btn.dataset.target)));
  activateTab(defaultTab);

  // Search filter
  search.addEventListener('input', () => {
    const term = search.value.trim().toLowerCase();
    const active = document.querySelector('.tab-btn.active').dataset.target;
    document.getElementById(active).querySelectorAll('tbody tr').forEach(tr => {
      tr.style.display = tr.textContent.toLowerCase().includes(term) ? '' : 'none';
    });
  });

  // Toggle verify from table row
  window.toggleVerify = async function (id, verified) {
    try {
      const res = await fetch('/public/activity/activity_toggle.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id, verified })
      });
      const result = await res.json();
      const btn = document.getElementById('verify-' + id);
      if (result.success && btn) {
        btn.classList.toggle('active', verified);
        btn.style.color = verified ? '#2ecc71' : '#e74c3c';
        btn.innerText = verified ? '✓' : '✖';
      }
    } catch (err) {
      alert('Error updating record');
    }
  };

  // Open modal
  document.querySelectorAll('.btn-modify').forEach(btn => btn.addEventListener('click', () => {
    const records = JSON.parse(btn.dataset.records);
    const tbody = modal.querySelector('#modalTable tbody');
    modal.querySelector('#modalTitle').textContent = btn.dataset.username + ' Details';
    tbody.innerHTML = '';
    records.forEach(r => {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td><input type="date" class="modal-date" data-id="${r.logId}" value="${r.isoDate}"></td>
        <td>${r.address}</td>
        <td>${r.checkIn}</td>
        <td>${r.checkOut}</td>
        <td>${r.duration}</td>
        <td><input type="checkbox" class="modal-verify" data-id="${r.logId}" ${r.ok === 'true' ? 'checked' : ''}></td>
      `;
      tbody.appendChild(tr);
    });
    toggleAll.checked = Array.from(modal.querySelectorAll('.modal-verify')).every(cb => cb.checked);
    modal.style.display = 'flex';
  }));

  // Lock/Unlock
  lockBtn.addEventListener('click', () => {
    const locked = lockBtn.textContent === 'Lock';
    lockBtn.textContent = locked ? 'Unlock' : 'Lock';
    modal.querySelectorAll('input').forEach(el => el.disabled = locked);
  });

  // Toggle All
  toggleAll.addEventListener('change', () => {
    const checked = toggleAll.checked;
    modal.querySelectorAll('.modal-verify').forEach(cb => cb.checked = checked);
  });

  // Submit changes and refresh the tab
  submitBtn.addEventListener('click', async () => {
    modal.style.display = 'none';
    const activeTab = document.querySelector('.tab-btn.active').dataset.target;
    const html = await fetch(window.location.href).then(r => r.text());
    const doc = new DOMParser().parseFromString(html, 'text/html');
    document.getElementById(activeTab).innerHTML = doc.getElementById(activeTab).innerHTML;
    search.dispatchEvent(new Event('input'));
    attachInlineHandlers();
  });

  // Close modal
  closeBtn.addEventListener('click', () => modal.style.display = 'none');
  modal.addEventListener('click', e => {
    if (!e.target.closest('.modal-content')) modal.style.display = 'none';
  });

  // Rebind inline verify buttons after refresh
  function attachInlineHandlers() {
    document.querySelectorAll('.verify-btn').forEach(btn => {
      const id = btn.id.replace('verify-', '');
      btn.onclick = () => toggleVerify(id, !btn.classList.contains('active'));
    });
  }

  attachInlineHandlers();
});

