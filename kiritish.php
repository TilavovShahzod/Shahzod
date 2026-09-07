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

// Til tanlash
$til = $_GET['til'] ?? $_COOKIE['til'] ?? 'kr';
if ($til === 'kr') {
    setcookie('til', 'kr', time() + 31536000, '/');
} else {
    setcookie('til', 'lot', time() + 31536000, '/');
}

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

function matn($lotin) {
    global $til;
    if ($til === 'kr') return kirill($lotin);
    return $lotin;
}

// Joriy sana
$sana = date('Y-m-d');

// Mavjud hisobotni tekshirish
$stmt = $conn->prepare("SELECT * FROM hisobotlar WHERE tuman_id = ? AND sana = ?");
$stmt->bind_param("is", $hudud_id, $sana);
$stmt->execute();
$mavjud = $stmt->get_result()->fetch_assoc();

// Kechagi hisobotni olish (farqlarni hisoblash uchun)
$kecha_sana = date('Y-m-d', strtotime('-1 day'));
$stmt_kecha = $conn->prepare("SELECT * FROM hisobotlar WHERE tuman_id = ? AND sana = ?");
$stmt_kecha->bind_param("is", $hudud_id, $kecha_sana);
$stmt_kecha->execute();
$kechagi = $stmt_kecha->get_result()->fetch_assoc();

// Adminning oylik prognozini olish
$admin_prognoz = 0;
$stmt_prog = $conn->prepare("SELECT oylik_prognoz_admin FROM hisobotlar WHERE tuman_id = ? AND sana = ?");
$stmt_prog->bind_param("is", $hudud_id, $sana);
$stmt_prog->execute();
$res_prog = $stmt_prog->get_result()->fetch_assoc();
if ($res_prog) $admin_prognoz = $res_prog['oylik_prognoz_admin'] ?? 0;

