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

function matn($lotin) {
    global $til;
    if ($til === 'kr') return kirill($lotin);
    return $lotin;
}

// Foydalanuvchi ma'lumotlarini olish
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Parolni o'zgartirish
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $old_password = trim($_POST['old_password']);
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);

    // Parolni tekshirish
    if (!password_verify($old_password, $user['password_hash'])) {
        $error = "Эски парол нотўғри!";
    } elseif (strlen($new_password) < 6) {
        $error = "Янги парол камида 6 та белгидан иборат бўлиши керак!";
    } elseif ($new_password !== $confirm_password) {
        $error = "Янги пароллар мос келмади!";
    } else {
        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->bind_param("si", $new_hash, $_SESSION['user_id']);
        if ($stmt->execute()) {
            $success = "Парол муваффақиятли ўзгартирилди!";
            jurnal('parol_uzgartirish', "Foydalanuvchi: {$user['login']}");
            // Sessiyani yangilash
            $user['password_hash'] = $new_hash;
        } else {
            $error = "Хатолик: " . $conn->error;
        }
    }
}

// F.I.Sh.ni o'zgartirish
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_fio'])) {
    $new_fio = trim($_POST['fio']);
    if (empty($new_fio)) {
        $error = "Ф.И.Ш. бўш бўлиши мумкин эмас!";
    } else {
        $stmt = $conn->prepare("UPDATE users SET fio = ? WHERE id = ?");
        $stmt->bind_param("si", $new_fio, $_SESSION['user_id']);
        if ($stmt->execute()) {
            $success = "Ф.И.Ш. муваффақиятли ўзгартирилди!";
            $_SESSION['fio'] = $new_fio;
            $user['fio'] = $new_fio;
            jurnal('fio_uzgartirish', "Foydalanuvchi: {$user['login']}");
        } else {
            $error = "Хатолик: " . $conn->error;
        }
    }
}

// Tizim haqida ma'lumot (statistika)
$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM hisobotlar WHERE tuman_id = ?");
$stmt->bind_param("i", $hudud_id);
$stmt->execute();
$my_reports = $stmt->get_result()->fetch_assoc()['cnt'];

$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM jurnal WHERE user_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$my_logs = $stmt->get_result()->fetch_assoc()['cnt'];

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="uz">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Созламалар</title>
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

/* Menyu (Sidebar) */
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

/* Kontent */
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

/* Statistika bloklari */
.stat-blocks {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 15px;
    margin-bottom: 25px;
}

.stat-block {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 20px;
    box-shadow: var(--shadow);
    text-align: center;
}

.stat-block b { font-size: 28px; color: var(--blue); }
.stat-block span { font-size: 13px; color: var(--text-gray); display: block; margin-top: 5px; }

/* Forma qutilari */
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

/* Xabarlar */
.xato-quti, .success-quti {
    background: #FDECEC;
    color: #8E2020;
    border: 1px solid #F3C4C4;
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 15px;
    font-size: 14px;
}

.success-quti {
    background: #E8F6E8;
    color: #0a6b0a;
    border: 1px solid #a7f3d0;
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

        <a href="kiritish.php" class="nav-link">
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
        <a href="sozlamalar.php" class="nav-link active">
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
            <h2><?php echo matn('Созламалар'); ?></h2>
            <p><?php echo matn('Шахсий маълумотлар ва тизим параметрлари'); ?></p>
        </div>
    </div>

    <?php if(isset($success)): ?>
        <div class="success-quti"><?php echo $success; ?></div>
    <?php elseif(isset($error)): ?>
        <div class="xato-quti"><?php echo $error; ?></div>
    <?php endif; ?>

    <!-- Statistika bloklari -->
    <div class="stat-blocks">
        <div class="stat-block">
            <b><?php echo $my_reports; ?></b>
            <span><?php echo matn('Киритилган ҳисоботлар'); ?></span>
        </div>
        <div class="stat-block">
            <b><?php echo $my_logs; ?></b>
            <span><?php echo matn('Журналдаги амаллар'); ?></span>
        </div>
        <div class="stat-block">
            <b><?php echo date('d.m.Y'); ?></b>
            <span><?php echo matn('Жорий сана'); ?></span>
        </div>
    </div>

    <!-- F.I.Sh.ni o'zgartirish -->
    <div class="form-panel">
        <div class="form-panel-header">
            <h3><?php echo matn('Ф.И.Ш.ни ўзгартириш'); ?></h3>
        </div>
        <div class="form-panel-body">
            <form method="POST" action="">
                <div class="form-group">
                    <label><?php echo matn('Ф.И.Ш.'); ?></label>
                    <input type="text" name="fio" value="<?php echo htmlspecialchars($user['fio'] ?? ''); ?>" required>
                </div>
                <button type="submit" name="change_fio" class="btn btn-primary"><?php echo matn('Сақлаш'); ?></button>
            </form>
        </div>
    </div>

    <!-- Parolni o'zgartirish -->
    <div class="form-panel">
        <div class="form-panel-header">
            <h3><?php echo matn('Паролни ўзгартириш'); ?></h3>
        </div>
        <div class="form-panel-body">
            <form method="POST" action="">
                <div class="form-grid">
                    <div class="form-group">
                        <label><?php echo matn('Эски парол'); ?></label>
                        <input type="password" name="old_password" required>
                    </div>
                    <div class="form-group">
                        <label><?php echo matn('Янги парол'); ?></label>
                        <input type="password" name="new_password" required>
                    </div>
                    <div class="form-group">
                        <label><?php echo matn('Янги паролни тасдиқлаш'); ?></label>
                        <input type="password" name="confirm_password" required>
                    </div>
                </div>
                <button type="submit" name="change_password" class="btn btn-primary"><?php echo matn('Паролни ўзгартириш'); ?></button>
            </form>
        </div>
    </div>

    <!-- Tizim haqida -->
    <div class="form-panel">
        <div class="form-panel-header">
            <h3><?php echo matn('Тизим ҳақида'); ?></h3>
        </div>
        <div class="form-panel-body">
            <div style="font-size: 14px; color: var(--text-gray); line-height: 1.7;">
                <p><b><?php echo matn('Логин'); ?>:</b> <?php echo htmlspecialchars($user['login']); ?></p>
                <p><b><?php echo matn('Рол'); ?>:</b> <?php echo $user['role'] == 'tuman' ? matn('Туман ходими') : matn('Вилоят администратори'); ?></p>
                <p><b><?php echo matn('Ҳудуд'); ?>:</b> <?php echo htmlspecialchars($tuman_nomi); ?></p>
                <p><b><?php echo matn('Охирги кириш'); ?>:</b> <?php echo $user['last_login'] ? date('d.m.Y H:i', strtotime($user['last_login'])) : matn('Маълумот йўқ'); ?></p>
            </div>
        </div>
    </div>
</div>

</body>
</html>