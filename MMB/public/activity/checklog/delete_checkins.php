<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../index.php");
    exit;
}
require_once(__DIR__ . '/../../config/db_connect.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $from = $_POST['from_date'] ?? '';
    $to   = $_POST['to_date']   ?? '';
    if ($from && $to) {
        $stmt = $mysqli->prepare("
            DELETE FROM `check_log`
             WHERE `check_in_date` BETWEEN ? AND ?
        ");
        $stmt->bind_param('ss', $from, $to);
        $stmt->execute();
        $stmt->close();
        header("Location: view_check_log.php");
        exit;
    }
}

require_once(__DIR__ . '/../../includes/header.php');
?>
<div class="container">
  <h2>Delete Check Logs</h2>
  <form method="post" style="display:flex; gap:1rem; align-items:flex-end;">
    <div>
      <label>From: <input type="date" name="from_date"></label>
    </div>
    <div>
      <label>To:   <input type="date" name="to_date"></label>
    </div>
    <button class="btn-primary" type="submit">Delete</button>
  </form>
</div>
<?php require_once(__DIR__ . '/../../includes/footer.php'); ?>
