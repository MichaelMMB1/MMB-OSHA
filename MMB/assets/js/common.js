// common.js

// Modal utilities
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

// Wait until DOM is fully loaded
document.addEventListener('DOMContentLoaded', () => 
  // 1) Column resizing
  document.querySelectorAll('.styled-table').forEach(makeTableResizable);

  // 2) Tab switching
  document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
      document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
      btn.classList.add('active');
      const target = btn.dataset.target;
      const panel = document.getElementById(target);
      if (!panel) return console.warn(`No tab panel with id="${target}"`);
      panel.classList.add('active');
    });
  });

<form id="checkoutForm" method="POST" action="activity/checklog/process_checkout.php">
  <input type="hidden" name="checkin_id" value="<?=$checkin_id?>">
  <div class="button-group" style="margin-top:1rem;">
    <button type="button" class="btn btn-secondary" onclick="closeCheckoutModal()">Cancel</button>
    <button type="submit" class="btn btn-primary">Confirm</button>
  </div>
</form>


// Enable column resizing on tables
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

// common.js

// ── Active/Inactive dropdown (with debugging & delegation) ────────────────
document.addEventListener('change', function(e) {
  if (!e.target.matches('.active-select')) return;

  const sel    = e.target;
  const tr     = sel.closest('tr');
  const id     = tr.dataset.id;
  const active = sel.value;    // "active" or "inactive"

  console.log('Active‑select changed:', { id, active });

  // build x‑www‑form‑urlencoded body
  const params = new URLSearchParams({ id, active });

  // absolute path to your endpoint
  const url = window.location.origin + '/MMB/public/projects/toggle_project_active.php';

  fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: params.toString()
  })
  .then(r => {
    console.log('Response status:', r.status, r.statusText);
    return r.json();
  })
  .then(json => {
    console.log('Response JSON:', json);
    if (!json.success) {
      alert('Save failed: ' + (json.error||'unknown'));
      // revert
      sel.value = (active === 'active' ? 'inactive' : 'active');
    }
  })
  .catch(err => {
    console.error('Fetch error:', err);
    alert('Network error');
    sel.value = (active === 'active' ? 'inactive' : 'active');
  });
});






// AJAX form helper for modal-based forms
function hookAjaxForm(form, modalId) {
  form.addEventListener('submit', e => {
    e.preventDefault();
    fetch(form.action, {
      method: form.method,
      body: new FormData(form),
      credentials: 'same-origin'
    })
      .then(response => {
        if (!response.ok) return response.text().then(text => Promise.reject(text));
        return response.text();
      })
      .then(() => {
        if (typeof closeModal === 'function') closeModal(modalId);
        location.reload(); // or update UI dynamically
      })
      .catch(error => {
        console.error('AJAX form error:', error);
        alert('Operation failed. Please try again.');
      });
  });
}
