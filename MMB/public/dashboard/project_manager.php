<?php
require_once __DIR__ . '/../includes/headers.php';
?>

<h2>Project Manager Dashboard</h2>
<p>Welcome, <?= htmlspecialchars($_SESSION['full_name'] ?? 'Project Manager') ?>. Manage your assigned jobs and reports here.</p>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
