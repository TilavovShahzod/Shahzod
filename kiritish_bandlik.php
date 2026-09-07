<?php
require_once 'includes/auth.php';
check_bandlik();

$hudud_id = $_SESSION['hudud_id'];
$tuman_nomi = get_tuman_nomi();

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

$sana = date('Y-m-d');

// Mavjud hisobotni tekshirish
$stmt = $conn->prepare("SELECT * FROM hisobotlar WHERE tuman_id = ? AND sana = ?");
$stmt->bind_param("is", $hudud_id, $sana);
$stmt->execute();
$mavjud = $stmt->get_result()->fetch_assoc();

// FORM YUBORILGANDA
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($mavjud && $mavjud['status'] == 'yuborilgan') {
        header("Location: kiritish_bandlik.php");
        exit;
    }

    $bandlik_jami = $_POST['bandlik_jami'] ?? 0;
    $bandlik_kunlik = $_POST['bandlik_kunlik'] ?? 0;
    $bandlik_farq = $_POST['bandlik_farq'] ?? 0;
    $norasmiy_band = $_POST['norasmiy_band'] ?? 0;
    $yangi_ish_orni = $_POST['yangi_ish_orni'] ?? 0;
    $status = $_POST['status'] ?? 'yuborilgan';

    if (!$mavjud) {
        $stmt = $conn->prepare("INSERT INTO hisobotlar (
            tuman_id, sana, 
            bandlik_jami, bandlik_kunlik, bandlik_farq, norasmiy_band, yangi_ish_orni,
            status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->bind_param("isddddds", 
            $hudud_id, $sana,
            $bandlik_jami, $bandlik_kunlik, $bandlik_farq, $norasmiy_band, $yangi_ish_orni,
            $status
        );
    } else {
        $stmt = $conn->prepare("UPDATE hisobotlar SET 
            bandlik_jami = ?, bandlik_kunlik = ?, bandlik_farq = ?, 
            norasmiy_band = ?, yangi_ish_orni = ?,
            status = ?
            WHERE tuman_id = ? AND sana = ?");
        
        $stmt->bind_param("ddddddsis", 
            $bandlik_jami, $bandlik_kunlik, $bandlik_farq, 
            $norasmiy_band, $yangi_ish_orni,
            $status, $hudud_id, $sana
        );
    }

    if ($stmt->execute()) {
        jurnal('bandlik_hisobot_saqlash', "Tuman: $tuman_nomi, Sana: $sana");
        header("Location: kiritish_bandlik.php?success=1");
        exit;
    } else {
        $error = "Xatolik: " . $conn->error;
    }
}

// Mavjud qiymatlarni olish
$bandlik_jami = $mavjud['bandlik_jami'] ?? 0;
$bandlik_kunlik = $mavjud['bandlik_kunlik'] ?? 0;
$bandlik_farq = $mavjud['bandlik_farq'] ?? 0;
$norasmiy_band = $mavjud['norasmiy_band'] ?? 0;
$yangi_ish_orni = $mavjud['yangi_ish_orni'] ?? 0;
$status = $mavjud['status'] ?? 'qoralama';

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="uz">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo matn('Бандлик ҳисоботи'); ?></title>
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
    --shadow: 0 1px 3px rgba(0,0,0,0.1);
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
nav {
    flex: 1;
    overflow-y: auto;
}
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
.nav-link.active {
    background: var(--blue);
    color: white;
    box-shadow: 0 4px 10px rgba(28, 92, 171, 0.4);
}
.nav-link svg { width: 20px; height: 20px; flex-shrink: 0; }
.sidebar-footer {
    padding: 20px;
    border-top: 1px solid rgba(255,255,255,0.1);
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
.main-content {
    margin-left: 260px;
    flex: 1;
    padding: 30px;
    background: var(--bg);
}
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
.form-panel-header {
    padding: 15px 20px;
    border-bottom: 1px solid var(--border);
}
.form-panel-header h3 { margin: 0; font-size: 18px; }
.form-panel-body {
    padding: 20px;
}
.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}
.form-group {
    margin-bottom: 15px;
}
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
.btn-group {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 20px;
}
.btn {
    padding: 10px 25px;
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
.success-quti {
    background: #E8F6E8;
    color: #0a6b0a;
    border: 1px solid #a7f3d0;
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 15px;
    font-size: 14px;
}
.xato-quti {
    background: #FDECEC;
    color: #8E2020;
    border: 1px solid #F3C4C4;
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 15px;
    font-size: 14px;
}
.locked-message {
    padding: 50px;
    text-align: center;
    color: #6b7280;
}
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

        <a href="kiritish_soliq.php" class="nav-link">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <?php echo matn('Солиқ ҳисоботи'); ?>
        </a>

        <a href="kiritish_bandlik.php" class="nav-link active">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <?php echo matn('Бандлик ҳисоботи'); ?>
        </a>

        <a href="tarix.php" class="nav-link">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <?php echo matn('Менинг тарихим'); ?>
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
            <div style="font-size: 12px; color: #8FB3D6;"><?php echo matn('Бандлик бошқармаси'); ?></div>
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
            <h2>👷 <?php echo matn('Бандлик ҳисоботи'); ?></h2>
            <p><?php echo matn('Бандлик, иш ўринлари ва ишсизлик маълумотлари'); ?> — <?php echo htmlspecialchars($tuman_nomi); ?></p>
        </div>
        <div>
            <input type="date" value="<?php echo $sana; ?>" max="<?php echo date('Y-m-d'); ?>" 
                   onchange="location.href='?sana='+this.value" style="padding: 10px; border: 1px solid #ccc; border-radius: 6px;">
        </div>
    </div>

    <?php if(isset($_GET['success'])): ?>
        <div class="success-quti">✅ <?php echo matn('Ҳисобот муваффақиятли сақланди!'); ?></div>
    <?php endif; ?>

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
            <!-- BANDLIK MA'LUMOTLARI -->
            <div class="form-panel">
                <div class="form-panel-header">
                    <h3>👷 <?php echo matn('Бандлик маълумотлари'); ?></h3>
                    <p style="font-size: 13px; color: var(--text-gray);"><?php echo matn('Камбағалликни қисқартириш ва бандлик бошқармаси'); ?></p>
                </div>
                <div class="form-panel-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label><?php echo matn('Жами бандлик'); ?></label>
                            <input type="number" step="1" name="bandlik_jami" value="<?php echo $bandlik_jami; ?>" required>
                        </div>
                        <div class="form-group">
                            <label><?php echo matn('Кунлик бандлик'); ?></label>
                            <input type="number" step="1" name="bandlik_kunlik" value="<?php echo $bandlik_kunlik; ?>" required>
                        </div>
                        <div class="form-group">
                            <label><?php echo matn('Ўтган кунга нисбатан фарқ'); ?></label>
                            <input type="number" step="1" name="bandlik_farq" value="<?php echo $bandlik_farq; ?>" required>
                        </div>
                    </div>
                </div>
            </div>

            <!-- NORASMIY BAND VA YANGI ISH O'RINLARI -->
            <div class="form-panel">
                <div class="form-panel-header">
                    <h3>📋 <?php echo matn('Норасмий бандлик ва янги иш ўринлари'); ?></h3>
                </div>
                <div class="form-panel-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label><?php echo matn('Жами норасмий банд аҳоли'); ?></label>
                            <input type="number" step="1" name="norasmiy_band" value="<?php echo $norasmiy_band; ?>" required>
                        </div>
                        <div class="form-group">
                            <label><?php echo matn('Жами янгиланган янги иш ўрни'); ?></label>
                            <input type="number" step="1" name="yangi_ish_orni" value="<?php echo $yangi_ish_orni; ?>" required>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TUGMALAR -->
            <div class="btn-group">
                <button type="submit" name="status" value="yuborilgan" class="btn btn-success">✅ <?php echo matn('Тасдиқлаш ва юбориш'); ?></button>
            </div>
        </form>
    <?php endif; ?>
</div>

</body>
</html>