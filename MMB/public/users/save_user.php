<?php
// save_user.php
// Handles both adding a new user and editing an existing user with separate first/last names

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

require_once(__DIR__ . '/../../config/db_connect.php');

// Collect POST data
$id           = isset($_POST['id']) ? intval($_POST['id']) : 0;
$firstName    = trim($_POST['first_name'] ?? '');
$lastName     = trim($_POST['last_name'] ?? '');
$fullName     = trim($firstName . ' ' . $lastName);
$username     = trim($_POST['username'] ?? '');
$email        = trim($_POST['email'] ?? '');
$phone        = trim($_POST['phone'] ?? '');
$position     = trim($_POST['position_title'] ?? '');
$role         = trim($_POST['role'] ?? '');
$passwordInput = $_POST['password'] ?? null;

// Validation
if (!$firstName || !$lastName || !$username || !$email || !$position || !$role) {
    $_SESSION['flash_error'] = 'Please fill in all required fields.';
    header('Location: users.php');
    exit;
}

// Prepare optional password update
$passwordClause = '';
$params         = [];
$types          = '';
if ($passwordInput !== null && $passwordInput !== '') {
    $hash = password_hash($passwordInput, PASSWORD_DEFAULT);
    $passwordClause = ', password = ?';
    $types         .= 's';
    $params[]       = $hash;
}

try {
    if ($id > 0) {
        // Update existing user
        $sql = "UPDATE users SET full_name = ?, username = ?, email = ?, phone = ?, position_title = ?, role = ?" . $passwordClause . " WHERE id = ?";
        $stmt = $mysqli->prepare($sql);
        $bindTypes = 'ssssssi' . ($passwordClause ? 's' : '');
        $bindParams = [];
        $bindParams[] = &$bindTypes;
        $bindParams[] = &$fullName;
        $bindParams[] = &$username;
        $bindParams[] = &$email;
        $bindParams[] = &$phone;
        $bindParams[] = &$position;
        $bindParams[] = &$role;
        if ($passwordClause) {
            $bindParams[] = &$hash;
        }
        $bindParams[] = &$id;
        call_user_func_array([$stmt, 'bind_param'], $bindParams);
        $stmt->execute();
        $stmt->close();
        $_SESSION['flash_success'] = 'User updated successfully.';
    } else {
        // Insert new user (password required)
        if (!$passwordInput) {
            $_SESSION['flash_error'] = 'Password is required for new users.';
            header('Location: users.php');
            exit;
        }
        $hash = password_hash($passwordInput, PASSWORD_DEFAULT);
        $sql = 'INSERT INTO users (full_name, username, email, phone, position_title, role, password) VALUES (?, ?, ?, ?, ?, ?, ?)';
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param('sssssss', $fullName, $username, $email, $phone, $position, $role, $hash);
        $stmt->execute();
        $stmt->close();
        $_SESSION['flash_success'] = 'User added successfully.';
    }
} catch (Exception $e) {
    $_SESSION['flash_error'] = 'Database error: ' . $e->getMessage();
}

header('Location: users.php');
exit;
