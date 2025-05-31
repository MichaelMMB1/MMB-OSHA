<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);


require_once __DIR__ . '/../config/db_connect.php';

if (empty($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}
$userId = (int)$_SESSION['user_id'];

$filter    = $_GET['filter'] ?? 'current';    // 'current' or 'last'
$offsetMap = ['current' => 0, 'last' => -1];
$weekOff   = $offsetMap[$filter] ?? 0;

$weekStart = date('Y-m-d', strtotime("monday this week {$weekOff} week"));
$weekEnd   = date('Y-m-d', strtotime("sunday this week {$weekOff} week"));

// ── Fetch Activities + Address Columns ──
$sql = "
  SELECT
    al.check_in_date,
    al.check_in_clock,
    al.check_out_clock,
    pa.project_name,
    pa.address_line1,
    pa.address_line2,
    pa.city,
    pa.state,
    pa.zip_code,
    pm.color AS tag_color
  FROM public.activities_log AS al
  JOIN public.project_addresses AS pa
    ON pa.id = al.project_id
  JOIN public.users AS pm
    ON pm.id = pa.project_manager_id
  WHERE al.user_id = \$1
    AND al.check_in_date BETWEEN \$2 AND \$3
  ORDER BY al.check_in_date, al.check_in_clock
";
$res = pg_query_params($conn, $sql, [
    $userId,
    $weekStart,
    $weekEnd,
]);

// bucket logs by date
$logs = [];
if ($res) {
    while ($r = pg_fetch_assoc($res)) {
        // build address
        $parts = array_filter([
          $r['address_line1'],
          $r['address_line2'],
          $r['city'],
          $r['state'],
          $r['zip_code'],
        ]);
        $r['project_address'] = implode(', ', $parts);

        // compute contrast color
        $bg = $r['tag_color'] ?: '#999';
        list($R,$G,$B) = sscanf($bg,'#%02x%02x%02x');
        $luma = ($R*299 + $G*587 + $B*114) / 1000;
        $r['text_color'] = $luma > 128 ? '#000' : '#fff';

        $logs[$r['check_in_date']][] = $r;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>My Activity Schedule</title>

    <style>
    /* center the Schedule heading */
    h2.schedule-title {
      text-align: center;
      margin: 1rem 0;
    }

    /* tabs */
    .tabs {
      display: flex;
      justify-content: center;
      gap: 1rem;
      margin-bottom: 1rem;
    }
    .tab-btn {
      text-decoration: none;
      padding: 0.5rem 1rem;
      font-family: system-ui, sans-serif;
      font-weight: 600;
      color: #444;
      border-radius: 4px;
      border-bottom: 2px solid transparent;
    }
    .tab-btn.active {
      border-color: orange;
      color: #000;
    }

    /* week-range */
    .week-range {
      width: 100%;
      box-sizing: border-box;
      padding: 1rem;
      margin: 0.5rem 0;
      font-size: 1rem;
      line-height: 1.4;
      text-align: center;
      font-weight: bold;
    }

    /* split entry into two columns */
    /* history page “tags” styling */
.assignment-tag {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  padding: 1rem;           /* full 1rem interior padding */
  margin: 0.5rem 0;
  background: #fff;        /* or your preferred neutral bg */
  border-radius: 8px;
  box-sizing: border-box;
}

.assignment-tag .time-range {
  flex: 1;
  text-align: left;
  font-family: monospace;
}

.assignment-tag .project-info {
  flex: 1;
  text-align: right;
  font-family: system-ui, sans-serif;
}

.assignment-info .project-name {
  font-weight: 600;
  display: block;
}

.assignment-info .project-address {
  display: block;
  font-size: 0.9rem;
  color: #666;             /* consistent, readable color */
  margin-top: 0.25rem;
}

.assignment-none {
  font-size: 1.5rem;
  color: #999;
  text-align: center;
  margin: 0.5rem 0;
}


    .schedule-day {
      margin: 1.5rem 0;
    }
    .day-label {
      font-weight: bold;
      margin-bottom: 0.5rem;
      font-size: 1.05rem;
    }
  </style>

</head>
<body>



  <h2 class="schedule-title">History</h2>

  <div class="tabs">
    <a href="?filter=current"
       class="tab-btn <?= $filter==='current' ? 'active':'' ?>">This Week</a>
    <a href="?filter=last"
       class="tab-btn <?= $filter==='last' ? 'active':'' ?>">Last Week</a>
  </div>

  <div class="week-range">
    <?= date('m/d/y', strtotime($weekStart)) ?> – <?= date('m/d/y', strtotime($weekEnd)) ?>
  </div>

<?php for ($d = 0; $d < 7; $d++):
    $date     = date('Y-m-d', strtotime("$weekStart +{$d} day"));
    $dayName  = date('l',    strtotime($date));
    $entries  = $logs[$date] ?? [];
?>
  <div class="schedule-day">
    <div class="day-label">
      <?= htmlspecialchars($dayName) ?> (<?= date('m/d', strtotime($date)) ?>)
    </div>

    <?php if ($entries): ?>
      <?php foreach ($entries as $act):
          $in    = date('H:i', strtotime($act['check_in_clock']));
          $out   = $act['check_out_clock']
                 ? date('H:i', strtotime($act['check_out_clock']))
                 : '–';
          $range = "{$in} – {$out}";

          // build full address
          $parts = array_filter([
            $act['address_line1'],
            $act['address_line2'],
            $act['city'],
            $act['state'],
            $act['zip_code'],
          ]);
          $address = implode(', ', $parts);
      ?>
        <div class="assignment-tag">
          <span class="time-range"><?= htmlspecialchars($range) ?></span>
          <span class="project-info assignment-info">
            <span class="project-name"><?= htmlspecialchars($act['project_name']) ?></span>
            <span class="project-address"><?= htmlspecialchars($address) ?></span>
          </span>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div class="assignment-none">–</div>
    <?php endif; ?>
  </div>
<?php endfor; ?>




</body>
</html>
