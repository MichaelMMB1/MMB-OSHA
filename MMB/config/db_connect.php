<?php
$host = 'localhost';
$db   = 'test_users_db';
$user = 'root';
$pass = '*mBm?1821C'; // <-- your actual MySQL root password

$mysqli = new mysqli($host, $user, $pass, $db);


if ($mysqli->connect_error) {
    die('Database connection failed: ' . $mysqli->connect_error);
}

date_default_timezone_set('America/New_York');
?>
