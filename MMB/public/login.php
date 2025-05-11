<?php
// login.php – MMB Contractors Login

// 1) Scope the session cookie to your app folder and make it HTTP-only
session_set_cookie_params([
    'path'     => '/MMB/public',
    'httponly' => true,
    // 'secure' => true,  // enable if you serve over HTTPS
]);

// 2) Start the session
session_start();

// 3) If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

// 4) Database connection
require_once(__DIR__ . '/../config/db_connect.php');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = "Please fill in both fields.";
    } else {
        // 5) Prepare statement selecting id, name, hash and role
        $stmt = $mysqli->prepare("
            SELECT id, full_name, password, role
              FROM users
             WHERE username = ?
             LIMIT 1
        ");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 1) {
            // 6) Bind result variables, including the role
            $stmt->bind_result(
                $user_id,
                $full_name,
                $hashed_password,
                $roleFromDb
            );
            $stmt->fetch();

            // 7) Verify password
            if (password_verify($password, $hashed_password) || $password === $hashed_password) {
                session_regenerate_id(true);

                // 8) Store everything in session, including role
                $_SESSION['user_id']   = $user_id;
                $_SESSION['full_name'] = $full_name;
                $_SESSION['role']      = $roleFromDb;  

                header("Location: dashboard.php");
                exit;
            } else {
                $error = "Invalid username or password.";
            }
        } else {
            $error = "Invalid username or password.";
        }

        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Login – MMB Contractors Portal</title>
  <link rel="stylesheet" href="../assets/css/style.css" />
</head>
<body class="login-page">

<div class="login-container" style="
    margin: 60px auto;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    padding: 40px;
    border-radius: 12px;
    box-shadow: 0 8px 20px rgba(0,0,0,0.1);
    background-color: white;
    max-width: 400px;
">
  <img src="../assets/images/logo.png"
       class="logo"
       alt="MMB Logo"
       style="margin-bottom: 20px; height: 60px;" />

  <?php if ($error): ?>
    <p style="color: red; font-weight: bold; margin-bottom: 10px;">
      <?= htmlspecialchars($error) ?>
    </p>
  <?php endif; ?>

  <form method="POST" action="login.php" style="width: 100%;">
    <input type="text"
           name="username"
           placeholder="Username"
           required
           style="width:100%;padding:14px 20px;margin-bottom:12px;
                  border-radius:8px;border:1px solid #ccc;box-sizing:border-box;" />

    <div style="position: relative; margin-bottom:12px;">
      <input type="password"
             name="password"
             id="password"
             placeholder="Password"
             required
             style="width:100%;padding:14px 20px;border-radius:8px;
                    border:1px solid #ccc;box-sizing:border-box;" />
      <label style="position: absolute; top:50%; right:12px;
                    transform: translateY(-50%); font-size:14px;">
        <input type="checkbox" id="togglePassword" style="margin-right:6px;" />
        Show
      </label>
    </div>

    <button type="submit" style="
        width:100%;padding:14px 20px;background-color:#0f4d75;
        color:white;font-weight:bold;border:none;border-radius:8px;
        font-size:16px;cursor:pointer;box-shadow:0 4px 10px rgba(0,0,0,0.1);
    ">
      Log In
    </button>
  </form>

  <div style="text-align: center; margin-top: 1rem;">
    <a href="reset_password.php" style="
        color: #1e40af; text-decoration: underline; display: block;
        margin-bottom: 6px;
    ">Forgot your password?</a>
    <a href="register.php" class="create-account" style="
        color: #1e40af; text-decoration: underline;
    ">Create new account</a>
  </div>
</div>

<script>
  // Toggle password visibility
  const togglePassword = document.getElementById('togglePassword');
  const passwordInput = document.getElementById('password');
  togglePassword.addEventListener('change', () => {
    passwordInput.type = togglePassword.checked ? 'text' : 'password';
  });
</script>

</body>
</html>
