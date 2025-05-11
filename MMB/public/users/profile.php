<?php
// public/users/profile.php – Individual User Profile Side-Drawer (logic moved to common.js)

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/header.php';

// Fetch current user info
$userId = $_SESSION['user_id'];
$stmt = $mysqli->prepare(
    "SELECT full_name, email, phone, position_title, role, username, created_at FROM users WHERE id = ?"
);
$stmt->bind_param('i', $userId);
$stmt->execute();
$stmt->bind_result($fullName, $email, $phone, $positionTitle, $role, $username, $createdAt);
$stmt->fetch();
$stmt->close();
?>

<!-- Side Drawer Markup -->
<div id="profileDrawer" class="side-drawer">
  <button class="drawer-close" onclick="toggleDrawer('profileDrawer')">×</button>
  <h2>My Profile</h2>
  <table class="styled-table">
    <tbody>
      <tr><th>Full Name</th><td><?= htmlspecialchars($fullName) ?></td></tr>
      <tr><th>Email</th><td><?= htmlspecialchars($email) ?></td></tr>
      <tr><th>Phone</th><td><?= htmlspecialchars($phone) ?></td></tr>
      <tr><th>Position</th><td><?= htmlspecialchars($positionTitle) ?></td></tr>
      <tr><th>Role</th><td><?= htmlspecialchars($role) ?></td></tr>
      <tr><th>Username</th><td><?= htmlspecialchars($username) ?></td></tr>
      <tr><th>Member Since</th><td><?= date('F j, Y', strtotime($createdAt)) ?></td></tr>
    </tbody>
  </table>
  <div class="button-group" style="margin-top:1rem;">
    <a href="edit_profile.php" class="btn btn-primary">Edit Profile</a>
    <a href="change_password.php" class="btn btn-secondary">Change Password</a>
  </div>
</div>
```html
<!-- In header.php ensure common.js is loaded -->
<script src="/MMB/public/js/common.js"></script>
<!-- Trigger button -->
<button type="button" class="btn btn-primary" onclick="toggleDrawer('profileDrawer')">View Profile</button>