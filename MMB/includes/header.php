<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$pageTitle = $pageTitle ?? 'MMB Contractors Admin';

// Build user initials for profile badge
$initials = '';
if (isset($_SESSION['full_name'])) {
    $names = explode(' ', $_SESSION['full_name']);
    foreach ($names as $n) {
        $initials .= strtoupper($n[0]);
    }
    $initials = substr($initials, 0, 2);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <link rel="stylesheet" href="/assets/css/style.css">
  <script src="/assets/js/common.js" defer></script>
</head>
<body>

  <!-- Navbar -->
<div class="navbar">
  <a href="/dashboard/admin.php" class="navbar-left">
    <img src="/assets/images/logo-white.png" alt="MMB Logo" class="logo-vertical">
  </a>

    <div class="navbar-right">
      <a href="/directory/directory.php">DIRECTORY</a>
      <a href="/activity/activity.php">ACTIVITY</a>
      <a href="/scheduler.php">SCHEDULER</a>
      <a href="/safety.php">SAFETY</a>
      <a href="/projects/projects.php" class="btn-primary"<?= ($current ?? '') === 'projects' ? ' active' : '' ?>">
        PROJECTS
      </a>

      <div class="navbar-profile-container">
        <div id="profileIcon" class="navbar-profile">
          <?= htmlspecialchars($initials) ?>
        </div>
        <div id="dropdown" class="dropdown-menu">
        <div class="dropdown-header">
          <?= htmlspecialchars($_SESSION['role'] ?? '') ?>
        </div>
          <a href="#" onclick="openProfileDrawer()">Profile</a>
          <a href="/logout.php">Log Out</a>
        </div>
      </div>
    </div>
  </div>


  <!-- Backdrop -->
  <div id="drawerBackdrop" class="drawer-backdrop"></div>


