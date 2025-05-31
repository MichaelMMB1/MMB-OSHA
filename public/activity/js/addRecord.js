// public/activity/js/addRecord.js

// Handles the "Add Activity" modal and submission
document.addEventListener('DOMContentLoaded', function() {
  const openBtn = document.getElementById('openAddActivityModal');
  const modal   = document.getElementById('addActivityModal');
  const form    = document.getElementById('addActivityForm');

  // Show the modal
  openBtn.addEventListener('click', () => {
    modal.classList.add('is-active');
  });

  // Close modal on background or close-button click
  modal.querySelectorAll('.modal-background, .modal-close').forEach(el =>
    el.addEventListener('click', () => modal.classList.remove('is-active'))
  );

  // Handle form submission
  form.addEventListener('submit', event => {
    event.preventDefault();
    const payload = [{
      user_id:        form.user_id.value,
      date:           form.activity_date.value,
      check_in_time:  form.check_in.value,
      check_out_time: form.check_out.value,
      project_id:     form.project_id.value
    }];

    fetch('api/add_multiple_records.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(resp => {
      if (resp.success) {
        window.location.reload();
      } else {
        alert('Error adding record: ' + resp.error);
      }
    })
    .catch(err => {
      console.error('Add record failed:', err);
      alert('Unexpected error. See console.');
    });
  });
});
