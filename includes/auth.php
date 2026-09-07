<?php
// config/db.php faylini chaqiramiz (u yerda $conn va session_start bor)
require_once __DIR__ . '/../config/db.php';

// Tizimga kirganligini tekshirish
function check_auth() {
    if (empty($_SESSION['user_id'])) {
        header("Location: kirish.php");
        exit;
    }
}

// Faqat admin (viloyat) uchun tekshirish
function check_admin() {
    check_auth();
    if ($_SESSION['role'] !== 'viloyat') {
        header("Location: kiritish.php");
        exit;
    }
}

// Jurnalga yozish
function jurnal($amal, $tafsilot = '') {
    global $conn;

    $user_id = $_SESSION['user_id'] ?? 0; 
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    
    $stmt = $conn->prepare("INSERT INTO jurnal (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
    
    if ($stmt) {
        $stmt->bind_param("isss", $user_id, $amal, $tafsilot, $ip);
        $stmt->execute();
        $stmt->close();
    }
}

// Login qilish (ACTIVE SHARTISIZ)
function login($login, $password) {
    global $conn;
    
    // Bu yerda "active = 1" sharti olib tashlandi!
    $stmt = $conn->prepare("SELECT * FROM users WHERE login = ?");
    $stmt->bind_param("s", $login);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        if (password_verify($password, $row['password_hash'])) {
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['role'] = $row['role'];
            $_SESSION['fio'] = $row['fio'];
            $_SESSION['hudud_id'] = $row['hudud_id'];
            
            $conn->query("UPDATE users SET last_login = NOW() WHERE id = {$row['id']}");
            
            jurnal('kirish', 'Tizimga kirdi');
            
            return true;
        } else {
            jurnal('kirish_xato', "Login: $login (Parol xato)");
        }
    } else {
        jurnal('kirish_xato', "Login: $login (Topilmadi)");
    }
    
    return false;
}

// Chiqish
function logout() {
    jurnal('chiqish', 'Tizimdan chiqdi');
    session_destroy();
    header("Location: kirish.php");
    exit;
}
?>