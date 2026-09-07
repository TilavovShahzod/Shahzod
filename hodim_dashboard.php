<?php
require_once 'includes/auth.php';

// Faqat tuman xodimi kirishi mumkin
if ($_SESSION['role'] !== 'tuman') {
    header("Location: index.php");
    exit;
}

// Hodimning tumanini avtomatik aniqlash
$hudud_id = $_SESSION['hudud_id'];
$tuman_nomi = 'Tuman';

if ($hudud_id) {
    $stmt = $conn->prepare("SELECT * FROM tumanlar WHERE id = ?");
    $stmt->bind_param("i", $hudud_id);
    $stmt->execute();
    $tuman = $stmt->get_result()->fetch_assoc();
    if ($tuman) $tuman_nomi = $tuman['nom'];
}

// Til tanlash (Lotin / Kirill)
$til = $_GET['til'] ?? $_COOKIE['til'] ?? 'kr';
if ($til === 'kr') {
    setcookie('til', 'kr', time() + 31536000, '/');
} else {
    setcookie('til', 'lot', time() + 31536000, '/');
}

// Lotindan Kirillga o'tkazish
function kirill($matn) {
    $birikmalar = [
        "O'" => "Ў", "o'" => "ў", "G'" => "Ғ", "g'" => "ғ",
        "Sh" => "Ш", "sh" => "ш", "Ch" => "Ч", "ch" => "ч",
        "Ya" => "Я", "ya" => "я", "Yo" => "Ё", "yo" => "ё",
        "Yu" => "Ю", "yu" => "ю", "Ye" => "Е", "ye" => "е"
    ];
    foreach ($birikmalar as $lot => $kir) $matn = str_replace($lot, $kir, $matn);

    $harflar = [
        'a'=>'а','b'=>'б','d'=>'д','e'=>'е','f'=>'ф','g'=>'г','h'=>'ҳ','i'=>'и','j'=>'ж','k'=>'к','l'=>'л','m'=>'м',
        'n'=>'н','o'=>'о','p'=>'п','q'=>'қ','r'=>'р','s'=>'с','t'=>'т','u'=>'у','v'=>'в','x'=>'х','y'=>'й','z'=>'з',
        'A'=>'А','B'=>'Б','D'=>'Д','E'=>'Е','F'=>'Ф','G'=>'Г','H'=>'Ҳ','I'=>'И','J'=>'Ж','K'=>'К','L'=>'Л','M'=>'М',
        'N'=>'Н','O'=>'О','P'=>'П','Q'=>'Қ','R'=>'Р','S'=>'С','T'=>'Т','U'=>'У','V'=>'В','X'=>'Х','Y'=>'Й','Z'=>'З'
    ];
    foreach ($harflar as $lot => $kir) $matn = str_replace($lot, $kir, $matn);
    return str_replace("'", "ъ", $matn);
}

// Matnni tanlangan tilga o'tkazish
function matn($lotin) {
    global $til;
    if ($til === 'kr') return kirill($lotin);
    return $lotin;
}

// Joriy sana
$sana = date('Y-m-d');

// Hodimning shu kungi ma'lumotlari
$stats = [
    'prognoz' => 0,
    'soliq_jami' => 0,
    'soliq_kunlik' => 0,
    'soliq_qoldiq' => 0,
    'bozor_soni' => 0,
    'bozor_raqam' => 0,
    'bozor_vm' => 0,
    'bozor_soliq' => 0,
    'und_jami' => 0,
    'und_dsb' => 0,
    'und_mib' => 0,
    'status' => 'qoralama'
];

// Hodimning ma'lumotlarini olish
$stmt = $conn->prepare("SELECT * FROM hisobotlar WHERE tuman_id = ? AND sana = ?");
$stmt->bind_param("is", $hudud_id, $sana);
$stmt->execute();
$hisobot = $stmt->get_result()->fetch_assoc();

