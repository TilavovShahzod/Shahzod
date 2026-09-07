<?php
// Sessiya va rol tekshiruvi
if (!isset($_SESSION['user_id'])) {
    header("Location: kirish.php");
    exit;
}

$role = $_SESSION['role'] ?? 'user';
$fio = $_SESSION['fio'] ?? 'Foydalanuvchi';
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="uz">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Boshqaruv paneli</title>
<style>
/* GLOBAL STILLAR */
:root {
    --bg: #f4f6f9;
    --white: #ffffff;
    --border: #e5e7eb;
    --text-dark: #111827;
    --text-gray: #6b7280;
    --blue: #1c5cab;
    --blue-dark: #12395C;
    --green: #16a34a;
    --red: #dc2626;
    --yellow: #d97706;
    --shadow: 0 1px 3px rgba(0,0,0,0.1), 0 1px 2px rgba(0,0,0,0.06);
}

* { box-sizing: border-box; margin: 0; padding: 0; }

body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: var(--bg);
    color: var(--text-dark);
    display: flex;
    min-height: 100vh;
    
}

/* CHAP MENYU (SIDEBAR) */
.sidebar {
    width: 270px;
    background: linear-gradient(180deg, var(--blue-dark) 0%, #0C2A45 100%);
    color: white;
    display: flex;
    flex-direction: column;
    position: fixed;
    top: 0;
    left: 0;
    height: 100vh;
    z-index: 1000;
    transition: transform 0.3s ease;
}

.sidebar-header {
    padding: 20px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    display: flex;
    align-items: center;
    gap: 15px;
}

.sidebar-header img {
    width: 90px;
    height: 120px;
    object-fit: contain;
}

.sidebar-header h2 {
    font-size: 16px;
    line-height: 1.4;
    font-weight: 700;
    text-transform: uppercase;
}

.sidebar-header small {
    font-size: 12px;
    color: #8FB3D6;
    display: block;
}

/* MENYU HAVOLALARI */
.sidebar-nav {
    flex: 1;
    padding: 15px 10px;
    overflow-y: auto;
}

.nav-section {
    margin-bottom: 20px;
   
}

.nav-title {
    font-size: 16px;
    color: #f7fbfc;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 10px 15px;
    font-weight: 600;
    font-family: Cambria, Cochin, Georgia, Times, 'Times New Roman', serif;
}

.nav-link {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 15px;
    color: #cbd5e1;
    text-decoration: none;
    border-radius: 8px;
    margin-bottom: 4px;
    font-size: 18px;
    transition: all 0.2s ease;
}

.nav-link:hover {
    background: rgba(255,255,255,0.1);
    color: white;
}

.nav-link.active {
    background: var(--blue);
    color: white;
    box-shadow: 0 4px 10px rgba(28, 92, 171, 0.4);
}

.nav-link svg {
    width: 20px;
    height: 20px;
    flex-shrink: 0;
}

/* FOYDALANUVCHI PASTKI QISMI */
.sidebar-footer {
    padding: 15px;
    border-top: 1px solid rgba(255,255,255,0.1);
}

.user-info {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
}

.avatar {
    width: 40px;
    height: 40px;
    background: var(--blue);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 18px;
    text-transform: uppercase;
}

.user-info b { display: block; font-size: 14px; }
.user-info small { color: #8FB3D6; font-size: 12px; }

.logout-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    background: rgba(220, 38, 38, 0.2);
    color: #fca5a5;
    padding: 10px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    text-decoration: none;
    font-size: 14px;
    transition: all 0.2s;
}

.logout-btn:hover {
    background: rgba(220, 38, 38, 0.3);
    color: white;
}

/* O'NG ASOSIY QISM */
.main-content {
    margin-left: 260px;
    flex: 1;
    padding: 20px;
    background: var(--bg);
}

/* MOBIL QURILMALAR UCHUN */
@media (max-width: 900px) {
    .sidebar { transform: translateX(-100%); }
    .main-content { margin-left: 0; }
    .sidebar.open { transform: translateX(0); }
    .menu-toggle { display: block !important; }
}

.menu-toggle {
    display: none;
    position: fixed;
    top: 15px;
    left: 15px;
    z-index: 1001;
    background: var(--blue);
    color: white;
    border: none;
    padding: 10px;
    border-radius: 5px;
    cursor: pointer;
}

</style>
</head>
<body>

<!-- Mobil menyu tugmasi -->
<button class="menu-toggle" onclick="document.querySelector('.sidebar').classList.toggle('open')">☰</button>

<!-- CHAP MENYU -->
<aside class="sidebar">
    <div class="sidebar-header">
        <!-- Gerb rasmini o'z manzilingiz bilan almashtiring -->
        <img src="q.png" alt="Gerb">
        <div>
            <h2 style="text-align:center">Yashirin iqtisodiyot</h2>
           <div style="text-align: center; color: #8cdae7; font-family:cambria">
           <i> 
           <h4>Qarshi kurashish</h4>
            <h4>sohasida shtabi</h4>
            </i>
           </div>
            
        </div>
    </div>
<div style="text-align:center; font-family: cambria Shash examination;"><h5>JIZZAX VILOYAT PROKURATURASI</h5></div>
    <nav class="sidebar-nav">
        <div class="nav-section">
            <div class="nav-title">Asosiy</div>
            
            <a href="index.php" class="nav-link <?php echo $current_page == 'index.php' ? 'active' : ''; ?>">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                Umumiy ko'rinish
            </a>

            <?php if ($role == 'viloyat'): ?>
            <a href="tumanlar.php" class="nav-link <?php echo $current_page == 'tumanlar.php' ? 'active' : ''; ?>">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                Tumanlar jadvali
            </a>
            <?php endif; ?>

            <a href="hisobot.php" class="nav-link <?php echo $current_page == 'hisobot.php' ? 'active' : ''; ?>">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"/></svg>
                Hisobot kiritish
            </a>
        </div>

        <?php if ($role == 'viloyat'): ?>
        <div class="nav-section">
            <div class="nav-title">Boshqaruv</div>
            
            <a href="nazorat.php" class="nav-link <?php echo $current_page == 'nazorat.php' ? 'active' : ''; ?>">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                Nazorat qoidalari
            </a>
            
            <a href="foydalanuvchilar.php" class="nav-link <?php echo $current_page == 'foydalanuvchilar.php' ? 'active' : ''; ?>">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                Foydalanuvchilar
            </a>

            <a href="jurnal.php" class="nav-link <?php echo $current_page == 'jurnal.php' ? 'active' : ''; ?>">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Tizim jurnali
            </a>
        </div>
        <?php endif; ?>
    </nav>

    <div class="sidebar-footer" >
        <div class="user-info">
            <div class="avatar"><?php echo strtoupper(substr($fio, 0, 1)); ?></div>
            <div style="text-align: center;">
                <b><?php echo htmlspecialchars($fio); ?></b>
                <small><?php echo $role == 'viloyat' ? 'Viloyat administratori' : 'Tuman xodimi'; ?></small>
            </div>
        </div>
        <a href="logout.php" class="logout-btn">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="18" height="18"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
            Chiqish
        </a>
    </div>
</aside>

<!-- ASOSIY KONTENT -->
<div class="main-content">