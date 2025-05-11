<?php
// remove_user.php
// Deletes a user record based on POSTed user_id

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

// Adjusted path to db_connect.php (two levels up from public/users)
require_once(__DIR__ . '/../../config/db_connect.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['user_id'])) {
    $userId = intval($_POST['user_id']);

    // Prevent deleting the currently logged-in user
    if ($userId === intval($_SESSION['user_id'])) {
        $_SESSION['flash_error'] = 'You cannot delete your own account.';
        header('Location: users.php');
        exit;
    }

    // Prepare and execute deletion
    $stmt = $mysqli->prepare('DELETE FROM users WHERE id = ?');
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        if ($stmt->execute()) {
            $_SESSION['flash_success'] = 'User removed successfully.';
        } else {
            $_SESSION['flash_error'] = 'Error deleting user.';
        }
        $stmt->close();
    } else {
        $_SESSION['flash_error'] = 'Database error.';
    }
}

// Redirect back to users listing
header('Location: users.php');
exit;
