<?php
require_once(__DIR__ . '/../../config/db_connect.php');

// simple sample seeder for check_log
$users    = [1,2,3];       // adjust to your real user IDs
$locations = ['Dashboard','Site A','Site B'];

foreach ($users as $uid) {
  for ($d = 0; $d < 7; $d++) {
    $date = date('Y-m-d', strtotime("-{$d} days"));
    $in   = date('H:i:s', strtotime('08:00'));
    $out  = date('H:i:s', strtotime('17:00'));
    $loc  = $locations[array_rand($locations)];

    $stmt = $mysqli->prepare("
      INSERT INTO `check_log`
        (`user_id`,`location`,`check_in_date`,`check_in_clock`,`check_out_date`,`check_out_clock`)
      VALUES (?,?,?,?,?,?)
    ");
    $stmt->bind_param('isssss', $uid, $loc, $date, $in, $date, $out);
    $stmt->execute();
    $stmt->close();
  }
}

echo "Seeded check_log with sample data.";
