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

// Filtr va sahifalash
$filter_date = $_GET['sana'] ?? '';
$filter_status = $_GET['status'] ?? '';
$current_page = (int)($_GET['page'] ?? 1);
$limit = 20;
$offset = ($current_page - 1) * $limit;

// So'rov shartlari
$where = ["tuman_id = ?"];
$params = [$hudud_id];
$types = "i";

if ($filter_date) {
    $where[] = "sana = ?";
    $params[] = $filter_date;
    $types .= "s";
}

if ($filter_status) {
    $where[] = "status = ?";
    $params[] = $filter_status;
    $types .= "s";
}

$where_sql = implode(" AND ", $where);

// Umumiy sonini hisoblash
$count_stmt = $conn->prepare("SELECT COUNT(*) FROM hisobotlar WHERE $where_sql");
$count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total_rows = $count_stmt->get_result()->fetch_row()[0];
$total_pages = ceil($total_rows / $limit);

// Ma'lumotlarni olish
$sql = "SELECT * FROM hisobotlar WHERE $where_sql ORDER BY sana DESC LIMIT $offset, $limit";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="uz">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Менинг тарихим</title>
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

/* Filtrlar */
.filter-panel {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: 10px;
    box-shadow: var(--shadow);
    padding: 20px;
    margin-bottom: 20px;
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}