// FORM YUBORILGANDA
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($mavjud && $mavjud['status'] == 'yuborilgan') {
        header("Location: hodim_dashboard.php");
        exit;
    }

    $status = $_POST['status'] ?? 'yuborilgan';

    // Vergulni nuqtaga o'girish funksiyasi
    function raqam($qiymat) {
        return (float)str_replace(',', '.', $qiymat);
    }

    // 1. Bandlik bloki
    $bandlik_jami = raqam($_POST['bandlik_jami'] ?? '0');
    $bandlik_kunlik = raqam($_POST['bandlik_kunlik'] ?? '0');
    $norasmiy_band = raqam($_POST['norasmiy_band'] ?? '0');
    $yangi_ish_orni = raqam($_POST['yangi_ish_orni'] ?? '0');
    
    // Yangi 15 kunlik maydonlar
    $norasmiy_band_jami = raqam($_POST['norasmiy_band_jami'] ?? '0');
    $yangi_ish_orni_jami = raqam($_POST['yangi_ish_orni_jami'] ?? '0');

    // Farq avtomatik: bugungi kunlik - kechagi kunlik
    $bandlik_farq = $bandlik_kunlik - ($kechagi['bandlik_kunlik'] ?? 0);

    // 2. Soliq tushumi (JAMI AVTOMATIK)
    $soliq_kunlik = raqam($_POST['soliq_kunlik'] ?? '0');
    
    // Soliq jami: eski jami + yangi kunlik
    if ($mavjud) {
        $soliq_jami = ($mavjud['soliq_jami'] ?? 0) + $soliq_kunlik;
    } else {
        $soliq_jami = $soliq_kunlik;
    }
    
    $soliq_qoldiq = $admin_prognoz - $soliq_jami;

    // 3. Qarz undirish
    $und_dsb = raqam($_POST['und_dsb'] ?? '0');
    $und_mib = raqam($_POST['und_mib'] ?? '0');
    $und_jami = $und_dsb + $und_mib;
    
    // Qarz boqimanda (agar maydon mavjud bo'lsa)
    $qarz_boqimanda = raqam($_POST['qarz_boqimanda'] ?? '0');

    // 4. Qo'shimcha manba
    $qm_kunlik = raqam($_POST['qm_kunlik'] ?? '0');
    $qm_reja = $mavjud['qm_reja'] ?? 0;
    $qm_jami = ($mavjud['qm_jami'] ?? 0) + $qm_kunlik;
    $qm_qoldiq = $qm_reja - $qm_jami;

    // 5. Savdo tushumi (FARQ AVTOMATIK)
    $savdo_kunlik = raqam($_POST['savdo_kunlik'] ?? '0');
    $savdo_jami = ($mavjud['savdo_jami'] ?? 0) + $savdo_kunlik;
    $savdo_farq = $savdo_kunlik - ($kechagi['savdo_kunlik'] ?? 0);

    // 6. Bozorlar
    $bozor_soni = (int)raqam($_POST['bozor_soni'] ?? '0');
    $bozor_raqam = (int)raqam($_POST['bozor_raqam'] ?? '0');
    $bozor_vm = (int)raqam($_POST['bozor_vm'] ?? '0');
    $bozor_soliq = (int)raqam($_POST['bozor_soliq'] ?? '0');

    // 7. Bozordagi tushum (FARQ AVTOMATIK)
    $bt_kunlik = raqam($_POST['bt_kunlik'] ?? '0');
    $bt_jami = ($mavjud['bt_jami'] ?? 0) + $bt_kunlik;
    $bt_farq = $bt_kunlik - ($kechagi['bt_kunlik'] ?? 0);

    // Yozish yoki Yangilash
    if (!$mavjud) {
        // INSERT so'rovi - barcha ustunlar ro'yxati
        $stmt = $conn->prepare("INSERT INTO hisobotlar (
            tuman_id, sana, oylik_prognoz_admin,
            bandlik_jami, bandlik_kunlik, bandlik_farq, 
            norasmiy_band, yangi_ish_orni,
            norasmiy_band_jami, yangi_ish_orni_jami,
            soliq_jami, soliq_kunlik, soliq_qoldiq,
            und_jami, und_dsb, und_mib, qarz_boqimanda,
            qm_reja, qm_jami, qm_kunlik, qm_qoldiq,
            savdo_jami, savdo_kunlik, savdo_farq,
            bozor_soni, bozor_raqam, bozor_vm, bozor_soliq,
            bt_jami, bt_kunlik, bt_farq,
            status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        // 32 ta ustun: 3 ta asosiy + 29 ta ma'lumot = 32
        // bind_param: 32 ta o'zgaruvchi
        $stmt->bind_param("isddddddddddddddddddddddddddddds", 
            $hudud_id, $sana, $admin_prognoz,
            $bandlik_jami, $bandlik_kunlik, $bandlik_farq, 
            $norasmiy_band, $yangi_ish_orni,
            $norasmiy_band_jami, $yangi_ish_orni_jami,
            $soliq_jami, $soliq_kunlik, $soliq_qoldiq,
            $und_jami, $und_dsb, $und_mib, $qarz_boqimanda,
            $qm_reja, $qm_jami, $qm_kunlik, $qm_qoldiq,
            $savdo_jami, $savdo_kunlik, $savdo_farq,
            $bozor_soni, $bozor_raqam, $bozor_vm, $bozor_soliq,
            $bt_jami, $bt_kunlik, $bt_farq,
            $status
        );
    } else {
        // UPDATE so'rovi
        $stmt = $conn->prepare("UPDATE hisobotlar SET 
            bandlik_jami = ?, bandlik_kunlik = ?, bandlik_farq = ?, 
            norasmiy_band = ?, yangi_ish_orni = ?,
            norasmiy_band_jami = ?, yangi_ish_orni_jami = ?,
            soliq_jami = ?, soliq_kunlik = ?, soliq_qoldiq = ?,
            und_jami = ?, und_dsb = ?, und_mib = ?, qarz_boqimanda = ?,
            qm_reja = ?, qm_jami = ?, qm_kunlik = ?, qm_qoldiq = ?,
            savdo_jami = ?, savdo_kunlik = ?, savdo_farq = ?,
            bozor_soni = ?, bozor_raqam = ?, bozor_vm = ?, bozor_soliq = ?,
            bt_jami = ?, bt_kunlik = ?, bt_farq = ?,
            status = ?
            WHERE tuman_id = ? AND sana = ?");
        
        // 29 ta SET + 2 ta WHERE = 31 ta o'zgaruvchi
        $stmt->bind_param("dddddddddddddddddddddddddddsis", 
            $bandlik_jami, $bandlik_kunlik, $bandlik_farq, 
            $norasmiy_band, $yangi_ish_orni,
            $norasmiy_band_jami, $yangi_ish_orni_jami,
            $soliq_jami, $soliq_kunlik, $soliq_qoldiq,
            $und_jami, $und_dsb, $und_mib, $qarz_boqimanda,
            $qm_reja, $qm_jami, $qm_kunlik, $qm_qoldiq,
            $savdo_jami, $savdo_kunlik, $savdo_farq,
            $bozor_soni, $bozor_raqam, $bozor_vm, $bozor_soliq,
            $bt_jami, $bt_kunlik, $bt_farq,
            $status, $hudud_id, $sana
        );
    }

    if ($stmt->execute()) {
        jurnal('hisobot_saqlash', "Tuman: $tuman_nomi, Sana: $sana, Holat: $status");
        header("Location: hodim_dashboard.php");
        exit;
    } else {
        $error = "Хатолик: " . $conn->error;
    }
}

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="uz">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Кунлик киритиш</title>
<style>
:root {
    --bg: #f4f6f9;
    --white: #ffffff;
    --border: #e5e7eb;
    --text-dark: #111827;
    --text-gray: #6b7280;
    --blue: #1c5cab;
    --green: #16a34a;
    --red: #dc2626;
    --yellow: #d97706;
    --sidebar-bg: #1e293b;
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
.sidebar {
    width: 260px;
    background: linear-gradient(180deg, var(--sidebar-bg) 0%, #0C2A45 100%);
    color: white;
    display: flex;
    flex-direction: column;
    position: fixed;
    top: 0;
    left: 0;
    height: 100vh;
    z-index: 1000;
}
.sidebar-header {
    padding: 25px 20px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    text-align: center;
}
.sidebar-header img {
    width: 60px;
    height: 60px;
    object-fit: contain;
    margin-bottom: 10px;
}
.sidebar-header h2 {
    font-size: 14px;
    line-height: 1.4;
    font-weight: 700;
    text-transform: uppercase;
}
nav { flex: 1; overflow-y: auto; }
.nav-title {
    font-size: 12px;
    color: #8FB3D6;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 20px 20px 10px;
    font-weight: 600;
}
.nav-link {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 20px;
    color: #cbd5e1;
    text-decoration: none;
    border-radius: 8px;
    margin-bottom: 4px;
    font-size: 15px;
    transition: all 0.2s;
}
.nav-link:hover { background: rgba(255,255,255,0.1); color: white; }
.nav-link.active { background: var(--blue); color: white; box-shadow: 0 4px 10px rgba(28, 92, 171, 0.4); }
.nav-link svg { width: 20px; height: 20px; flex-shrink: 0; }
.sidebar-footer { padding: 20px; border-top: 1px solid rgba(255,255,255,0.1); }
.til-tanla { display: flex; gap: 5px; margin-bottom: 15px; }
.til-tanla a {
    flex: 1;
    text-align: center;
    padding: 8px;
    font-size: 13px;
    color: #cbd5e1;
    text-decoration: none;
    background: rgba(255,255,255,0.1);
    border-radius: 6px;
}
.til-tanla a.active { background: var(--blue); color: white; font-weight: bold; }
.logout-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    background: rgba(220, 38, 38, 0.2);
    color: #fca5a5;
    padding: 12px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    text-decoration: none;
    font-size: 14px;
}
.logout-btn:hover { background: rgba(220, 38, 38, 0.3); color: white; }
.main-content { margin-left: 260px; flex: 1; padding: 30px; background: var(--bg); }
.dashboard-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 25px;
}
.dashboard-header h2 { font-size: 26px; margin: 0; }
.dashboard-header p { font-size: 14px; margin: 5px 0 0; color: var(--text-gray); }
.form-panel {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: 10px;
    box-shadow: var(--shadow);
    margin-bottom: 20px;
}
.form-panel-header { padding: 15px 20px; border-bottom: 1px solid var(--border); }
.form-panel-header h3 { margin: 0; font-size: 18px; }
.form-panel-body { padding: 20px; }
.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}
.form-group { margin-bottom: 15px; }
.form-group label {
    font-size: 13px;
    color: var(--text-gray);
    font-weight: 600;
    margin-bottom: 5px;
    display: block;
}
.form-group input {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid var(--border);
    border-radius: 6px;
    font-size: 14px;
    color: var(--text-dark);
    transition: 0.2s;
}
.form-group input:focus {
    border-color: var(--blue);
    outline: none;
    box-shadow: 0 0 0 3px rgba(28, 92, 171, 0.1);
}
.form-group input[readonly] {
    background-color: #f9fafb;
    color: #6b7280;
    cursor: not-allowed;
}
.info-box {
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    color: #1e40af;
    padding: 10px 15px;
    border-radius: 8px;
    margin-bottom: 10px;
    font-size: 14px;
    font-weight: 500;
}
.info-box-warning {
    background: #fffbeb;
    border: 1px solid #fde68a;
    color: #92400e;
    padding: 10px 15px;
    border-radius: 8px;
    margin-bottom: 10px;
    font-size: 14px;
    font-weight: 500;
}
.btn-group { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }
.btn {
    padding: 10px 15px;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: var(--white);
    font-size: 14px;
    color: var(--text-dark);
    cursor: pointer;
    transition: 0.2s;
    text-decoration: none;
    display: inline-block;
}
.btn:hover { background: #f3f4f6; }
.btn-primary { background: var(--blue); color: var(--white); border: none; }
.btn-primary:hover { background: #12395C; }
.btn-success { background: var(--green); color: var(--white); border: none; }
.btn-success:hover { background: #15803d; }
.xato-quti, .success-quti {
    background: #FDECEC;
    color: #8E2020;
    border: 1px solid #F3C4C4;
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 15px;
    font-size: 14px;
}
.success-quti { background: #E8F6E8; color: #0a6b0a; border: 1px solid #a7f3d0; }
.locked-message { padding: 50px; text-align: center; color: #6b7280; }
.locked-message b { display: block; font-size: 18px; margin-bottom: 10px; }
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
        <img src="1.png" alt="Gerb">
        <h2><?php echo matn('Яширин иқтисодиётга қарши курашиш департаменти'); ?></h2>
        <p style="font-size: 12px; color: #8FB3D6; margin-top: 5px;"><?php echo matn('Ҳисобот тизими'); ?></p>
    </div>

    <nav>
        <div class="nav-title"><?php echo matn('Ҳисобот'); ?></div>
        <a href="hodim_dashboard.php" class="nav-link">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
            <?php echo matn('Ҳисобот'); ?>
        </a>

        <a href="kiritish.php" class="nav-link active">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <?php echo matn('Кунлик киритиш'); ?>
        </a>

        <a href="tarix.php" class="nav-link">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <?php echo matn('Менинг тарихим'); ?>
        </a>

        <a href="dinamika.php" class="nav-link">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
            <?php echo matn('Динамика'); ?>
        </a>

        <div class="nav-title"><?php echo matn('Тизим'); ?></div>
        <a href="sozlamalar.php" class="nav-link">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            <?php echo matn('Созламалар'); ?>
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="til-tanla">
            <a href="?til=lot" class="<?php echo $til === 'lot' ? 'active' : ''; ?>">Lotin</a>
            <a href="?til=kr" class="<?php echo $til === 'kr' ? 'active' : ''; ?>">Кирилл</a>
        </div>
        <div style="margin-bottom: 15px; text-align: center;">
            <div style="font-weight: 700; font-size: 14px;"><?php echo htmlspecialchars($tuman_nomi); ?></div>
            <div style="font-size: 12px; color: #8FB3D6;"><?php echo matn('Туман'); ?></div>
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
            <h2><?php echo matn('Кунлик киритиш'); ?></h2>
            <p><?php echo matn('Кунлик ҳисоботни киритинг'); ?> — <?php echo htmlspecialchars($tuman_nomi); ?></p>
        </div>
        <div>
            <input type="date" value="<?php echo $sana; ?>" max="<?php echo date('Y-m-d'); ?>" 
                   onchange="location.href='?sana='+this.value" style="padding: 10px; border: 1px solid #ccc; border-radius: 6px;">
        </div>
    </div>

    <?php if(isset($error)): ?>
        <div class="xato-quti"><?php echo $error; ?></div>
    <?php endif; ?>

    <?php if ($mavjud && $mavjud['status'] == 'yuborilgan'): ?>
        <div class="form-panel">
            <div class="locked-message">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width: 50px; height: 50px; margin-bottom: 15px;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0110 0v4"></path></svg>
                <b><?php echo matn('Ҳисобот 1 марта юборилган'); ?></b>
                <p><?php echo matn('Қайта таҳрирлаш мумкин эмас. Агар хатолик бўлса, администраторга мурожаат қилинг.'); ?></p>
                <a href="hodim_dashboard.php" class="btn btn-primary"><?php echo matn('Бош саҳифага қайтиш'); ?></a>
            </div>
        </div>
    <?php else: ?>
        <form method="POST" action="">
            
            <div class="form-panel">
                <div class="form-panel-header">
                    <h3><?php echo matn('1. Бандлик'); ?></h3>
                </div>
                <div class="form-panel-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label><?php echo matn('Жами бандлик'); ?></label>
                            <input type="text" name="bandlik_jami" value="<?php echo $mavjud['bandlik_jami'] ?? '0,00'; ?>" required oninput="this.value = this.value.replace(/[^0-9,]/g, '')">
                        </div>
                        <div class="form-group">
                            <label><?php echo matn('Кунлик бандлик'); ?></label>
                            <input type="text" name="bandlik_kunlik" value="<?php echo $mavjud['bandlik_kunlik'] ?? '0,00'; ?>" required oninput="this.value = this.value.replace(/[^0-9,]/g, '')" oninput="hisoblaBandlikFarq()">
                        </div>
                        <div class="form-group">
                            <label><?php echo matn('Ўтган кунга нисбатан фарқ (Авто)'); ?></label>
                            <input type="text" id="bandlik_farq" name="bandlik_farq" value="<?php echo $mavjud['bandlik_farq'] ?? '0,00'; ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label><?php echo matn('Норасмий банд аҳоли (кунлик)'); ?></label>
                            <input type="text" name="norasmiy_band" value="<?php echo $mavjud['norasmiy_band'] ?? '0,00'; ?>" required oninput="this.value = this.value.replace(/[^0-9,]/g, '')">
                        </div>
                        <div class="form-group">
                            <label><?php echo matn('Янги иш ўрни (кунлик)'); ?></label>
                            <input type="text" name="yangi_ish_orni" value="<?php echo $mavjud['yangi_ish_orni'] ?? '0,00'; ?>" required oninput="this.value = this.value.replace(/[^0-9,]/g, '')">
                        </div>
                    </div>
                    
                    <!-- 15 kunlik maydonlar -->
                    <div style="margin-top: 20px; border-top: 2px dashed #e5e7eb; padding-top: 20px;">
                        <div class="info-box-warning">
                            ⚠️ <?php echo matn('Қуйидаги маълумотлар 15 кунда 1 марта киритилади'); ?>
                        </div>
                        <div class="form-grid">
                            <div class="form-group">
                                <label><?php echo matn('Жами норасмий банд аҳоли (15 кунлик)'); ?></label>
                                <input type="text" name="norasmiy_band_jami" value="<?php echo $mavjud['norasmiy_band_jami'] ?? '0,00'; ?>" oninput="this.value = this.value.replace(/[^0-9,]/g, '')">
                            </div>
                            <div class="form-group">
                                <label><?php echo matn('Жами янгиланиб ушбу йилда ишга жойлашганлар (15 кунлик)'); ?></label>
                                <input type="text" name="yangi_ish_orni_jami" value="<?php echo $mavjud['yangi_ish_orni_jami'] ?? '0,00'; ?>" oninput="this.value = this.value.replace(/[^0-9,]/g, '')">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-panel">
                <div class="form-panel-header">
                    <h3><?php echo matn('2. Солиқ тушуми'); ?></h3>
                </div>
                <div class="form-panel-body">
                    <div class="info-box">
                        <?php echo matn('Ойлик прогноз (Админ томонидан киритилади): '); ?> <strong><?php echo number_format($admin_prognoz, 2, ',', ' '); ?></strong>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label><?php echo matn('Жами тушум (млрд) - Автоматик'); ?></label>
                            <input type="text" id="soliq_jami" name="soliq_jami" value="<?php echo $mavjud['soliq_jami'] ?? '0,00'; ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label><?php echo matn('Кунлик тушум (млрд)'); ?></label>
                            <input type="text" id="soliq_kunlik" name="soliq_kunlik" value="0,00" required oninput="this.value = this.value.replace(/[^0-9,]/g, '')" oninput="hisoblaSoliq()">
                        </div>
                        <div class="form-group">
                            <label><?php echo matn('Ой охирига қолдиқ (Авто)'); ?></label>
                            <input type="text" id="soliq_qoldiq" name="soliq_qoldiq" value="<?php echo $mavjud['soliq_qoldiq'] ?? '0,00'; ?>" readonly>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-panel">
                <div class="form-panel-header">
                    <h3><?php echo matn('3. Ундирилган қарз'); ?></h3>
                </div>
                <div class="form-panel-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label><?php echo matn('ДСБ орқали (млрд)'); ?></label>
                            <input type="text" id="und_dsb" name="und_dsb" value="<?php echo $mavjud['und_dsb'] ?? '0,00'; ?>" required oninput="this.value = this.value.replace(/[^0-9,]/g, '')" oninput="hisoblaUndirilganQarz()">
                        </div>
                        <div class="form-group">
                            <label><?php echo matn('МИБ орқали (млрд)'); ?></label>
                            <input type="text" id="und_mib" name="und_mib" value="<?php echo $mavjud['und_mib'] ?? '0,00'; ?>" required oninput="this.value = this.value.replace(/[^0-9,]/g, '')" oninput="hisoblaUndirilganQarz()">
                        </div>
                        <div class="form-group">
                            <label><?php echo matn('Жами ундирилган қарз (Авто)'); ?></label>
                            <input type="text" id="und_jami" name="und_jami" value="<?php echo $mavjud['und_jami'] ?? '0,00'; ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label><?php echo matn('Жами бўқиманда қарздорлик'); ?></label>
                            <input type="text" name="qarz_boqimanda" value="<?php echo $mavjud['qarz_boqimanda'] ?? '0,00'; ?>" required oninput="this.value = this.value.replace(/[^0-9,]/g, '')">
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-panel">
                <div class="form-panel-header">
                    <h3><?php echo matn('4. Қўшимча манба'); ?></h3>
                </div>
                <div class="form-panel-body">
                    <div class="info-box">
                        <?php echo matn('Ойлик режа (Админ томонидан киритилади): '); ?> <strong><?php echo number_format($mavjud['qm_reja'] ?? 0, 2, ',', ' '); ?></strong>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label><?php echo matn('Кунлик тушум'); ?></label>
                            <input type="text" name="qm_kunlik" value="<?php echo $mavjud['qm_kunlik'] ?? '0,00'; ?>" required oninput="this.value = this.value.replace(/[^0-9,]/g, '')">
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-panel">
                <div class="form-panel-header">
                    <h3><?php echo matn('5. Жами савдо тушуми'); ?></h3>
                </div>
                <div class="form-panel-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label><?php echo matn('Кунлик тушум'); ?></label>
                            <input type="text" name="savdo_kunlik" value="<?php echo $mavjud['savdo_kunlik'] ?? '0,00'; ?>" required oninput="this.value = this.value.replace(/[^0-9,]/g, '')" oninput="hisoblaSavdoFarq()">
                        </div>
                        <div class="form-group">
                            <label><?php echo matn('Ўтган кунга нисбатан фарқ (Авто)'); ?></label>
                            <input type="text" id="savdo_farq" name="savdo_farq" value="<?php echo $mavjud['savdo_farq'] ?? '0,00'; ?>" readonly>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-panel">
                <div class="form-panel-header">
                    <h3><?php echo matn('6. Бозорларни рақамлаштириш'); ?></h3>
                </div>
                <div class="form-panel-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label><?php echo matn('Мавжуд бозорлар'); ?></label>
                            <input type="text" name="bozor_soni" value="<?php echo $mavjud['bozor_soni'] ?? '0'; ?>" required oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                        </div>
                        <div class="form-group">
                            <label><?php echo matn('Рақамлаштирилгани'); ?></label>
                            <input type="text" name="bozor_raqam" value="<?php echo $mavjud['bozor_raqam'] ?? '0'; ?>" required oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                        </div>
                        <div class="form-group">
                            <label><?php echo matn('ВМга уланган'); ?></label>
                            <input type="text" name="bozor_vm" value="<?php echo $mavjud['bozor_vm'] ?? '0'; ?>" required oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                        </div>
                        <div class="form-group">
                            <label><?php echo matn('Солиқ органига уланган'); ?></label>
                            <input type="text" name="bozor_soliq" value="<?php echo $mavjud['bozor_soliq'] ?? '0'; ?>" required oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-panel">
                <div class="form-panel-header">
                    <h3><?php echo matn('7. Бозордаги тушум'); ?></h3>
                </div>
                <div class="form-panel-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label><?php echo matn('Кунлик тушум'); ?></label>
                            <input type="text" name="bt_kunlik" value="<?php echo $mavjud['bt_kunlik'] ?? '0,00'; ?>" required oninput="this.value = this.value.replace(/[^0-9,]/g, '')" oninput="hisoblaBozorFarq()">
                        </div>
                        <div class="form-group">
                            <label><?php echo matn('Ўтган кунга нисбатан фарқ (Авто)'); ?></label>
                            <input type="text" id="bt_farq" name="bt_farq" value="<?php echo $mavjud['bt_farq'] ?? '0,00'; ?>" readonly>
                        </div>
                    </div>
                </div>
            </div>

            <div class="btn-group">
                <button type="submit" name="status" value="yuborilgan" class="btn btn-success"><?php echo matn('Тасдиқлаш ва юбориш'); ?></button>
            </div>
        </form>

        <script>
            // Kechagi ma'lumotlarni PHP dan olish
            const kechagiBandlik = <?php echo json_encode($kechagi['bandlik_kunlik'] ?? 0); ?>;
            const kechagiSavdo = <?php echo json_encode($kechagi['savdo_kunlik'] ?? 0); ?>;
            const kechagiBozor = <?php echo json_encode($kechagi['bt_kunlik'] ?? 0); ?>;
            const adminPrognoz = <?php echo json_encode($admin_prognoz); ?>;
            const eskiSoliqJami = <?php echo json_encode($mavjud['soliq_jami'] ?? 0); ?>;

            // Bandlik farqini hisoblash
            function hisoblaBandlikFarq() {
                const bugun = parseFloat(document.querySelector('[name="bandlik_kunlik"]').value.replace(',', '.')) || 0;
                const farq = bugun - kechagiBandlik;
                document.getElementById('bandlik_farq').value = farq.toFixed(2).replace('.', ',');
            }

            // Soliq jami va qoldiqni hisoblash
            function hisoblaSoliq() {
                const kunlik = parseFloat(document.getElementById('soliq_kunlik').value.replace(',', '.')) || 0;
                const yangiJami = eskiSoliqJami + kunlik;
                document.getElementById('soliq_jami').value = yangiJami.toFixed(2).replace('.', ',');
                
                const qoldiq = adminPrognoz - yangiJami;
                document.getElementById('soliq_qoldiq').value = qoldiq.toFixed(2).replace('.', ',');
            }

            // Savdo farqini hisoblash
            function hisoblaSavdoFarq() {
                const bugun = parseFloat(document.querySelector('[name="savdo_kunlik"]').value.replace(',', '.')) || 0;
                const farq = bugun - kechagiSavdo;
                document.getElementById('savdo_farq').value = farq.toFixed(2).replace('.', ',');
            }

            // Bozor farqini hisoblash
            function hisoblaBozorFarq() {
                const bugun = parseFloat(document.querySelector('[name="bt_kunlik"]').value.replace(',', '.')) || 0;
                const farq = bugun - kechagiBozor;
                document.getElementById('bt_farq').value = farq.toFixed(2).replace('.', ',');
            }

            // Qarzni hisoblash
            function hisoblaUndirilganQarz() {
                const dsb = parseFloat(document.getElementById('und_dsb').value.replace(',', '.')) || 0;
                const mib = parseFloat(document.getElementById('und_mib').value.replace(',', '.')) || 0;
                document.getElementById('und_jami').value = (dsb + mib).toFixed(2).replace('.', ',');
            }

            // Sahifa yuklanganda barcha hisob-kitoblarni bajaramiz
            document.addEventListener('DOMContentLoaded', () => {
                hisoblaBandlikFarq();
                hisoblaSoliq();
                hisoblaSavdoFarq();
                hisoblaBozorFarq();
                hisoblaUndirilganQarz();
            });
        </script>
    <?php endif; ?>
</div>

</body>
</html>