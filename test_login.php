<?php
// Test uchun to'g'ridan-to'g'ri ulanish
$conn = new mysqli('localhost', 'root', '', 'jizzax_hisobot');
if ($conn->connect_error) { die("DB Xatosi: " . $conn->connect_error); }

$login = 'admin';
$parol = '123456';

$stmt = $conn->prepare("SELECT * FROM users WHERE login = ?");
$stmt->bind_param("s", $login);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

if (!$row) {
    die("Xato: Bazada 'admin' login topilmadi!");
} else {
    echo "Login topildi! Active holati: " . $row['active'] . "<br>";
    
    if (password_verify($parol, $row['password_hash'])) {
        echo "<h1 style='color:green'>PAROL TO'G'RI! Tizimga kirish mumkin.</h1>";
    } else {
        echo "<h1 style='color:red'>PAROL XATO! Bazadagi hash: " . $row['password_hash'] . "</h1>";
        echo "Siz kiritgan parol: $parol";
    }
}
?>