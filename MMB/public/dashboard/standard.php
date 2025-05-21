<?php
require_once __DIR__ . '/../includes/headers.php';
?>

<h2>Standard User Dashboard</h2>
<p>Welcome, <?= htmlspecialchars($_SESSION['full_name'] ?? 'User') ?>. Check in to projects and access your safety materials here.</p>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
