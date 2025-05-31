<?php
// public/leads/leads.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

// 1) Main app DB (sessions, header logic)
require_once __DIR__ . '/../../../config/db_connect.php';

// 2) Permits DB connection (URI style)
require_once __DIR__ . '/../../../config/permits_db_connect.php';

// 3) Session & role checks
if (session_status() === PHP_SESSION_NONE) session_start();
$roleName  = $_SESSION['role'] ?? '';
$isAdmin   = strtolower($roleName) === 'admin';
$isPM      = strtolower($roleName) === 'project manager';
$canFilter = $isAdmin || $isPM;

// 4) Admin column‐control POST handler
if (
    $isAdmin
 && $_SERVER['REQUEST_METHOD'] === 'POST'
 && isset($_POST['control_order'])
) {
    foreach ($_POST['control_order'] as $col => $order) {
        $vis = isset($_POST['control_visible'][$col]) ? 't' : 'f';
        $ord = intval($order);
        pg_query_params(
            $permConn,
            'UPDATE public.leads_column_control
                SET is_visible    = $1,
                    display_order = $2
              WHERE column_name = $3',
            [$vis, $ord, $col]
        );
    }
    // rebuild the 6-month, ASC-sorted view
    pg_query($permConn, 'SELECT public.refresh_leads_view()');
}

