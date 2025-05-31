// public/projects/projects_script.js
'use strict';
(function(){
  console.log('▶ projects_script.js loaded', window.PROJECTS);

  if (!window.PROJECTS) {
    console.error('PROJECTS config missing');
    return;
  }
  const { getUsersByRoleUrl, updateProjectFieldUrl } = window.PROJECTS;

  const $  = sel => document.querySelector(sel);
  const $$ = sel => Array.from(document.querySelectorAll(sel));

  let pmList = [], ssList = [];

  // 1) fetch role‐specific user lists
  async function fetchLists() {
    try {
      const [pmRes, ssRes] = await Promise.all([
        fetch(`${getUsersByRoleUrl}?role=${encodeURIComponent('Project Manager')}`),
        fetch(`${getUsersByRoleUrl}?role=${encodeURIComponent('Superintendent')}`)
      ]);
      pmList = await pmRes.json();
      ssList = await ssRes.json();
    } catch (e) {
      console.error('Error loading role lists', e);
    }
  }

  // 2) populate a <select>
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

  // 3) wire each row's edit/save toggles
  function wire() {
    $$('tr[data-id]').forEach(row => {
      const pmSel   = row.querySelector('.pm-select');
      const ssSel   = row.querySelector('.ss-select');
      const origPm  = row.dataset.pmId;
      const origSs  = row.dataset.ssId;
      populate(pmSel, pmList, origPm);
      populate(ssSel, ssList, origSs);

      const lockBtn = row.querySelector('.lock-btn');
      const saveBtn = row.querySelector('.save-btn');
      const fields  = [...row.querySelectorAll('.editable'), pmSel, ssSel];

      lockBtn.addEventListener('click', () => {
        const editing = lockBtn.textContent === '🔒';
        lockBtn.textContent = editing ? '🔓' : '🔒';
        lockBtn.title       = editing ? 'Lock Row' : 'Unlock Row';
        fields.forEach(f => {
          if (f.classList.contains('editable')) {
            f.contentEditable = editing;
          } else {
            f.disabled = !editing;
          }
        });
        saveBtn.style.display = editing ? 'inline-block' : 'none';
      });

      saveBtn.addEventListener('click', async () => {
        const id      = row.dataset.id;
        const updates = [];

        row.querySelectorAll('.editable').forEach(c => {
          updates.push({ field: c.dataset.field, value: c.textContent.trim() });
        });
        updates.push({ field: pmSel.dataset.field, value: pmSel.value });
        updates.push({ field: ssSel.dataset.field, value: ssSel.value });

        for (const u of updates) {
          const res = await fetch(updateProjectFieldUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, field: u.field, value: u.value })
          });
          const json = await res.json();
          if (!json.success) {
            return alert(`Error updating ${u.field}: ${json.error}`);
          }
        }

        // re‐lock UI
        fields.forEach(f => {
          if (f.classList.contains('editable')) f.contentEditable = false;
          else f.disabled = true;
        });
        lockBtn.textContent = '🔒';
        saveBtn.style.display = 'none';
        alert('Saved!');
      });
    });
  }

  // 4) init
  fetchLists().then(wire);
})();
