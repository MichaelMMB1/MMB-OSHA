<?php
// public/activity/checklog/activity_log.php

session_start();
require_once __DIR__ . '/../../../config/db_connect.php';

// … your existing header/permission checks …

// 1) Fetch everything plus the project name
$sql = "
  SELECT al.id,
         al.user_id,
         al.check_in_date,
         al.check_in_clock,
         al.check_out_date,
         al.check_out_clock,
         pa.project_name
    FROM public.activities_log AS al
    JOIN public.project_addresses AS pa
      ON al.project_id = pa.id
   ORDER BY al.id DESC
";
$res = pg_query($conn, $sql);

// 2) Start your table
echo '<table class="styled-table">';
echo '<thead><tr>
        <th>Name</th>
        <th>Verified</th>
        <th>Activity</th>
        <th>Actions</th>
      </tr></thead>';
echo '<tbody>';

// 3) Loop and format each row
while ($row = pg_fetch_assoc($res)) {
    // Build DateTime objects
    $dtIn  = new DateTime("{$row['check_in_date']} {$row['check_in_clock']}");
    $inFmt = $dtIn->format('m\/d\/y H:i:s');

    if ($row['check_out_date'] && $row['check_out_clock']) {
        $dtOut  = new DateTime("{$row['check_out_date']} {$row['check_out_clock']}");
        $outFmt = $dtOut->format('H:i:s');

        // Compute elapsed interval
        $diff   = $dtOut->diff($dtIn);
        $elapsed = sprintf(
            '%02d:%02d:%02d',
            $diff->h,
            $diff->i,
            $diff->s
        );

        $activityLabel = sprintf(
            '%s %s %s–%s (%s)',
            $dtIn->format('m\/d\/y'),
            htmlspecialchars($row['project_name']),
            $dtIn->format('H:i:s'),
            $dtOut->format('H:i:s'),
            $elapsed
        );
    } else {
        // still checked-in
        $activityLabel = sprintf(
            '%s %s %s (ongoing)',
            $dtIn->format('m\/d\/y'),
            htmlspecialchars($row['project_name']),
            $dtIn->format('H:i:s')
        );
    }

    echo '<tr>';
      // Name column (replace with your own user lookup)
      echo '<td>' . htmlspecialchars($row['user_id']) . '</td>';

      // Verified column (your existing logic)
      echo '<td>00:00</td>';

      // Activity column
      echo '<td>
              <span class="label">' 
                . $activityLabel . 
              '</span>
            </td>';

      // Actions column (your existing buttons)
      echo '<td>
              <!-- e.g. your MODIFY / DELETE links here -->
            </td>';
    echo '</tr>';
}

echo '</tbody></table>';
