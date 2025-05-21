<?php
require_once __DIR__ . '/../includes/headers.php';
?>

<h2>Superintendent Dashboard</h2>
<p>Welcome, <?= htmlspecialchars($_SESSION['full_name'] ?? 'Superintendent') ?>. Monitor your jobsite progress and compliance here.</p>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
