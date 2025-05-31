<?php
require_once __DIR__ . '/../../config/db_connect.php';

$sql = "SELECT id, username, password FROM users";
$result = pg_query($conn, $sql);

while ($row = pg_fetch_assoc($result)) {
    $id       = $row['id'];
    $username = $row['username'];
    $plain    = $row['password'];

    // Skip if it's already hashed (starts with $2y$ which bcrypt uses)
    if (str_starts_with($plain, '$2y$')) {
        echo "Skipping '$username' - already hashed.<br>";
        continue;
    }

    $hash = password_hash($plain, PASSWORD_DEFAULT);
    $update = "UPDATE users SET password = $1 WHERE id = $2";
    $res = pg_query_params($conn, $update, [$hash, $id]);

    if ($res) {
        echo "✅ Updated '$username'<br>";
    } else {
        echo "❌ Error updating '$username'<br>";
    }
}
?>
