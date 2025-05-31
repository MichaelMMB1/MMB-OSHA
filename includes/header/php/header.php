<?php
// includes/header.php

declare(strict_types=1);
// 1) Bootstrap: session, timezone, DB connection

// Prevent double‐inclusion
if (defined('MMB_HEADER_INCLUDED')) {
    return;
}
define('MMB_HEADER_INCLUDED', true);

// 2) Build avatar initials
$first    = $_SESSION['first_name'] ?? '';
$last     = $_SESSION['last_name']  ?? '';
$initials = '';
if ($first) $initials .= mb_strtoupper(mb_substr($first, 0, 1));
if ($last)  $initials .= mb_strtoupper(mb_substr($last,  0, 1));

// 3) Normalize the role slug
$roleName = $_SESSION['role'] ?? '';
$slug     = strtolower(str_replace(' ', '_', trim($roleName)));

// 4) Map each role → its dashboard URL
$dashboardMap = [
    'admin'             => '/dashboard/php/dashboard_admin.php',
    'project_manager'   => '/dashboard/php/dashboard_project_manager.php',
    'standard'          => '/dashboard/php/dashboard_standard.php',
    'superintendent'    => '/dashboard/php/dashboard_superintendent.php',
];

// Brand link (fallback to home)
$brandUrl = $dashboardMap[$slug] ?? '/index.php';

// 5) Primary nav items & required permission flags
$navItems = [
    'Activity'  => '/activity/php/activity.php',
    'Directory' => '/directory/php/directory.php',
    'Leads'     => '/leads/php/leads.php',    
    'Projects'  => '/projects/php/projects.php',    
    'Safety'    => '/safety/php/safety.php',
    'Scheduler' => '/scheduler/php/scheduler.php',
];
$permissionMap = [
    'Activity'  => 'can_activity',
    'Directory' => 'can_directory',
    'Leads'     => 'can_leads',
    'Projects'  => 'can_projects',   
    'Safety'    => 'can_safety',     
    'Scheduler' => 'can_scheduler',
];

// 6) Fetch permission flags for this role
$allowedNav = [];
if ($slug && isset($conn)) {
    $colsList = implode(',', array_values($permissionMap));
    $res = pg_query_params(
        $conn,
        "SELECT {$colsList} FROM roles WHERE LOWER(name)=LOWER($1) LIMIT 1",
        [$roleName]
    );
    if ($res && $row = pg_fetch_assoc($res)) {
        foreach ($navItems as $label => $url) {
            if (!empty($row[$permissionMap[$label]]) && $row[$permissionMap[$label]] === 't') {
                $allowedNav[] = $label;
            }
        }
    }
}

// 7) Determine active menu item
$currentFile = basename($_SERVER['SCRIPT_NAME']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>MMB Contractors</title>
  <link rel="stylesheet" href="/assets/css/style.css">
  <link rel="manifest" href="/manifest.json">
  <meta name="theme-color" content="#0f4d75">
  <script src="/assets/js/header.js" defer></script>
  <script src="/assets/js/sw-register.js" defer></script>
</head>
<body>
<header class="navbar">
  <div class="navbar-left">

    <a class="navbar-brand" href="<?= htmlspecialchars($brandUrl) ?>">
      <img src="/assets/images/logo-white.png" alt="MMB Contractors">
    </a>
  </div>
  <div class="navbar-right">
    <?php foreach ($navItems as $label => $url):
      if (!in_array($label, $allowedNav, true)) continue;
      $isActive = ($currentFile === basename($url)) ? 'active' : '';
    ?>
      <a href="<?= htmlspecialchars($url) ?>" class="<?= $isActive ?>"><?= htmlspecialchars($label) ?></a>
    <?php endforeach; ?>

    <div class="navbar-profile-container">
      <div class="navbar-profile"><?= htmlspecialchars($initials) ?></div>
      <div class="dropdown-menu">
        <div class="dropdown-header"><?= htmlspecialchars($_SESSION['full_name'] ?? '') ?></div>
        <?php if ($slug === 'standard'): ?>
          <a href="/user_history.php" class="dropdown-link">History</a>
          <a href="/user_schedule.php" class="dropdown-link">Schedule</a>
        <?php endif; ?>
        <a href="/user_profile/php/user_profile.php" class="dropdown-link">Profile</a>
        <a href="/logout.php" class="dropdown-link">Log Out</a>
      </div>
    </div>
  </div>
</header>

<main class="container page-content">
