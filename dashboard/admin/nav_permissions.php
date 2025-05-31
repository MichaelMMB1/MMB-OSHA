<?php
// public/dashboard/admin/nav_permissions.php

if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: /dashboard/project_manager.php');
    exit;
}

require_once __DIR__ . '/../../../config/db_connect.php';


// Define which nav-permission columns we manage
$cols = [
    'can_directory' => 'Directory',
    'can_activity'  => 'Activity',
    'can_scheduler' => 'Scheduler',
    'can_safety'    => 'Safety',
    'can_projects'  => 'Projects',
    'can_leads'     => 'Leads'
];

// Fetch all roles and their current flags
$sql     = 'SELECT id, name,' . implode(',', array_keys($cols)) . ' FROM roles ORDER BY name';
$res     = pg_query($conn, $sql);
$roles   = $res ? pg_fetch_all($res) : [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($roles as $r) {
        $id     = (int)$r['id'];
        $params = [];

        // Build params for each permission column
        foreach (array_keys($cols) as $col) {
            // We look in $_POST['permissions'][$col][$id]
            $params[] = isset($_POST['permissions'][$col][$id]) ? 't' : 'f';
        }

        // Finally push the role ID as the last placeholder
        $params[] = $id;

        // Build SET clause placeholders dynamically: can_x = $1, can_y = $2, …
        $setClauses = [];
        foreach (array_keys($cols) as $idx => $col) {
            // $idx is zero-based, so placeholder is $idx+1
            $setClauses[] = "$col = $" . ($idx + 1);
        }

        // The WHERE id = placeholder is at position count($cols)+1
        $sql = 'UPDATE roles SET '
             . implode(', ', $setClauses)
             . ' WHERE id = $' . (count($cols) + 1);

        pg_query_params($conn, $sql, $params);
    }

    // Refresh to show updated values
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}
?>

<h2>Navigation Permissions by Role</h2>
<form method="POST">
  <table class="styled-table">
    <thead>
      <tr>
        <th>Role</th>
        <?php foreach ($cols as $col => $label): ?>
          <th><?= htmlspecialchars($label) ?></th>
        <?php endforeach; ?>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($roles as $r): ?>
        <tr>
          <td><?= htmlspecialchars($r['name']) ?></td>
          <?php foreach (array_keys($cols) as $col): ?>
            <td>
              <input
                type="checkbox"
                name="permissions[<?= $col ?>][<?= $r['id'] ?>]"
                <?= ($r[$col] === 't') ? 'checked' : '' ?>
              >
            </td>
          <?php endforeach; ?>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <button type="submit" class="btn btn-primary">Save Changes</button>
</form>

<?php
require_once __DIR__ . '/../../../includes/footer.php';
?>
