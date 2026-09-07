<?php
require_once 'config/db.php';

$parol = 'admin123';
$hash = password_hash($parol, PASSWORD_DEFAULT);

// 1-admin va 2-admin parollarini yangilash
$conn->query("UPDATE users SET password_hash = '$hash' WHERE login IN ('admin', 'admin2', 'viloyat')");

echo "✅ Parollar yangilandi!";
echo "<br>Login: admin, admin2, viloyat";
echo "<br>Parol: admin123";
?>