<?php
session_start();

// Generate user initials from full name
$initials = '??';
if (isset($_SESSION['full_name'])) {
    $names = explode(' ', trim($_SESSION['full_name']));
    $first = strtoupper(substr($names[0], 0, 1));
    $last = isset($names[1]) ? strtoupper(substr($names[1], 0, 1)) : '';
    $initials = $first . $last;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>MMB Portal</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<header>
  <nav>
    <div class="nav-left">
      <a href="../public/dashboard.php">
        <img src="../assets/images/logo.png" alt="MMB Portal" class="logo">
      </a>
    </div>

    <div class="nav-right">
      <div class="nav-links">
        <a href="../public/users.php">USERS</a>
        <a href="../public/activity.php">ACTIVITY</a>
        <a href="../public/scheduler.php">SCHEDULER</a>
        <a href="../public/safety.php">SAFETY</a>
      </div>

      <div class="profile-menu">
        <div class="profile-circle" onclick="toggleDropdown()"><?= htmlspecialchars($initials) ?></div>
        <div id="dropdown" class="dropdown-content">
          <a href="#">My Profile</a>
          <a href="../public/logout.php">Logout</a>
        </div>
      </div>
    </div>
  </nav>
</header>

<script>
function toggleDropdown() {
  const dropdown = document.getElementById("dropdown");
  dropdown.style.display = dropdown.style.display === "block" ? "none" : "block";
}

// Close dropdown when clicking outside
window.onclick = function(event) {
  if (!event.target.matches('.profile-circle')) {
    const dropdown = document.getElementById("dropdown");
    if (dropdown && dropdown.style.display === "block") {
      dropdown.style.display = "none";
    }
  }
}
</script>
