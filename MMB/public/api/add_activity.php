<script>
document.addEventListener('DOMContentLoaded', () => {
  const addForm = document.getElementById('addActivityForm');
  const modal = document.getElementById('addActivityModal');

  addForm.addEventListener('submit', async function (e) {
    e.preventDefault();

    const formData = new FormData(addForm);

    try {
      const response = await fetch(addForm.action, {
        method: 'POST',
        body: formData
      });

      const result = await response.json();
      console.log(result);

      if (result.success) {
        // Reset form fields
        addForm.reset();

        // Hide modal
        modal.style.display = 'none';

        // Refresh page to show new data
        window.location.reload();
      } else {
        alert(result.error || 'Something went wrong.');
        console.error(result);
      }
    } catch (err) {
      alert('Submission failed.');
      console.error('Error submitting form:', err);
    }
  });

  // Cancel button behavior
  document.getElementById('cancelAddModal').addEventListener('click', () => {
    modal.style.display = 'none';
  });
});
</script>