if ($hisobot) {
    $stats['prognoz'] = $hisobot['oylik_prognoz_admin'] ?? 0;
    $stats['soliq_jami'] = $hisobot['soliq_jami'] ?? 0;
    $stats['soliq_kunlik'] = $hisobot['soliq_kunlik'] ?? 0;
    $stats['soliq_qoldiq'] = $stats['prognoz'] - $stats['soliq_jami'];
    $stats['bozor_soni'] = $hisobot['bozor_soni'] ?? 0;
    $stats['bozor_raqam'] = $hisobot['bozor_raqam'] ?? 0;
    $stats['bozor_vm'] = $hisobot['bozor_vm'] ?? 0;
    $stats['bozor_soliq'] = $hisobot['bozor_soliq'] ?? 0;
    $stats['und_jami'] = $hisobot['und_jami'] ?? 0;
    $stats['und_dsb'] = $hisobot['und_dsb'] ?? 0;
    $stats['und_mib'] = $hisobot['und_mib'] ?? 0;
    $stats['status'] = $hisobot['status'] ?? 'qoralama';
}

// Oxirgi 7 kun dinamikasi (faqat shu tuman uchun)
$dinamika = [];
$stmt = $conn->prepare("SELECT sana, SUM(soliq_kunlik) as s FROM hisobotlar WHERE tuman_id = ? AND sana >= DATE_SUB(?, INTERVAL 7 DAY) GROUP BY sana ORDER BY sana");
$stmt->bind_param("is", $hudud_id, $sana);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $dinamika[] = ['sana' => $row['sana'], 'qiymat' => $row['s'] ?? 0];

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="uz">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Hodim Dashboard</title>
<style>
/* ZAMONAVIY PROFESSIONAL DIZAYN */
:root {
    --bg: #f0f4f8;
    --white: #ffffff;
    --border: #e2e8f0;
    --text-dark: #1e293b;
    --text-gray: #64748b;
    --blue: #2563eb;
    --blue-dark: #1e40af;
    --green: #10b981;
    --green-dark: #047857;
    --red: #ef4444;
    --red-dark: #b91c1c;
    --yellow: #f59e0b;
    --purple: #8b5cf6;
    --sidebar-bg: #0f172a;
    --sidebar-hover: #1e293b;
    --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
    --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);
    --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05);
}

* { box-sizing: border-box; margin: 0; padding: 0; }

body {
    font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: var(--bg);
    color: var(--text-dark);
    display: flex;
    min-height: 100vh;
}

/* MENYU (SIDEBAR) - PROFESSIONAL */
.sidebar {
    width: 270px;
    background: linear-gradient(180deg, var(--sidebar-bg) 0%, #1e293b 100%);
    color: white;
    display: flex;
    flex-direction: column;
    position: fixed;
    top: 0;
    left: 0;
    height: 100vh;
    z-index: 1000;
    border-right: 1px solid rgba(255,255,255,0.05);
}

.sidebar-header {
    padding: 30px 20px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    text-align: center;
}

.sidebar-header img {
    width: 100px;
    height: 100px;
    object-fit: contain;
    margin-bottom: 15px;
    filter: drop-shadow(0 4px 6px rgba(0,0,0,0.3));
}

.sidebar-header h2 {
    font-size: 15px;
    line-height: 1.5;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    background: linear-gradient(90deg, #60a5fa, #a78bfa);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.sidebar-header p {
    font-size: 12px;
    color: #94a3b8;
    margin-top: 8px;
}

nav {
    flex: 1;
    overflow-y: auto;
    padding: 15px 10px;
}

.nav-title {
    font-size: 11px;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 1px;
    padding: 15px 15px 8px;
    font-weight: 600;
}

.nav-link {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 15px;
    color: #94a3b8;
    text-decoration: none;
    border-radius: 10px;
    margin-bottom: 4px;
    font-size: 14px;
    transition: all 0.3s ease;
    border: 1px solid transparent;
}

.nav-link:hover {
    background: var(--sidebar-hover);
    color: white;
    transform: translateX(3px);
}

.nav-link.active {
    background: linear-gradient(90deg, var(--blue), var(--blue-dark));
    color: white;
    box-shadow: 0 4px 15px rgba(37, 99, 235, 0.4);
    border-color: rgba(255,255,255,0.1);
}

.nav-link svg { width: 20px; height: 20px; flex-shrink: 0; }

/* FOOTER (PASTKI CHAP BURCHAK) */
.sidebar-footer {
    padding: 20px;
    border-top: 1px solid rgba(255,255,255,0.1);
    background: rgba(0,0,0,0.2);
}

.til-tanla {
    display: flex;
    gap: 5px;
    margin-bottom: 15px;
}

.til-tanla a {
    flex: 1;
    text-align: center;
    padding: 8px;
    font-size: 13px;
    color: #94a3b8;
    text-decoration: none;
    background: rgba(255,255,255,0.05);
    border-radius: 8px;
    transition: all 0.3s;
}

.til-tanla a.active {
    background: var(--blue);
    color: white;
    font-weight: bold;
    box-shadow: 0 2px 10px rgba(37, 99, 235, 0.3);
}

.tuman-info {
    text-align: center;
    margin-bottom: 15px;
    padding: 10px;
    background: rgba(255,255,255,0.05);
    border-radius: 10px;
}

.tuman-info b {
    display: block;
    font-size: 16px;
    color: white;
}

.tuman-info span {
    font-size: 12px;
    color: #94a3b8;
}

.logout-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    background: linear-gradient(90deg, var(--red), var(--red-dark));
    color: white;
    padding: 12px;
    border: none;
    border-radius: 10px;
    cursor: pointer;
    text-decoration: none;
    font-size: 14px;
    font-weight: 600;
    transition: all 0.3s;
    box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
}

.logout-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(239, 68, 68, 0.4);
}

