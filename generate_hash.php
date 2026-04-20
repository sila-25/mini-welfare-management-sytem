<?php
$password = 'SuperAdmin2024!';
$hash = password_hash($password, PASSWORD_DEFAULT);
echo "Password: " . $password . "<br>";
echo "Hash: " . $hash . "<br>";
echo "<hr>";
echo "Copy this hash into your SQL: <br>";
echo "<code style='background: #f4f4f4; padding: 10px; display: block;'>" . $hash . "</code>";
?>