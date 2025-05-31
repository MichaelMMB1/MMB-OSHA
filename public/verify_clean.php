<?php
$password = 'admin123';
$hash = password_hash($password, PASSWORD_BCRYPT);

echo "<pre>";
echo "Password: $password\n";
echo "Generated Hash: $hash\n";
var_dump(password_verify($password, $hash));
