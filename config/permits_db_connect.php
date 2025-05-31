<?php
// config/permits_db_connect.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Philly Permits database credentials
$host     = 'localhost';
$port     = '5432';
$dbname   = 'Philly Permits';  // contains a space
$user     = 'postgres';
$password = '*mBm?1821C';

// Build a PostgreSQL URI, percent-encoding the user, password, and dbname
$uri = sprintf(
    'postgresql://%s:%s@%s:%s/%s',
    rawurlencode($user),
    rawurlencode($password),
    $host,
    $port,
    rawurlencode($dbname)
);

// Attempt connection
$permConn = pg_connect($uri);

if ($permConn === false) {
    die('Error connecting to Philly Permits DB: ' . pg_last_error());
}
