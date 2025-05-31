<?php
session_start();
require_once(__DIR__ . '/../config/db_connect.php');

if (!isset($_SESSION['pending_email'], $_SESSION['pending_code'])) {
    header("Location: reset_password.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['start_over'])) {
        unset($_SESSION['pending_email'], $_SESSION['pending_code'], $_SESSION['pending_name']);
        header("Location: reset_password.php");
        exit;
    }

    $entered_code = trim($_POST['code'] ?? '');
    $new_password = trim($_POST['new_password'] ?? '');

    if ($entered_code === $_SESSION['pending_code']) {
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $mysqli->prepare("UPDATE users SET password=? WHERE email=?");
        $stmt->bind_param("ss", $hashed, $_SESSION['pending_email']);

        if ($stmt->execute()) {
            unset($_SESSION['pending_code'], $_SESSION['pending_email'], $_SESSION['pending_name']);
            $_SESSION['flash_success'] = "Password has been reset successfully.";
            header("Location: login.php");
            exit;
        } else {
            $error = "Failed to reset password. Please try again.";
        }
    } else {
        $error = "Invalid verification code.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Verify Code</title>
  <link rel="stylesheet" href="../assets/css/style.css" />
  <style>
    .form-container {
      max-width: 400px; margin: 80px auto; padding: 2rem;
      border: 1px solid #ccc; border-radius: 8px; background: #fff;
    }
    .form-container h2 {
      margin-bottom: 1rem; text-align: center;
    }
    .form-container input {
      width: 100%; padding: 10px; margin-bottom: 1rem;
      border: 1px solid #ccc; border-radius: 4px;
    }
    .form-container .btn {
      width: 100%; padding: 10px; background: #dc6504;
      border: none; color: white; font-weight: bold; border-radius: 4px;
    }
    .error-message {
      color: red; text-align: center; margin-bottom: 1rem;
    }
  </style>
</head>
<body>
  <div class="form-container">
    <h2>Verify Code & Set New Password</h2>
    <?php if (!empty($error)): ?>
      <div class="error-message"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <input type="text" name="code" placeholder="Enter verification code" value="<?= htmlspecialchars($_POST['code'] ?? '') ?>" required />
      <input type="password" name="new_password" placeholder="Enter new password" required />
      <div style="display: flex; justify-content: space-between; gap: 1rem;">
        <button type="submit" name="start_over" class="btn" style="background: #1e40af;">Back</button>
        <button class="btn" type="submit">Reset Password</button>
      </div>
    </form>
  </div>
</body>
</html>
