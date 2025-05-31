// public/activity/js/tabs.js

// Handles switching between the different Activity tabs
document.addEventListener('DOMContentLoaded', function() {
  const tabs = document.querySelectorAll('.activity-tab');
  const container = document.getElementById('activityTableContainer');

  function loadTab(tabKey) {
    fetch(`php/activity_content.php?tab=${tabKey}`)
      .then(res => res.text())
      .then(html => {
        container.innerHTML = html;
      })
      .catch(err => {
        console.error('Failed to load Activity tab:', err);
        container.innerHTML = '<p style="color:red;">Error loading data.</p>';
      });
  }

  tabs.forEach(tab => {
    tab.addEventListener('click', e => {
      e.preventDefault();
      const key = tab.getAttribute('data-tab');
      // highlight
      tabs.forEach(t => t.classList.remove('is-active'));
      tab.classList.add('is-active');
      loadTab(key);
    });
  });

  // On initial page load, fire the current active tab
  const active = document.querySelector('.activity-tab.is-active');
  if (active) loadTab(active.getAttribute('data-tab'));
});
