// common.js

// ── Modal utilities ─────────────────────────────────────────────────────────
function openModal(id) {
  const modal = document.getElementById(id);
  if (!modal) return console.warn(`No modal with id="${id}"`);
  modal.classList.add('active');
}

function closeModal(id) {
  const modal = document.getElementById(id);
  if (!modal) return console.warn(`No modal with id="${id}"`);
  modal.classList.remove('active');
}

// ── Column resizing ────────────────────────────────────────────────────────
function makeTableResizable(table) {
  table.classList.add('resizable-table');
  table.querySelectorAll('th').forEach(th => {
    if (th.querySelector('.th-resizer')) return;
    const resizer = document.createElement('div');
    resizer.classList.add('th-resizer');
    th.append(resizer);

    let startX, startWidth;
    resizer.addEventListener('mousedown', e => {
      e.preventDefault();
      startX = e.clientX;
      startWidth = th.offsetWidth;

      function onMouseMove(moveEvent) {
        const newWidth = startWidth + (moveEvent.clientX - startX);
        if (newWidth > 40) th.style.width = `${newWidth}px`;
      }

      function onMouseUp() {
        document.removeEventListener('mousemove', onMouseMove);
        document.removeEventListener('mouseup', onMouseUp);
      }

      document.addEventListener('mousemove', onMouseMove);
      document.addEventListener('mouseup', onMouseUp);
    });
  });
}

// ── Tab switching ──────────────────────────────────────────────────────────
function initTabs() {
  document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
      document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
      btn.classList.add('active');
      const panel = document.getElementById(btn.dataset.target);
      if (!panel) return console.warn(`No tab panel with id="${btn.dataset.target}"`);
      panel.classList.add('active');
    });
  });
}

// ── Active/Inactive dropdown (projects) ───────────────────────────────────
function initActiveSelect() {
  document.addEventListener('change', e => {
    if (!e.target.matches('.active-select')) return;

    const sel    = e.target;
    const tr     = sel.closest('tr');
    const id     = tr?.dataset.id;
    const active = sel.value;    // "active" or "inactive"
    const url    = `${window.location.origin}/MMB/public/projects/toggle_project_active.php`;
    const params = new URLSearchParams({ id, active });

    console.log('Saving active status:', { id, active });
    fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: params.toString()
    })
    .then(r => r.json()
      .then(json => ({ status: r.status, json }))
    )
    .then(({ status, json }) => {
      console.log('Response:', status, json);
      if (!json.success) {
        alert(`Save failed: ${json.error || 'Unknown error'}`);
        sel.value = active === 'active' ? 'inactive' : 'active';
      }
    })
    .catch(err => {
      console.error('Fetch error:', err);
      alert('Network error');
      sel.value = active === 'active' ? 'inactive' : 'active';
    });
  });
}

// ── Check-in / Check-out modals ───────────────────────────────────────────
function openCheckinModal()   { openModal('checkinModal'); }
function closeCheckinModal()  { closeModal('checkinModal'); }
function openCheckoutModal()  { openModal('checkoutModal'); }
function closeCheckoutModal() { closeModal('checkoutModal'); }

// ── AJAX form helper for modal forms ──────────────────────────────────────
function hookAjaxForm(formSelector, modalId) {
  const form = document.querySelector(formSelector);
  if (!form) return console.warn(`No form matching "${formSelector}"`);
  form.addEventListener('submit', e => {
    e.preventDefault();
    fetch(form.action, {
      method: form.method,
      body: new FormData(form),
      credentials: 'same-origin'
    })
    .then(resp => {
      if (!resp.ok) return resp.text().then(txt => Promise.reject(txt));
      return resp.text();
    })
    .then(() => {
      closeModal(modalId);
      location.reload();
    })
    .catch(err => {
      console.error('AJAX form error:', err);
      alert('Operation failed. Please try again.');
    });
  });
}

