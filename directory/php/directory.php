<?php

//–– Show all errors while developing ––
ini_set('display_errors', '1');
error_reporting(E_ALL);

//–– Sessions ––
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

//–– Include DB ––
// __DIR__ = public/directory/php
// two levels up is public/config/db_connect.php
require_once __DIR__ . '/../../../config/db_connect.php';

//–– Tab logic ––
$activeTab = $_GET['tab'] ?? 'users';

//–– Fetch data ––
$resUsers = pg_query($conn, "
    SELECT u.id, u.username, u.full_name, u.email, u.role, u.trade_id, u.color, t.name AS trade
      FROM users u
 LEFT JOIN trades t ON u.trade_id = t.id
  ORDER BY u.full_name
");
$users = $resUsers ? pg_fetch_all($resUsers) : [];

$pmUsers = array_filter($users, fn($u) => strtolower($u['role'] ?? '') === 'project manager');
$suUsers = array_filter($users, fn($u) => strtolower($u['role'] ?? '') === 'superintendent');

$resCompanies = pg_query($conn, "
    SELECT id, name AS company_name, trade, website, email, phone, created_at, updated_at, full_address
      FROM companies
  ORDER BY name
");
$companies = $resCompanies ? pg_fetch_all($resCompanies) : [];

$resTrades = pg_query($conn, "
    SELECT id, name, color
      FROM trades
  ORDER BY id
");
$trades = $resTrades ? pg_fetch_all($resTrades) : [];

$resRoles = pg_query($conn, "SELECT name FROM roles ORDER BY name");
$roles    = $resRoles ? pg_fetch_all_columns($resRoles) : [];

//–– Stop PHP and start HTML ––
?>
<h1 style="text-align:center; margin:1.5rem 0; font-size:1.75rem; font-weight:bold;">
  DIRECTORY
</h1>

<div class="tabs">
  <a href="?tab=users" class="tab-btn<?= $activeTab==='users' ? ' active':'' ?>">Users</a>
  <a href="?tab=project_managers" class="tab-btn<?= $activeTab==='project_managers'?' active':'' ?>">Project Managers</a>
  <a href="?tab=superintendents" class="tab-btn<?= $activeTab==='superintendents' ? ' active':'' ?>">Superintendents</a>
  <a href="?tab=companies" class="tab-btn<?= $activeTab==='companies' ? ' active':'' ?>">Companies</a>
  <a href="?tab=trades" class="tab-btn<?= $activeTab==='trades' ? ' active':'' ?>">Trades</a>
</div>

<div class="tab-panels">
  <!-- Users -->
  <?php if ($activeTab === 'users'): ?>
  <div class="tab-panel active">
    <style>
      /* modal CSS omitted for brevity */
    </style>
    <table class="styled-table" data-endpoint="/directory/api/update_user_field.php">
      <thead>
        <tr><th>Name</th><th>Role</th><th>Trade</th><th>Login Info</th></tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u): ?>
        <tr data-id="<?= (int)$u['id'] ?>">
          <td class="editable" data-field="full_name" contenteditable><?= htmlspecialchars($u['full_name'], ENT_QUOTES) ?></td>
          <td>
            <select class="editable" data-field="role">
              <?php foreach ($roles as $r): ?>
              <option value="<?= htmlspecialchars($r, ENT_QUOTES) ?>" <?= $r === ($u['role'] ?? '') ? 'selected' : '' ?>>
                <?= htmlspecialchars($r, ENT_QUOTES) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </td>
          <td>
            <select class="editable" data-field="trade_id">
              <option value="">--</option>
              <?php foreach ($trades as $t): ?>
              <option value="<?= (int)$t['id'] ?>" <?= ((int)$u['trade_id']=== (int)$t['id']) ? 'selected':'' ?>>
                <?= htmlspecialchars($t['name'], ENT_QUOTES) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </td>
          <td>
            <button
  class="btn-login-info"
  data-userid="<?= $u['id'] ?>"
  data-username="<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>"
  data-email   ="<?= htmlspecialchars($u['email'],    ENT_QUOTES) ?>"
>Login Info</button>
            </button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <?php foreach ($users as $u): ?>
<!-- Shared Login Info Modal – standard markup -->
<div id="loginInfoModal" class="modal-overlay">
  <div class="modal-box">
    <div class="modal-header">
      <h2>Login Information</h2>
      <button id="loginModalClose" class="btn-cancel">×</button>
    </div>
    <div class="modal-body">
      <p><strong>Username:</strong> <span id="modalUsername"></span></p>
      <p><strong>Email:</strong>    <span id="modalEmail"></span></p>
      <p><a id="modalResetLink" class="btn-link" href="#">Send Reset Link</a></p>
      <form id="modalChangePasswordForm" method="POST" action="/admin/set_password.php">
        <input type="hidden" name="user_id" id="modalUserId" value="">
        <div class="form-row">
          <label for="modalNewPassword">Reset Password:</label>
          <input id="modalNewPassword" type="password" name="new_password" required class="form-control">
        </div>
      </form>
    </div>
    <div class="modal-actions">
      <button type="submit" form="modalChangePasswordForm" class="btn-primary">Change</button>
      <button type="button" id="cancelLoginModal" class="btn-cancel">Cancel</button>
    </div>
  </div>
</div>




    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Project Managers -->
  <?php if ($activeTab === 'project_managers'): ?>
  <div class="tab-panel active">
    <table class="styled-table" data-endpoint="../api/update_user_field.php">
      <thead><tr><th>Name</th><th>Username</th><th>Email</th><th>Color</th></tr></thead>
      <tbody>
        <?php foreach ($pmUsers as $u): ?>
        <tr data-id="<?= (int)$u['id'] ?>">
          <td class="editable" data-field="full_name" contenteditable><?= htmlspecialchars($u['full_name'], ENT_QUOTES) ?></td>
          <td class="editable" data-field="username" contenteditable><?= htmlspecialchars($u['username'], ENT_QUOTES) ?></td>
          <td class="editable" data-field="email" contenteditable><?= htmlspecialchars($u['email'], ENT_QUOTES) ?></td>
          <td>
            <input type="color" class="editable" data-field="color"
                   value="<?= htmlspecialchars($u['color'], ENT_QUOTES) ?>">
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <!-- Superintendents -->
  <?php if ($activeTab === 'superintendents'): ?>
  <div class="tab-panel active">
    <table class="styled-table" data-endpoint="/directory/api/update_user_field.php">
      <thead><tr><th>Name</th><th>Username</th><th>Email</th><th>Color</th></tr></thead>
      <tbody>
        <?php foreach ($suUsers as $u): ?>
        <tr data-id="<?= (int)$u['id'] ?>">
          <td class="editable" data-field="full_name" contenteditable><?= htmlspecialchars($u['full_name'], ENT_QUOTES) ?></td>
          <td class="editable" data-field="username" contenteditable><?= htmlspecialchars($u['username'], ENT_QUOTES) ?></td>
          <td class="editable" data-field="email" contenteditable><?= htmlspecialchars($u['email'], ENT_QUOTES) ?></td>
          <td>
            <input type="color" class="editable" data-field="color"
                   value="<?= htmlspecialchars($u['color'], ENT_QUOTES) ?>">
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <!-- Companies -->
  <?php if ($activeTab === 'companies'): ?>
  <div class="tab-panel active">
    <table class="styled-table" data-endpoint="/directory/api/update_company_field.php">
      <thead>
        <tr><th>Name</th><th>Trade</th><th>Website</th><th>Email</th><th>Phone</th><th>Created</th><th>Updated</th><th>Address</th></tr>
      </thead>
      <tbody>
        <?php foreach ($companies as $c): ?>
        <tr data-id="<?= (int)$c['id'] ?>">
          <td class="editable" data-field="name" contenteditable><?= htmlspecialchars($c['company_name'], ENT_QUOTES) ?></td>
          <td>
            <select class="editable" data-field="trade">
              <option value="">--</option>
              <?php foreach ($trades as $t): ?>
              <option value="<?= htmlspecialchars($t['name'], ENT_QUOTES) ?>" <?= $c['trade']=== $t['name']?'selected':''?>>
                <?= htmlspecialchars($t['name'], ENT_QUOTES) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </td>
          <td class="editable" data-field="website" contenteditable><?= htmlspecialchars($c['website'], ENT_QUOTES) ?></td>
          <td class="editable" data-field="email" contenteditable><?= htmlspecialchars($c['email'], ENT_QUOTES) ?></td>
          <td class="editable" data-field="phone" contenteditable><?= htmlspecialchars($c['phone'], ENT_QUOTES) ?></td>
          <td><?= htmlspecialchars($c['created_at'], ENT_QUOTES) ?></td>
          <td><?= htmlspecialchars($c['updated_at'], ENT_QUOTES) ?></td>
          <td class="editable" data-field="full_address" contenteditable><?= nl2br(htmlspecialchars($c['full_address'], ENT_QUOTES)) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <!-- Trades -->
  <?php if ($activeTab === 'trades'): ?>
  <div class="tab-panel active">
    <table class="styled-table" data-endpoint="/directory/api/update_trade_field.php">
      <thead><tr><th>Name</th><th>Color</th></tr></thead>
      <tbody>
        <?php foreach ($trades as $t): ?>
        <tr data-id="<?= (int)$t['id'] ?>">
          <td class="editable" data-field="name" contenteditable><?= htmlspecialchars($t['name'], ENT_QUOTES) ?></td>
          <td><input type="color" class="editable" data-field="color" value="<?= htmlspecialchars($t['color'], ENT_QUOTES) ?>"></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

</div>

<link rel="stylesheet" href="/directory/styles/directory.css">
<script src="/directory/js/directory.js"></script>
<script src="/assets/js/sw-register.js"></script>