/* KONTENT - ASOSIY QISM */
.main-content {
    margin-left: 270px;
    flex: 1;
    padding: 40px;
    background: var(--bg);
}

.dashboard-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 20px;
    margin-bottom: 40px;
}

.dashboard-header h2 {
    font-size: 32px;
    font-weight: 800;
    color: var(--text-dark);
    letter-spacing: -0.5px;
}

.dashboard-header p {
    font-size: 15px;
    color: var(--text-gray);
    margin-top: 8px;
    font-weight: 500;
}

.dashboard-header .date-picker {
    background: var(--white);
    border: 2px solid var(--border);
    border-radius: 12px;
    padding: 12px 20px;
    font-size: 15px;
    font-weight: 500;
    color: var(--text-dark);
    box-shadow: var(--shadow-sm);
    transition: all 0.3s;
}

.dashboard-header .date-picker:focus {
    border-color: var(--blue);
    outline: none;
    box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
}

/* STATISTIK KARTALAR - ZAMONAVIY */
.stat-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 20px;
    margin-bottom: 40px;
}

.stat-card {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 25px;
    box-shadow: var(--shadow-sm);
    position: relative;
    overflow: hidden;
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 5px;
    background: var(--blue);
}

.stat-card.green::before { background: linear-gradient(90deg, var(--green), var(--green-dark)); }
.stat-card.yellow::before { background: linear-gradient(90deg, var(--yellow), #d97706); }
.stat-card.red::before { background: linear-gradient(90deg, var(--red), var(--red-dark)); }

.stat-card h3 {
    font-size: 13px;
    color: var(--text-gray);
    text-transform: uppercase;
    letter-spacing: 1px;
    font-weight: 600;
    margin-bottom: 15px;
}

.stat-card .value {
    font-size: 36px;
    font-weight: 800;
    color: var(--text-dark);
    line-height: 1;
    margin-bottom: 10px;
}

.stat-card .value small {
    font-size: 14px;
    font-weight: 500;
    color: var(--text-gray);
    margin-left: 5px;
}

.stat-card .sub {
    font-size: 13px;
    color: var(--text-gray);
    font-weight: 500;
}

/* PANELLAR - PROFESSIONAL */
.panel {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: 16px;
    box-shadow: var(--shadow-sm);
    margin-bottom: 30px;
    overflow: hidden;
}

.panel-header {
    padding: 20px 25px;
    border-bottom: 1px solid var(--border);
    background: #f8fafc;
}

.panel-header h3 {
    margin: 0;
    font-size: 20px;
    font-weight: 700;
    color: var(--text-dark);
}

.panel-header p {
    margin: 5px 0 0;
    font-size: 14px;
    color: var(--text-gray);
}

.panel-body {
    padding: 25px;
}

/* HISOBOT HOLATI */
.status-box {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    flex-wrap: wrap;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    border-radius: 12px;
    font-weight: 600;
    font-size: 15px;
}

.status-badge.success {
    background: #d1fae5;
    color: #065f46;
    border: 1px solid #a7f3d0;
}

.status-badge.error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
}

.status-badge.warning {
    background: #fef3c7;
    color: #92400e;
    border: 1px solid #fde68a;
}

.status-badge.info {
    background: #e0e7ff;
    color: #3730a3;
    border: 1px solid #c7d2fe;
}

/* GRAFIK - CHIROYLI */
.chart-container {
    height: 250px;
    display: flex;
    align-items: flex-end;
    gap: 12px;
    padding: 20px 0 40px;
}

.chart-bar {
    flex: 1;
    background: linear-gradient(180deg, var(--blue), var(--blue-dark));
    border-radius: 8px 8px 0 0;
    position: relative;
    min-height: 8px;
    transition: all 0.3s ease;
    box-shadow: 0 4px 10px rgba(37, 99, 235, 0.2);
}

.chart-bar:hover {
    opacity: 0.9;
    transform: scaleY(1.02);
    transform-origin: bottom;
}

.chart-bar span {
    position: absolute;
    bottom: -30px;
    left: 50%;
    transform: translateX(-50%);
    font-size: 12px;
    font-weight: 600;
    color: var(--text-gray);
}

/* IKKI USTUNLI JADVAL */
.grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
    margin-bottom: 30px;
}

