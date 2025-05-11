<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../index.php");
    exit;
}
require_once(__DIR__ . '/../../config/db_connect.php');
require_once(__DIR__ . '/../../includes/header.php');

$filter_today = isset($_GET['filter_today']);

$sql = "
  SELECT cl.id,
         u.full_name            AS user_name,
         cl.location,
         cl.check_in_date,
         cl.check_in_clock,
         cl.check_out_date,
         cl.check_out_clock
    FROM `check_log` cl
    JOIN `users` u ON cl.user_id = u.id
";
if ($filter_today) {
    $sql .= " WHERE cl.check_in_date = CURDATE()";
}
$sql .= " ORDER BY cl.check_in_date DESC, cl.check_in_clock DESC";

$result = $mysqli->query($sql);
?>
<div class="container">
  <h2>Check Log</h2>
  <div style="margin-bottom:1rem;">
    <a href="view_check_log.php">All</a> |
    <a href="view_check_log.php?filter_today=1">Today</a>
  </div>
  <table class="table">
    <thead>
      <tr>
        <th>User</th>
        <th>Location</th>
        <th>In Date</th>
        <th>In Time</th>
        <th>Out Date</th>
        <th>Out Time</th>
      </tr>
    </thead>
    <tbody>
      <?php while ($row = $result->fetch_assoc()): ?>
      <tr>
        <td><?= htmlspecialchars($row['user_name']) ?></td>
        <td><?= htmlspecialchars($row['location'])  ?></td>
        <td><?= htmlspecialchars($row['check_in_date'])  ?></td>
        <td><?= htmlspecialchars($row['check_in_clock']) ?></td>
        <td><?= htmlspecialchars($row['check_out_date']  ?? '-') ?></td>
        <td><?= htmlspecialchars($row['check_out_clock'] ?? '-') ?></td>
      </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</div>
<?php require_once(__DIR__ . '/../../includes/footer.php'); ?>
