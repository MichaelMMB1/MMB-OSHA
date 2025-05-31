<?php
session_start();
require_once __DIR__ . '/../../config/db_connect.php';

// Optional: block non-admin users
if ($_SESSION['role'] ?? '' !== 'admin') {
    die("Access denied.");
}

$success = '';
$error = '';
$hash = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $plain    = trim($_POST['password'] ?? '');

    if ($username === '' || $plain === '') {
        $error = "Both fields are required.";
    } else {
        $hash = password_hash($plain, PASSWORD_BCRYPT);

        // Update user
        $sql = "UPDATE users SET password = $1, plain_password = $2 WHERE username = $3";
        $result = pg_query_params($conn, $sql, [$hash, $plain, $username]);

        if ($result && pg_affected_rows($result) === 1) {
            $success = "Password updated for user '$username'.";
        } else {
            $error = "User not found or update failed.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin - Set User Password</title>
    <link rel="stylesheet" href="/assets/styles.css">
</head>
<body>
    <h2>🔐 Admin: Set User Password</h2>

    <?php if ($success): ?>
        <p style="color: green;"><?= htmlspecialchars($success) ?></p>
    <?php elseif ($error): ?>
        <p style="color: red;"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="POST">
        <label>Username:<br>
            <input type="text" name="username" required>
        </label><br><br>

        <label>Plain Password:<br>
            <input type="text" name="password" required>
        </label><br><br>

        <button type="submit">Set Password</button>
    </form>

    <?php if ($hash): ?>
        <h3>Generated Bcrypt Hash:</h3>
        <code><?= htmlspecialchars($hash) ?></code>
    <?php endif; ?>
</body>
</html>
