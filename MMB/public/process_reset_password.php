<!-- Reset Password Modal (ensure this is present) -->
<div id="resetPasswordModal" class="modal-overlay">
  <div class="modal-content">
    <h3>Reset Password for <span id="reset-user-name"></span></h3>
    <form method="POST" action="process_reset_password.php">
      <input type="hidden" name="user_id" id="reset-user-id">
      <label>New Password</label>
      <input type="password" name="new_password" class="modal-input" required>
      <label>Confirm Password</label>
      <input type="password" name="confirm_password" class="modal-input" required>
      <div style="display:flex;justify-content:flex-end;gap:0.5rem;margin-top:1rem;">
        <button type="button" class="btn btn-secondary" onclick="closeResetModal()">Cancel</button>
        <button type="submit" class="btn btn-primary">Save</button>
      </div>
    </form>
  </div>
</div>

<script>
// 1) Make these global so inline onclick can see them:
window.openResetModal = function(userId, fullName) {
  console.log('openResetModal called for', userId, fullName);
  document.getElementById('reset-user-id').value        = userId;
  document.getElementById('reset-user-name').textContent = fullName;
  document.getElementById('resetPasswordModal').style.display = 'flex';
};

window.closeResetModal = function() {
  document.getElementById('resetPasswordModal').style.display = 'none';
};

// 2) Anywhere user clicks outside modal-content, close it
window.addEventListener('click', function(e) {
  if (e.target.id === 'resetPasswordModal') {
    closeResetModal();
  }
});
</script>
</body>
</html>