.filter-panel input,
.filter-panel select {
    padding: 10px;
    border: 1px solid var(--border);
    border-radius: 6px;
    font-size: 14px;
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

/* Jadval */
.table-panel {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: 10px;
    box-shadow: var(--shadow);
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 800px;
}

th, td {
    border: 1px solid var(--border);
    padding: 12px 15px;
    text-align: center;
    font-size: 13px;
}

th {
    background: #f3f4f6;
    color: var(--text-dark);
    font-weight: 700;
}

tr:hover { background: #f9fafb; }

.badge {
    padding: 6px 10px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
    display: inline-block;
}

.badge-yashil { background: #dcfce7; color: #166534; }
.badge-sariq { background: #fef9c3; color: #854d0e; }
.badge-qizil { background: #fee2e2; color: #991b1b; }
.badge-kulrang { background: #f3f4f6; color: #6b7280; }

/* Sahifalash */
.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 10px;
    margin-top: 20px;
    padding: 20px 0;
}

.pagination a {
    padding: 8px 12px;
    border: 1px solid var(--border);
    border-radius: 6px;
    background: var(--white);
    font-size: 13px;
    color: var(--text-dark);
    text-decoration: none;
}

.pagination a:hover { background: #f3f4f6; }
.pagination a.active { background: var(--blue); color: var(--white); }

/* Empty state */
.empty-state {
    text-align: center;
    padding: 50px;
    color: var(--text-gray);
}

.empty-state b { display: block; font-size: 16px; margin-bottom: 5px; color: var(--text-dark); }
.empty-state svg { width: 50px; height: 50px; margin-bottom: 15px; color: #d1d5db; }

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

        <a href="tarix.php" class="nav-link active">
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
            <h2><?php echo matn('Менинг тарихим'); ?></h2>
            <p><?php echo matn('Киртилган ҳисоботлар тарихи'); ?> — <?php echo htmlspecialchars($tuman_nomi); ?></p>
        </div>
    </div>

    <!-- Filtrlar -->
    <div class="filter-panel">
        <form method="GET" action="tarix.php" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <input type="date" name="sana" value="<?php echo $filter_date; ?>">
            <select name="status">
                <option value=""><?php echo matn('Барча ҳолатлар'); ?></option>
                <option value="qoralama" <?php echo $filter_status == 'qoralama' ? 'selected' : ''; ?>><?php echo matn('Қоралама'); ?></option>
                <option value="yuborilgan" <?php echo $filter_status == 'yuborilgan' ? 'selected' : ''; ?>><?php echo matn('Юборилган'); ?></option>
                <option value="qaytarildi" <?php echo $filter_status == 'qaytarildi' ? 'selected' : ''; ?>><?php echo matn('Қайтарилган'); ?></option>
                <option value="tuzatildi" <?php echo $filter_status == 'tuzatildi' ? 'selected' : ''; ?>><?php echo matn('Тузатилган'); ?></option>
            </select>
            <button type="submit" class="btn btn-primary"><?php echo matn('Филтр'); ?></button>
            <a href="tarix.php" class="btn"><?php echo matn('Тозалаш'); ?></a>
        </form>
    </div>

    <!-- Jadval -->
    <div class="table-panel">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th><?php echo matn('Сана'); ?></th>
                    <th><?php echo matn('Прогноз'); ?></th>
                    <th><?php echo matn('Жами тушум'); ?></th>
                    <th><?php echo matn('Кунлик тушум'); ?></th>
                    <th><?php echo matn('Қолдиқ'); ?></th>
                    <th><?php echo matn('Бозорлар'); ?></th>
                    <th><?php echo matn('Қарз'); ?></th>
                    <th><?php echo matn('Ҳолат'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($history)): ?>
                    <tr>
                        <td colspan="9" style="padding: 40px; text-align: center; color: #6b7280;">
                            <b><?php echo matn('Маълумот топилмади'); ?></b>
                            <p><?php echo matn('Бу сана ёки ҳолат бўйича ҳисобот киритилмаган.'); ?></p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php $i = $offset + 1; foreach ($history as $h): ?>
                        <tr>
                            <td><?php echo $i++; ?></td>
                            <td><?php echo date('d.m.Y', strtotime($h['sana'])); ?></td>
                            <td><?php echo number_format($h['prognoz'] ?? 0, 2); ?></td>
                            <td><?php echo number_format($h['soliq_jami'] ?? 0, 2); ?></td>
                            <td><?php echo number_format($h['soliq_kunlik'] ?? 0, 2); ?></td>
                            <td><?php echo number_format($h['soliq_qoldiq'] ?? 0, 2); ?></td>
                            <td><?php echo number_format($h['bozor_soni'] ?? 0); ?> / <?php echo number_format($h['bozor_raqam'] ?? 0); ?></td>
                            <td><?php echo number_format($h['und_jami'] ?? 0, 2); ?></td>
                            <td>
                                <?php 
                                if ($h['status'] == 'yuborilgan') {
                                    echo '<span class="badge badge-yashil">' . matn('Юборилган') . '</span>';
                                } elseif ($h['status'] == 'qoralama') {
                                    echo '<span class="badge badge-sariq">' . matn('Қоралама') . '</span>';
                                } elseif ($h['status'] == 'qaytarildi') {
                                    echo '<span class="badge badge-qizil">' . matn('Қайтарилган') . '</span>';
                                } else {
                                    echo '<span class="badge badge-kulrang">' . matn('Тузатилган') . '</span>';
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Sahifalash -->
    <?php if ($total_pages > 1): ?>
    <div class="pagination">
        <?php if ($current_page > 1): ?>
            <a href="?page=<?php echo $current_page - 1; ?>&sana=<?php echo $filter_date; ?>&status=<?php echo $filter_status; ?>"><?php echo matn('Олдинги'); ?></a>
        <?php endif; ?>

        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <?php if ($i == $current_page): ?>
                <a href="#" class="active"><?php echo $i; ?></a>
            <?php elseif ($i >= $current_page - 2 && $i <= $current_page + 2): ?>
                <a href="?page=<?php echo $i; ?>&sana=<?php echo $filter_date; ?>&status=<?php echo $filter_status; ?>"><?php echo $i; ?></a>
            <?php endif; ?>
        <?php endfor; ?>

        <?php if ($current_page < $total_pages): ?>
            <a href="?page=<?php echo $current_page + 1; ?>&sana=<?php echo $filter_date; ?>&status=<?php echo $filter_status; ?>"><?php echo matn('Кейинги'); ?></a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Footer -->
    <div class="footer-note">
        <b><?php echo matn('Ҳисоботлар туман ишчи гуруҳлари томонидан киритилади. Ўлчов бирлиги — млн сўм.'); ?></b>
    </div>
</div>

</body>
</html>