@media (max-width: 1000px) {
    .grid-2 { grid-template-columns: 1fr; }
}

/* INFO BOX'LAR */
.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
}

.info-item {
    background: #f8fafc;
    padding: 20px;
    border-radius: 12px;
    text-align: center;
    border: 1px solid var(--border);
    transition: all 0.3s;
}

.info-item:hover {
    border-color: var(--blue);
    box-shadow: var(--shadow-md);
}

.info-item b {
    display: block;
    font-size: 28px;
    font-weight: 800;
    color: var(--blue);
}

.info-item span {
    font-size: 13px;
    color: var(--text-gray);
    margin-top: 5px;
    display: block;
    font-weight: 500;
}

/* BO'SH HOLAT */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--text-gray);
}

.empty-state svg {
    width: 60px;
    height: 60px;
    margin-bottom: 20px;
    color: #cbd5e1;
}

.empty-state b {
    display: block;
    font-size: 18px;
    color: var(--text-dark);
    margin-bottom: 10px;
}

/* FOOTER NOTE */
.footer-note {
    margin-top: 40px;
    padding-top: 25px;
    border-top: 2px solid var(--border);
    font-size: 13px;
    color: var(--text-gray);
    line-height: 1.8;
    text-align: center;
}

.footer-note b {
    color: var(--text-dark);
}

@media (max-width: 900px) {
    .sidebar { transform: translateX(-100%); }
    .main-content { margin-left: 0; }
}
</style>
</head>
<body>

<!-- MENYU (SIDEBAR) -->
<aside class="sidebar">
    <div class="sidebar-header">
        <img src="q.png" alt="Gerb">
        <h2><?php echo matn('Яширин иқтисодиётга қарши курашиш штаби'); ?></h2>
        <p style="font-size: 12px; color: #8FB3D6; margin-top: 5px;"><?php echo matn('Ҳисобот тизими'); ?></p>
    </div>

    <nav>
        <div class="nav-title"><?php echo matn('Ҳисобот'); ?></div>
        <a href="hodim_dashboard.php" class="nav-link <?php echo $current_page == 'hodim_dashboard.php' ? 'active' : ''; ?>">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
            <?php echo matn('Ҳисобот'); ?>
        </a>

        <a href="kiritish.php" class="nav-link <?php echo $current_page == 'kiritish.php' ? 'active' : ''; ?>">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <?php echo matn('Кунлик киритиш'); ?>
        </a>

        <a href="tarix.php" class="nav-link <?php echo $current_page == 'tarix.php' ? 'active' : ''; ?>">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <?php echo matn('Менинг тарихим'); ?>
        </a>

        <a href="dinamika.php" class="nav-link <?php echo $current_page == 'dinamika.php' ? 'active' : ''; ?>">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
            <?php echo matn('Динамика'); ?>
        </a>

        <div class="nav-title"><?php echo matn('Тизим'); ?></div>
        <a href="sozlamalar.php" class="nav-link <?php echo $current_page == 'sozlamalar.php' ? 'active' : ''; ?>">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            <?php echo matn('Созламалар'); ?>
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="til-tanla">
            <a href="?til=lot" class="<?php echo $til === 'lot' ? 'active' : ''; ?>">Lotin</a>
            <a href="?til=kr" class="<?php echo $til === 'kr' ? 'active' : ''; ?>">Кирилл</a>
        </div>
        <div class="tuman-info">
            <b><?php echo htmlspecialchars($tuman_nomi); ?></b>
            <span><?php echo matn('Туман'); ?></span>
        </div>
        <a href="logout.php" class="logout-btn">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="18" height="18"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
            <?php echo matn('Чиқиш'); ?>
        </a>
    </div>
