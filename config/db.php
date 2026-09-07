<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'jizzax_hisobot');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Ulanish xatosi: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

session_start();
// config/db.php oxiriga qo'shing
if (isset($_GET['test_db'])) {
    $test = $conn->query("SELECT * FROM users LIMIT 1");
    if ($test) {
        echo "Baza ishlayapti!<br>";
        $row = $test->fetch_assoc();
        echo "Mavjud login: " . $row['login'] . "<br>";
        echo "Parol shifrlangan: " . $row['password_hash'];
    } else {
        echo "Xato: " . $conn->error;
    }
    exit;
}
?>  
