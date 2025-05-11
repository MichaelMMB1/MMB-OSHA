<?php
// session_start should already be active in the including script

$initials = '';
if (isset($_SESSION['full_name'])) {
    $names = explode(' ', $_SESSION['full_name']);
    foreach ($names as $n) {
        $initials .= strtoupper($n[0]);
    }
    $initials = substr($initials, 0, 2); // Only 2 letters
}
?>

<style>
  .navbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0px 40px;
    height: 60px;
    background-color:  #0f4d75;
    border-bottom: 5px solid #de5f05;
    font-family: 'Segoe UI', sans-serif;
  }

  .navbar-left a {
    text-decoration: none;
    font-size: 26px;
    font-weight: bold;
    color: #003366;
    display: flex;
    align-items: center;
  }

  .navbar-left a span {
    color: #0096d6;
    margin-left: 5px;
  }

  .navbar-right {
    display: flex;
    gap: 30px;
    align-items: center;
    position: relative;
  }

  .navbar-right a {
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
    color:rgb(255, 255, 255);
    letter-spacing: 0.5px;
  }

  .navbar-right a:hover {
    text-decoration: underline;
    color: #0096d6;
  }

  .navbar-profile {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background-color: #ccc;
    color: white;
    font-weight: bold;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    cursor: pointer;
  }

  .dropdown-menu {
    display: none;
    position: absolute;
    right: 0;
    top: 40px;
    background-color: white;
    border: 1px solid #ccc;
    min-width: 120px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    z-index: 999;
  }

  .dropdown-menu a {
    display: block;
    padding: 10px;
    font-size: 13px;
    text-decoration: none;
    color: #333;
  }

  .dropdown-menu a:hover {
    background: color #f5f5f5;
  }
</style>

<div class="navbar">
<a href="/MMB/public/dashboard.php">
    <img src="/MMB/assets/images/logo-white.png" alt="MMB Logo" style="height: 30px; margin-bottom: 5px;margin-top: 5px;margin-left: 0px; width: auto;">

</a>


  <div class="navbar-right">
    <a href="/MMB/public/users/users.php">DIRECTORY</a>
    <a href="/MMB/public/activity/activity.php">ACTIVITY</a>
    <a href="/MMB/public/scheduler/scheduler.php">SCHEDULER</a>
    <a href="/MMB/public/safety/safety.php">SAFETY</a>
   <a href="/MMB/public/projects/view_projects.php" class="btn btn-secondary<?= $current === 'projects' ? ' active' : '' ?>">
     PROJECTS
</a>
      <div class="navbar-profile" onclick="toggleDropdown()"><?= htmlspecialchars($initials) ?></div>
    <div class="dropdown-menu" id="dropdown">
        <a href onclick="openProfileDrawer()">Profile</a>
      <a href="/MMB/public/logout.php">Log Out</a>
      </div>
    </div>
  </div>
</div>



<script>
  function toggleDropdown() {
    const menu = document.getElementById('dropdown');
    menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
  }

  window.addEventListener('click', function(e) {
    const profile = document.querySelector('.navbar-profile');
    const dropdown = document.getElementById('dropdown');
    if (!profile.contains(e.target) && !dropdown.contains(e.target)) {
      dropdown.style.display = 'none';
    }
  });









  
</script>