// ── Initialize everything once DOM is ready ────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
  // 1) Make all styled tables resizable
  document.querySelectorAll('.styled-table').forEach(makeTableResizable);

  // 2) Wire up tabs
  initTabs();

  // 3) Wire up Active/Inactive selects
  initActiveSelect();

  // 4) Hook AJAX forms
  hookAjaxForm('#checkoutForm', 'checkoutModal');
  hookAjaxForm('#checkinForm',  'checkinModal');

  // ── Dropdown & Drawer Logic ──────────────────────────────────────────────
  const profileIcon = document.getElementById("profileIcon");
  const dropdown    = document.getElementById("dropdown");
  const drawer      = document.getElementById("profileDrawer");
  const backdrop    = document.getElementById("drawerBackdrop");
  const btnClose    = document.getElementById("closeDrawer");

  // Toggle small dropdown
  if (profileIcon && dropdown) {
    profileIcon.addEventListener("click", function (e) {
      e.stopPropagation();
      dropdown.style.display =
        dropdown.style.display === "block" ? "none" : "block";
    });

    window.addEventListener("click", function (e) {
      if (!dropdown.contains(e.target)) {
        dropdown.style.display = "none";
      }
    });
  }

  // Drawer open/close
  window.openProfileDrawer = function () {
    if (drawer && backdrop) {
      drawer.classList.add("open");
      backdrop.classList.add("show");
    }
  };

  window.closeProfileDrawer = function () {
    if (drawer && backdrop) {
      drawer.classList.remove("open");
      backdrop.classList.remove("show");
    }
  };

  // Close via × button
  if (btnClose) {
    btnClose.addEventListener("click", closeProfileDrawer);
  }

  // Close drawer when clicking on backdrop
  if (backdrop) {
    backdrop.addEventListener("click", closeProfileDrawer);
  }

  // Close on Escape key
  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") closeProfileDrawer();
  });
});

const modal = document.getElementById('verifyModal');
modal.style.display = 'flex';



function filterActivityTable() {
  const term = document.getElementById('activitySearch').value.toLowerCase();
  document.querySelectorAll('#activityTable tbody tr').forEach(row => {
    const text = row.textContent.toLowerCase();
    row.style.display = text.includes(term) ? '' : 'none';
  });
}
window.filterActivityTable = filterActivityTable;


function openVerifyModal(userId, tab, from, to, name) {
  document.getElementById('verifyModalTitle').textContent = `Verify Records for ${name}`;
  // show overlay
  const modal = document.getElementById('verifyModal');
  modal.style.display = 'flex';        // <-- use flex so it centers
  // load detail
  fetch(`activity_detail.php?user_id=${userId}&tab=${tab}&from=${from}&to=${to}`)
    .then(r => r.text())
    .then(html => document.getElementById('modalBody').innerHTML = html)
    .catch(() => document.getElementById('modalBody').innerHTML = 'Error loading records');
}
function closeVerifyModal() {
  document.getElementById('verifyModal').style.display = 'none';
}




document.addEventListener("DOMContentLoaded", function () {
  // … all your existing dropdown/drawer/tab/init code …

  // ── Make all your styled tables resizable ────────────────────────────
  document.querySelectorAll('.styled-table').forEach(table => {
    try {
      makeTableResizable(table);
    } catch (e) {
      console.warn('Could not make table resizable:', e);
    }
  });
});


// Filters rows based on the search input (matches any cell text)
function filterTable() {
  const term = document.getElementById('searchInput').value.toLowerCase();
  document.querySelectorAll('.tab-panel table tbody tr').forEach(row => {
    const text = row.innerText.toLowerCase();
    row.style.display = text.includes(term) ? '' : 'none';
  });
}

// Reloads the current panels via AJAX without a full page refresh
function refreshTable() {
  fetch(window.location.href)
    .then(resp => resp.text())
    .then(html => {
      const tmp = document.createElement('div');
      tmp.innerHTML = html;
      const newPanels = tmp.querySelector('#checkLogPanels');
      document.getElementById('checkLogPanels').innerHTML = newPanels.innerHTML;
      // Reset event listeners (rebind toggles & tab clicks if needed)
      bindToggleAndTabs();
    })
    .catch(() => alert('Failed to refresh data'));
}

function bindToggleAndTabs() {
  // Re-bind verify toggles
  document.querySelectorAll('.verify-btn').forEach(btn => {
    btn.onclick = function() {
      const state = !btn.classList.contains('active');
      btn.classList.toggle('active');
      const logId = Number(btn.getAttribute('data-log-id'));
      toggleVerify(logId, state);
    };
  });
  // Re-bind tab switching
  document.querySelectorAll('.inner-tabs .tab-btn').forEach(b => {
    b.onclick = () => {
      document.querySelectorAll('.tab-btn').forEach(x => x.classList.remove('active'));
      b.classList.add('active');
      document.querySelectorAll('.tab-panel').forEach(p => p.style.display = 'none');
      document.getElementById(b.dataset.target).style.display = 'block';
    };
  });
}

// Initial bind
bindToggleAndTabs();
