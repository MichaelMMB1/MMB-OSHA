<?php
// public/users/update_company_trade.php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
  header("Location: ../index.php");
  exit;
}
require_once __DIR__ . '/../../config/db_connect.php';

$company_id = intval($_POST['company_id'] ?? 0);
$trade      = trim($_POST['trade'] ?? '');

if ($company_id) {
    $stmt = $mysqli->prepare("
      UPDATE companies
      SET trade = ?
     WHERE id = ?
    ");
    $stmt->bind_param('si', $trade, $company_id);
    $stmt->execute();
    $stmt->close();
}

// Redirect back to users.php anchored on Companies
header("Location: users.php#companiesTab");
exit;
