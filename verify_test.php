<?php
$passwordInput = 'admin123';
$storedHash = '$2y$10$UMNfCyZMF1rp3zZgHrcOqu/2RP2lDgSgR2u9QxLt4SiP2jAgF7M7m';

echo "<pre>";
echo "Password: $passwordInput\n";
echo "Hash: $storedHash\n";
var_dump(password_verify($passwordInput, $storedHash));
