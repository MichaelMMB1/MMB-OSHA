<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    session_unset();
    session_destroy();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>MMB Portal</title>
</head>
<body>

<div style="height: 100vh; display: flex; justify-content: center; align-items: center;">

  <?php if (isset($_SESSION['user_id'])): ?>
    <form action="/logout.php" method="post">
      <button type="submit" style="
        padding: 14px 28px;
        background-color: #ff4d4d;
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 18px;
        font-weight: bold;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        cursor: pointer;
      ">
        🔓 Log Out
      </button>
    </form>
  <?php else: ?>
    <a href="/login.php" style="
      padding: 14px 28px;
      background-color: #0f4d75;
      color: white;
      text-decoration: none;
      border-radius: 8px;
      font-size: 18px;
      font-weight: bold;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    ">
      Log In
    </a>
  <?php endif; ?>

</div>

</body>
</html>