?>
<div class="container page-content">
  <h1>Leads</h1>

  <?php if ($isAdmin): ?>
    <!-- Admin only: Column visibility & order -->
    <section style="margin-bottom:2rem; padding:1rem; border:1px solid #ccc; background:#fafafa;">
      <h2>Admin: Column Visibility &amp; Order</h2>
      <form method="post">
        <table class="styled-table" style="width:auto;">
          <thead>
            <tr>
              <th>Show?</th>
              <th>Column Name</th>
              <th>Order</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $ctrlRes = pg_query(
              $permConn,
              'SELECT column_name, display_order, is_visible
                 FROM public.leads_column_control
                ORDER BY display_order'
            );
            while ($ctrl = pg_fetch_assoc($ctrlRes)): ?>
            <tr>
              <td style="text-align:center;">
                <input
                  type="checkbox"
                  name="control_visible[<?= htmlspecialchars($ctrl['column_name']) ?>]"
                  <?= $ctrl['is_visible'] === 't' ? 'checked' : '' ?>
                >
              </td>
              <td><?= htmlspecialchars($ctrl['column_name']) ?></td>
              <td>
                <input
                  type="number"
                  name="control_order[<?= htmlspecialchars($ctrl['column_name']) ?>]"
                  value="<?= intval($ctrl['display_order']) ?>"
                  style="width:4rem;"
                >
              </td>
            </tr>
            <?php endwhile;
            pg_free_result($ctrlRes);
            ?>
          </tbody>
        </table>
        <button type="submit" class="btn btn-primary" style="margin-top:1rem;">
          Save Settings
        </button>
      </form>
    </section>
  <?php endif; ?>

  <?php
  // 6) Determine visible columns
  $visibleFields = [];
  $visRes = pg_query(
    $permConn,
    'SELECT column_name
       FROM public.leads_column_control
      WHERE is_visible = TRUE
      ORDER BY display_order'
  );
  if ($visRes) {
      while ($r = pg_fetch_assoc($visRes)) {
          $visibleFields[] = $r['column_name'];
      }
      pg_free_result($visRes);
  }
  // fallback if none selected
  if (empty($visibleFields)) {
      $tmp = pg_query($permConn, 'SELECT * FROM public.leads_filtered_view LIMIT 0');
      for ($i = 0, $n = pg_num_fields($tmp); $i < $n; $i++) {
          $visibleFields[] = pg_field_name($tmp, $i);
      }
      pg_free_result($tmp);
  }

  // 7) Gather filter options
  $ptRes = pg_query($permConn, 'SELECT DISTINCT permittype FROM public.permits ORDER BY permittype');
  $permitTypeOptions = $ptRes ? array_column(pg_fetch_all($ptRes), 'permittype') : [];
  $stRes = pg_query($permConn, 'SELECT DISTINCT status FROM public.permits ORDER BY status');
  $statusOptions = $stRes ? array_column(pg_fetch_all($stRes), 'status') : [];

  // 8) Read filter inputs
  $f7        = $canFilter && isset($_GET['filter_7']);
  $f14       = $canFilter && isset($_GET['filter_14']);
  $selType   = $canFilter ? ($_GET['permittype'] ?? '') : '';
  $selStatus = $canFilter ? ($_GET['status']     ?? '') : '';

  // 9) Build WHERE clause
  $where = [];
  if ($f7) {
    $where[] = "permitissuedate >= CURRENT_DATE - INTERVAL '7 days'";
  } elseif ($f14) {
    $where[] = "permitissuedate >= CURRENT_DATE - INTERVAL '14 days'";
  }
  if ($selType !== '') {
    $where[] = "permittype = " . pg_escape_literal($permConn, $selType);
  }
  if ($selStatus !== '') {
    $where[] = "status = " . pg_escape_literal($permConn, $selStatus);
  }
  $whereSQL = $canFilter && $where ? 'WHERE ' . implode(' AND ', $where) : '';

  // 10) Fetch data (always sorted ASC by view)
  $sql    = 'SELECT * FROM public.leads_filtered_view ' . $whereSQL . ' ORDER BY permitissuedate ASC';
  $dataRs = pg_query($permConn, $sql);
  if (!$dataRs) {
      die('Query error: ' . htmlspecialchars(pg_last_error($permConn)));
  }
  ?>

  <?php if ($canFilter): ?>
    <!-- 11) Filter UI (PMs & Admins) -->
    <form id="filterForm" method="get"
          style="margin-bottom:1rem; padding:1rem; background:#eef4f7; border:1px solid #c6d9e6;">
      <label style="margin-right:1rem;">
        <input type="checkbox" name="filter_7" <?= $f7 ? 'checked':'' ?>>
        Last 7 Days
      </label>
      <label style="margin-right:1rem;">
        <input type="checkbox" name="filter_14" <?= $f14 ? 'checked':'' ?>>
        Last 14 Days
      </label>
  <div style="margin-bottom:1rem; margin-top:1rem;">
      <label style="margin-right:1rem;">
        Permit Type:
        <select name="permittype">
   
          <option value="">All</option>
          <?php foreach ($permitTypeOptions as $opt): ?>
            <option value="<?= htmlspecialchars($opt) ?>"
              <?= $opt === $selType ? 'selected' : '' ?>>
              <?= htmlspecialchars($opt) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <label style="margin-right:1rem;">
        Status:
        <select name="status">
          <option value="">All</option>
          <?php foreach ($statusOptions as $opt): ?>
            <option value="<?= htmlspecialchars($opt) ?>"
              <?= $opt === $selStatus ? 'selected' : '' ?>>
              <?= htmlspecialchars($opt) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
        <div style="margin-bottom:1rem; margin-top:1rem;">
      <button type="submit" class="btn btn-primary">Apply Filters</button>
      <a href="leads.php" class="btn btn-secondary" style="margin-left:1rem;">Reset Filters</a>
    </form>
  <?php endif; ?>

  <!-- Global search (always visible) -->
  <div style="margin-bottom:1rem; margin-top:1rem;">
    <input
      type="text"
      id="globalSearch"
      placeholder="Search …"
      style="padding:.5rem; width:100%;"
    />
  </div>

  <!-- 13) Data table -->
  <table class="styled-table">
    <thead>
      <tr>
        <?php foreach ($visibleFields as $col): ?>
          <th><?= htmlspecialchars($col) ?></th>
        <?php endforeach; ?>
      </tr>
    </thead>
    <tbody>
      <?php while ($row = pg_fetch_assoc($dataRs)): ?>
        <tr>
          <?php foreach ($visibleFields as $col): ?>
            <td><?= htmlspecialchars($row[$col] ?? '') ?></td>
          <?php endforeach; ?>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const search = document.getElementById('globalSearch');
  if (!search) return;
  const rows = Array.from(document.querySelectorAll('table.styled-table tbody tr'));
  search.addEventListener('input', () => {
    const term = search.value.toLowerCase();
    rows.forEach(r => {
      const text = Array.from(r.children)
                        .map(td => td.textContent.toLowerCase())
                        .join(' ');
      r.style.display = text.includes(term) ? '' : 'none';
    });
  });
});
</script>

<?php
// 14) Shared footer
require_once __DIR__ . '/../../../includes/footer.php';
