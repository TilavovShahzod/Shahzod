<?php
require_once 'config/db.php';

$login = 'admin'; // O'zgartirmoqchi bo'lgan login
$new_password = 'admin123'; // Yangi parol

$hashed = password_hash($new_password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE login = ?");
$stmt->bind_param("ss", $hashed, $login);

if ($stmt->execute()) {
    echo "Parol muvaffaqiyatli yangilandi! Yangi parol: <b>$new_password</b>";
} else {
    echo "Xatolik: " . $conn->error;
}
?>