</aside>

<!-- ASOSIY KONTENT -->
<div class="main-content">
    <div class="dashboard-header">
        <div>
            <h2><?php echo matn('Ҳисобот'); ?></h2>
            <p><?php echo matn('Кунлик киритиш'); ?> — <?php echo htmlspecialchars($tuman_nomi); ?></p>
        </div>
        <div>
            <input type="date" class="date-picker" value="<?php echo $sana; ?>" max="<?php echo date('Y-m-d'); ?>" 
                   onchange="location.href='?sana='+this.value">
        </div>
    </div>

    <!-- STATISTIK KARTALAR -->
    <div class="stat-cards">
        <div class="stat-card">
            <h3><?php echo matn('Ойлик прогноз'); ?></h3>
            <div class="value"><?php echo number_format($stats['prognoz'], 2); ?> <small><?php echo matn('млрд сўм'); ?></small></div>
            <div class="sub"><?php echo matn('Админ томонидан киритилган'); ?></div>
        </div>
        <div class="stat-card <?php echo $stats['soliq_jami'] >= $stats['prognoz'] ? 'green' : 'yellow'; ?>">
            <h3><?php echo matn('Жами тушум'); ?></h3>
            <div class="value"><?php echo number_format($stats['soliq_jami'], 2); ?> <small><?php echo matn('млрд сўм'); ?></small></div>
            <div class="sub"><?php echo matn('Бажарилиш'); ?>: <b><?php echo $stats['prognoz'] > 0 ? number_format(($stats['soliq_jami'] / $stats['prognoz']) * 100, 1) : '0'; ?>%</b></div>
        </div>
        <div class="stat-card">
            <h3><?php echo matn('Бугунги тушум'); ?></h3>
            <div class="value"><?php echo number_format($stats['soliq_kunlik'], 2); ?> <small><?php echo matn('млрд сўм'); ?></small></div>
            <div class="sub"><?php echo date('d.m.Y'); ?></div>
        </div>
        <div class="stat-card <?php echo $stats['soliq_qoldiq'] < 0 ? 'green' : 'red'; ?>">
            <h3><?php echo matn('Ой охирига қолдиқ'); ?></h3>
            <div class="value"><?php echo number_format(abs($stats['soliq_qoldiq']), 2); ?> <small><?php echo matn('млрд сўм'); ?></small></div>
            <div class="sub"><?php echo $stats['soliq_qoldiq'] < 0 ? matn('Прогноз ошириб бажарилган') : matn('Прогнозгача етмаган'); ?></div>
        </div>
    </div>

    <!-- HISOBOT HOLATI -->
    <div class="panel">
        <div class="panel-header">
            <h3><?php echo matn('Ҳисобот ҳолати'); ?></h3>
            <p><?php echo matn('Бугунги кун учун'); ?> <?php echo date('d.m.Y'); ?></p>
        </div>
        <div class="panel-body">
            <?php if (!$hisobot): ?>
                <div class="empty-state">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><path d="M8 12h8M12 8v8"/></svg>
                    <b><?php echo matn('Ҳисобот киритилмаган'); ?></b>
                    <p><?php echo matn('Илтимос, кунлик ҳисоботни киритинг.'); ?></p>
                    <a href="kiritish.php" style="color: var(--blue); font-weight: bold;"><?php echo matn('Ҳисобот киритиш'); ?></a>
                </div>
            <?php else: ?>
                <div class="status-box">
                    <?php if ($hisobot['status'] == 'yuborilgan'): ?>
                        <div class="status-badge success">✓ <?php echo matn('Ҳисобот топширилган'); ?></div>
                        <p style="font-size: 14px; color: var(--text-gray);"><?php echo matn('Ҳисобот 1 марта юборилган. Қайта таҳрирлаш мумкин эмас.'); ?></p>
                    <?php elseif ($hisobot['status'] == 'qaytarildi'): ?>
                        <div class="status-badge error">✗ <?php echo matn('Ҳисобот қайтарилган'); ?></div>
                        <div>
                            <p style="font-size: 14px; color: var(--red); margin-bottom: 5px;"><b><?php echo matn('Сабаб'); ?>:</b> <?php echo htmlspecialchars($hisobot['qaytarish_sababi'] ?? '—'); ?></p>
                            <a href="kiritish.php" style="color: var(--blue); font-weight: bold;"><?php echo matn('Қайта киритиш'); ?></a>
                        </div>
                    <?php else: ?>
                        <div class="status-badge info">🔒 <?php echo matn('Ҳисобот якуний ҳолатга ўтказилган'); ?></div>
                        <p style="font-size: 14px; color: var(--text-gray);"><?php echo matn('Қайта таҳрирлаш мумкин эмас.'); ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 7 KUNLIK DINAMIKA -->
    <div class="panel">
        <div class="panel-header">
            <h3><?php echo matn('Кунлик солиқ тушуми'); ?></h3>
            <p><?php echo matn('Охирги 7 кун, жами (млрд сўм)'); ?></p>
        </div>
        <div class="panel-body">
            <?php if (empty($dinamika)): ?>
                <div class="empty-state">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><path d="M8 12h8M12 8v8"/></svg>
                    <b><?php echo matn('Маълумот етарли эмас'); ?></b>
                    <p><?php echo matn('Охирги 7 кун ичида ҳисобот киритилмаган'); ?></p>
                </div>
            <?php else: ?>
                <div class="chart-container">
                    <?php 
                    $max = max(array_column($dinamika, 'qiymat')) ?: 1;
                    foreach ($dinamika as $d): 
                        $balandlik = ($d['qiymat'] / $max) * 100;
                    ?>
                    <div class="chart-bar" style="height: <?php echo max(10, $balandlik); ?>%;" title="<?php echo number_format($d['qiymat'], 2); ?> млрд">
                        <span><?php echo date('d.m', strtotime($d['sana'])); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div style="display: flex; justify-content: space-between; margin-top: 40px; font-size: 13px; font-weight: 600; color: var(--text-gray);">
                    <span><?php echo matn('Энг паст кун'); ?>: <?php echo number_format(min(array_column($dinamika, 'qiymat')), 2); ?> млрд</span>
                    <span><?php echo matn('Энг юқори кун'); ?>: <?php echo number_format(max(array_column($dinamika, 'qiymat')), 2); ?> млрд</span>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- BOZORLAR VA QARZ -->
    <div class="grid-2">
        <div class="panel">
            <div class="panel-header"><h3><?php echo matn('Бозорларни рақамлаштириш'); ?></h3></div>
            <div class="panel-body">
                <div class="info-grid">
                    <div class="info-item"><b><?php echo number_format($stats['bozor_soni']); ?></b><span><?php echo matn('Мавжуд'); ?></span></div>
                    <div class="info-item"><b><?php echo number_format($stats['bozor_raqam']); ?></b><span><?php echo matn('Рақамлаштирилган'); ?></span></div>
                    <div class="info-item"><b><?php echo number_format($stats['bozor_vm']); ?></b><span><?php echo matn('ВМга уланган'); ?></span></div>
                    <div class="info-item"><b><?php echo number_format($stats['bozor_soliq']); ?></b><span><?php echo matn('Солиққа уланган'); ?></span></div>
                </div>
            </div>
        </div>
        <div class="panel">
            <div class="panel-header"><h3><?php echo matn('Ундирилган солиқ қарзи'); ?></h3></div>
            <div class="panel-body">
                <div class="info-grid">
                    <div class="info-item"><b><?php echo number_format($stats['und_dsb'], 2); ?></b><span><?php echo matn('ДСБ орқали'); ?></span></div>
                    <div class="info-item"><b><?php echo number_format($stats['und_mib'], 2); ?></b><span><?php echo matn('МИБ орқали'); ?></span></div>
                    <div class="info-item"><b><?php echo number_format($stats['und_jami'], 2); ?></b><span><?php echo matn('Жами'); ?></span></div>
                </div>
            </div>
        </div>
    </div>

    <!-- FOOTER NOTE -->
    <div class="footer-note">
        <b><?php echo matn('Маълумотлар туман ишчи гуруҳлари томонидан киритилади. Ўлчов бирлиги — млрд сўм.'); ?></b>
        <br>
        <?php echo matn('Тизимдаги барча амаллар журналга ёзилади. Ҳисоб маълумотлари шахсий — бошқа шахсга берилмайди.'); ?>
        <br><br>
        <?php echo matn('© 2026 Яширин иқтисодиётга қарши курашиш департаменти.'); ?>
    </div>
</div>

</body>
</html>