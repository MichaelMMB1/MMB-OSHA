<?php
declare(strict_types=1);

// ─── Bootstrap ─────────────────────────────────────────────────────
// Make sure this includes session_start() before you touch $_SESSION
require_once __DIR__ . '/../../bootstrap.php';

// ─── Redirect logged-in users ──────────────────────────────────────
if (isset($_SESSION['user_id'], $_SESSION['role'])) {
    switch ($_SESSION['role']) {
        case 'admin':
            header('Location: /dashboard/php/dashboard_admin.php');
            break;
        case 'project_manager':
            header('Location: /dashboard/php/dashboard_project_manager.php');
            break;
        case 'standard':
            header('Location: /dashboard/php/dashboard_standard.php');
            break;
        case 'superintendent':
            header('Location: /dashboard/php/dashboard_superintendent.php');
            break;
        default:
            header('Location: /login.php');
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>MMB Portal</title>
  <style>
    body, html {
      height: 100%;
      margin: 0;
    }
    .center-wrap {
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100%;
    }
    .btn {
      padding: 14px 28px;
      border: none;
      border-radius: 8px;
      font-size: 18px;
      font-weight: bold;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
      cursor: pointer;
      text-decoration: none;
      color: white;
    }
    .btn-login { background-color: #0f4d75; }
    .btn-logout { background-color: #ff4d4d; }
  </style>
</head>
<body>
  <div class="center-wrap">
    <?php if (isset($_SESSION['user_id'])): ?>
      <!-- This should never appear, since we redirect above -->
      <form action="/logout.php" method="post">
        <button type="submit" class="btn btn-logout">🔓 Log Out</button>
      </form>
    <?php else: ?>
      <a href="/login.php" class="btn btn-login">Log In</a>
    <?php endif; ?>
  </div>
</body>
</html>
