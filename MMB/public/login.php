<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db_connect.php';

$error    = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Both username and password are required.';
    } else {
        $sql = 'SELECT id, username, full_name, password, role FROM users WHERE username = $1 LIMIT 1';
        $result = pg_query_params($conn, $sql, [$username]);

        if ($result && $user = pg_fetch_assoc($result)) {
            if (password_verify($password, trim($user['password']))) {
                session_regenerate_id(true);
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['role']      = $user['role'];
                $_SESSION['full_name'] = $user['full_name'];

                $role = strtolower($user['role']);
                header("Location: /dashboard/{$role}.php");
                exit;
            } else {
                $error = 'Incorrect password.';
            }
        } else {
            $error = 'User not found.';
        }
    }
}
?>






<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Login - MMB Contractors</title>
  <link rel="stylesheet" href="/assets/styles.css">
</head>
<body>
  <h2>Login</h2>

  <?php if ($error): ?>
    <p style="color: red; font-weight: bold;">
      <?= htmlspecialchars($error) ?>
    </p>
  <?php endif; ?>

  <form method="POST">
    <label>Username:<br>
      <input type="text" name="username" value="<?= htmlspecialchars($username) ?>" required>
    </label><br><br>

    <label>Password:<br>
      <input type="password" name="password" required>
    </label><br><br>

    <button type="submit">Log In</button>
  </form>
</body>
</html>




