<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit;
}
require_once(__DIR__ . '/../../config/db_connect.php');
require_once(__DIR__ . '/../../includes/worker-header.php');

$user_id = $_SESSION['user_id'];
$stmt = $mysqli->prepare("
  SELECT location,
         check_in_date,
         check_in_clock,
         check_out_date,
         check_out_clock
    FROM `check_log`
   WHERE `user_id` = ?
   ORDER BY check_in_date DESC, check_in_clock DESC
");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>
<div class="container">
  <h2>Your Check Log</h2>
  <table class="table">
    <thead>
      <tr>
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
        <td><?= htmlspecialchars($row['location']) ?></td>
        <td><?= htmlspecialchars($row['check_in_date']) ?></td>
        <td><?= htmlspecialchars($row['check_in_clock']) ?></td>
        <td><?= htmlspecialchars($row['check_out_date']  ?? '-') ?></td>
        <td><?= htmlspecialchars($row['check_out_clock'] ?? '-') ?></td>
      </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</div>
<?php require_once(__DIR__ . '/../../includes/footer.php'); ?>
