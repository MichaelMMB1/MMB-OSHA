// public/activity/js/verifyRecord.js

document.addEventListener('DOMContentLoaded', () => {
  const container = document.getElementById('checkLogPanels');
  container.addEventListener('click', async (e) => {
    // Only fire on clicks of our verify buttons
    if (!e.target.classList.contains('verify-btn')) return;

    const btn = e.target;
    const logId = parseInt(btn.dataset.logId, 10);
    const newState = !btn.classList.contains('active');

    try {
      const res = await fetch('/api/verify_all.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: logId, verified: newState })
      });
      const json = await res.json();
      if (json.success) {
        // Toggle the UI
        btn.classList.toggle('active', newState);
        btn.textContent = newState ? '✓' : '✖';
      } else {
        alert('Verify failed: ' + (json.error || ''));
      }
    } catch (err) {
      console.error(err);
      alert('Error toggling verify');
    }
  });
});
