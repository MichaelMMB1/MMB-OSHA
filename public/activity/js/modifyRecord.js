// public/activity/js/modifyRecord.js

// Handles the "Modify Activity" modal and update requests
document.addEventListener('DOMContentLoaded', function() {
  const modal   = document.getElementById('modifyActivityModal');
  const form    = document.getElementById('modifyActivityForm');
  let currentId = null;

  // Delegate clicks on all modify buttons
  document.getElementById('activityTableContainer').addEventListener('click', e => {
    if (e.target.matches('.modify-activity-btn')) {
      currentId = e.target.dataset.id;
      // Fill form fields from row data attributes
      form.id.value         = currentId;
      form.activity_date.value  = e.target.dataset.date;
      form.user_id.value    = e.target.dataset.userId;
      form.project_id.value = e.target.dataset.projectId;
      form.check_in.value   = e.target.dataset.checkIn;
      form.check_out.value  = e.target.dataset.checkOut;
      form.verified.checked = e.target.dataset.verified === '1';

      modal.classList.add('is-active');
    }
  });

  // Close modal
  modal.querySelectorAll('.modal-background, .modal-close').forEach(el =>
    el.addEventListener('click', () => modal.classList.remove('is-active'))
  );

  // Submit update
  form.addEventListener('submit', event => {
    event.preventDefault();
    const payload = [{
      id:             currentId,
      date:           form.activity_date.value,
      user_id:        form.user_id.value,
      project_id:     form.project_id.value,
      check_in:       form.check_in.value,
      check_out:      form.check_out.value,
      verified:       form.verified.checked
    }];

    fetch('php/update_multiple.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(resp => {
      if (resp.success) {
        window.location.reload();
      } else {
        alert('Error updating record: ' + resp.error);
      }
    })
    .catch(err => {
      console.error('Update failed:', err);
      alert('Unexpected error. See console.');
    });
  });
});
