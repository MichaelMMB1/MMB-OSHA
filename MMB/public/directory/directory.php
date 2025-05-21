<?php
// public/directory.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/header.php';

$activeTab = $_GET['tab'] ?? 'users';

// Fetch users with trade
$resUsers = pg_query($conn, "
  SELECT u.id, u.username, u.full_name, u.email, u.role, u.trade_id, t.name AS trade
  FROM users u
  LEFT JOIN trades t ON u.trade_id = t.id
  ORDER BY u.full_name
");
$users = $resUsers ? pg_fetch_all($resUsers) : [];

// Fetch companies
$resCompanies = pg_query($conn, "
  SELECT id, name AS company_name, trade, website, email, phone, created_at, updated_at, trades AS trades_set, full_address
  FROM companies
  ORDER BY name
");
$companies = $resCompanies ? pg_fetch_all($resCompanies) : [];

// Fetch roles
$resRoles = pg_query($conn, 'SELECT name FROM roles ORDER BY name');
$roles = $resRoles ? pg_fetch_all_columns($resRoles) : [];

// Fetch trades
$trades = pg_fetch_all(pg_query($conn, "SELECT id, name, color FROM trades ORDER BY id")) ?: [];
?>

  <h1 style="text-align:center; margin:1.5rem 0; font-size:1.75rem; font-weight:bold;">
    DIRECTORY
  </h1>

<div class="container page-content">
  <div class="tabs">
    <a href="?tab=users" class="tab-btn<?= $activeTab === 'users' ? ' active' : '' ?>">Users</a>
    <a href="?tab=companies" class="tab-btn<?= $activeTab === 'companies' ? ' active' : '' ?>">Companies</a>
    <a href="?tab=trades" class="tab-btn<?= $activeTab === 'trades' ? ' active' : '' ?>">Trades</a>
  </div>

  <div class="tab-panels">

    <!-- Users Panel -->
    <?php if ($activeTab === 'users'): ?>
    <div id="usersTab" class="tab-panel active">
      <table class="styled-table">
        <thead>
          <tr>
            <th>Name</th>
            <th>Username</th>
            <th>Email</th>
            <th>Role</th>
            <th>Trade</th>
            <th>Edit</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($users): foreach ($users as $u): ?>
            <tr data-id="<?= (int)$u['id'] ?>">
              <td contenteditable="false" class="editable" data-field="full_name"><?= htmlspecialchars($u['full_name']) ?></td>
              <td contenteditable="false" class="editable" data-field="username"><?= htmlspecialchars($u['username']) ?></td>
              <td contenteditable="false" class="editable" data-field="email"><?= htmlspecialchars($u['email']) ?></td>
              <td>
                <select class="role-select" data-field="role" disabled>
                  <?php foreach ($roles as $r): ?>
                    <option value="<?= htmlspecialchars($r) ?>" <?= $r === $u['role'] ? 'selected' : '' ?>>
                      <?= htmlspecialchars($r) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td>
                <select class="trade-select" data-field="trade_id" disabled>
                  <option value="">--</option>
                  <?php foreach ($trades as $t): ?>
                    <option value="<?= (int)$t['id'] ?>" <?= ((int)$u['trade_id'] === (int)$t['id']) ? 'selected' : '' ?>>
                      <?= htmlspecialchars($t['name']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td>
                <button class="lock-btn btn-modify" title="Unlock Row">🔒</button>
                <button class="save-btn btn-primary" style="display:none;">💾</button>
              </td>
            </tr>
          <?php endforeach; else: ?>
            <tr><td colspan="6" class="text-center">No users found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <!-- Companies Panel -->
    <?php if ($activeTab === 'companies'): ?>
    <div id="companiesTab" class="tab-panel active">
      <table class="styled-table">
        <thead>
          <tr>
            <th>Name</th>
            <th>Trade</th>
            <th>Website</th>
            <th>Email</th>
            <th>Phone</th>
            <th>Created</th>
            <th>Updated</th>
            <th>Address</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($companies): foreach ($companies as $c): ?>
            <tr data-id="<?= (int)$c['id'] ?>">
              <td><?= htmlspecialchars($c['company_name']) ?></td>
              <td><?= htmlspecialchars($c['trade']) ?></td>
              <td><?= htmlspecialchars($c['website']) ?></td>
              <td><?= htmlspecialchars($c['email']) ?></td>
              <td><?= htmlspecialchars($c['phone']) ?></td>
              <td><?= htmlspecialchars($c['created_at']) ?></td>
              <td><?= htmlspecialchars($c['updated_at']) ?></td>
              <td><?= nl2br(htmlspecialchars($c['full_address'])) ?></td>
            </tr>
          <?php endforeach; else: ?>
            <tr><td colspan="8" class="text-center">No companies found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <!-- Trades Panel -->
    <?php if ($activeTab === 'trades'): ?>
    <div id="tradesTab" class="tab-panel active">
      <h2>Trades</h2>
      <table class="styled-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Trade Name</th>
            <th>Color</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($trades as $trade): ?>
            <tr>
              <td><?= htmlspecialchars($trade['id']) ?></td>
              <td><?= htmlspecialchars($trade['name']) ?></td>
              <td>
                <span class="tag" style="background-color: <?= htmlspecialchars($trade['color']) ?>;">
                  <?= htmlspecialchars($trade['color']) ?>
                </span>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('tr[data-id]').forEach(row => {
    const lockBtn = row.querySelector('.lock-btn');
    const saveBtn = row.querySelector('.save-btn');
    const fields  = row.querySelectorAll('[contenteditable], select');

    if (lockBtn && saveBtn) {
      lockBtn.addEventListener('click', () => {
        const unlocking = lockBtn.textContent === '🔒';
        lockBtn.textContent = unlocking ? '🔓' : '🔒';
        lockBtn.title       = unlocking ? 'Lock Row' : 'Unlock Row';

        fields.forEach(el => {
          if (el.hasAttribute('contenteditable')) {
            el.setAttribute('contenteditable', unlocking ? 'true' : 'false');
          } else {
            el.disabled = !unlocking;
          }
        });

        saveBtn.style.display = unlocking ? 'inline-block' : 'none';
      });

      saveBtn.addEventListener('click', async () => {
        const id = row.dataset.id;
        const updates = [];

        row.querySelectorAll('.editable').forEach(cell => {
          updates.push({ field: cell.dataset.field, value: cell.textContent.trim() });
        });

        const role  = row.querySelector('.role-select');
        const trade = row.querySelector('.trade-select');
        updates.push({ field: 'role', value: role.value });
        updates.push({ field: 'trade_id', value: trade.value });

        for (const upd of updates) {
          try {
            const res = await fetch('api/update_user_field.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ id, field: upd.field, value: upd.value })
            });
            const json = await res.json();
            if (!json.success) throw new Error(json.error || 'Failed to update');
          } catch (err) {
            return alert(`Error updating ${upd.field}: ${err.message}`);
          }
        }

        fields.forEach(el => {
          if (el.hasAttribute('contenteditable')) {
            el.setAttribute('contenteditable', 'false');
          } else {
            el.disabled = true;
          }
        });
        lockBtn.textContent = '🔒';
        saveBtn.style.display = 'none';
        alert('Changes saved successfully.');
      });
    }
  });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
