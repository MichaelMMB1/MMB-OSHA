<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../config/db_connect.php';

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Both email and password are required.';
    } else {
        $sql = '
          SELECT id, full_name, first_name, last_name, password, role
            FROM users
           WHERE email = $1
           LIMIT 1
        ';
        $result = pg_query_params($conn, $sql, [$email]);

        if ($result && $user = pg_fetch_assoc($result)) {
            if (password_verify($password, trim($user['password']))) {
                session_regenerate_id(true);

                // store session data
                $_SESSION['id']         = (int)$user['id'];
                $_SESSION['user_id']    = (int)$user['id'];
                $_SESSION['full_name']  = $user['full_name'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name']  = $user['last_name'];
                $_SESSION['role']       = $user['role'];

                // redirect by role
                $roleMap = [
                    'admin'           => 'dashboard/php/dashboard_admin.php',
                    'project manager' => 'dashboard/php/dashboard_project_manager.php',
                    'standard'        => 'dashboard/php/dashboard_standard.php',
                    'superintendent'  => 'dashboard/php/dashboard_superintendent.php',
                ];
                $key  = strtolower(trim($user['role']));
                $dest = $roleMap[$key] ?? 'index.php';

                header("Location: /{$dest}");
                exit;
            } else {
                $error = 'Incorrect password.';
            }
        } else {
            $error = 'No account found with that email.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Login — MMB Contractors</title>
  <link rel="stylesheet" href="/assets/styles.css">
  <style>
    /* LOGIN PAGE STYLES */
    .login-container {
      width: 80%;
      max-width: 400px;
      margin: 5rem auto;
      padding: 1rem;
      background: #ffff;
      border:2px  #1C262B;
      border-radius: 8px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      font-family: sans-serif;
      box-sizing: border-box;
    }
    .login-logo {
      text-align: center;
      margin-bottom: 1rem;
    }
    .login-logo img {
      max-width: auto;
      width: 100%;
      height: auto;
    }
    .login-container h2 {
      text-align: center;
      margin-bottom: 1rem;
      font-size: 1rem;
      color: #1C262B;
    }
    .form-group {
      margin-bottom: 1rem;
    }
    .form-group label {
      display: block;
      margin-bottom: .5rem;
      font-weight: 600;
    }
    .form-group input {
      width: 100%;
      padding: .75rem;
      font-size: 1rem;
      border: 1px solid #ccc;
      border-radius: 4px;
      box-sizing: border-box;
    }
    .btn-submit {
      width: 100%;
      padding: .75rem;
      background: #0f4d75;
      color: #fff;
      border: none;
      border-radius: 4px;
      font-size: 1rem;
      font-weight: 600;
      cursor: pointer;
      margin-top: 0.5rem;
    }
    .error {
      color: #d00;
      text-align: center;
      margin-bottom: 1rem;
    }
    @media (min-width: 768px) {
      .login-container {
        margin-top: 10vh;
      }
    }
  </style>
</head>
<body>
  <div class="login-container">
    <!-- Logo up top -->
    <div class="login-logo">
      <img src="/assets/images/logo.png" alt="MMB Contractors Logo">
    </div>

    
    <?php if ($error): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" novalidate>
      <div class="form-group">
        <label for="email">Email Address</label>
        <input
          id="email"
          type="email"
          name="email"
          value="<?= htmlspecialchars($email) ?>"
          required
          autofocus
        >
      </div>

      <div class="form-group">
        <label for="password">Password</label>
        <input
          id="password"
          type="password"
          name="password"
          required
        >
      </div>

      <button type="submit" class="btn-submit">Log In</button>
    </form>
  </div>

  <script src="/js/sw-register.js"></script>
</body>
</html>
