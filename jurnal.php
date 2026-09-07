    <?php
require_once 'includes/auth.php';
check_admin(); // Faqat viloyat admini kira oladi

// Sana, foydalanuvchi va amal bo'yicha filtrlar
$filter_user = $_GET['user'] ?? '';
$filter_action = $_GET['action'] ?? '';
$filter_date = $_GET['date'] ?? '';
$current_page = (int)($_GET['page'] ?? 1);
$limit = 50;
$offset = ($current_page - 1) * $limit;

// Jurnal jadvali mavjudligini tekshirish yoki yaratish
$check = $conn->query("SHOW TABLES LIKE 'jurnal'");
if ($check->num_rows == 0) {
    $conn->query("CREATE TABLE jurnal (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT DEFAULT NULL,
        action VARCHAR(100) NOT NULL,
        details TEXT DEFAULT NULL,
        ip_address VARCHAR(45) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
}

// Filtrlash uchun umumiy shartlar
$where = [];
$params = [];
$types = "";

if ($filter_user) {
    $where[] = "u.login LIKE ?";
    $params[] = "%$filter_user%";
    $types .= "s";
}

if ($filter_action) {
    $where[] = "j.action = ?";
    $params[] = $filter_action;
    $types .= "s";
}

if ($filter_date) {
    $where[] = "DATE(j.created_at) = ?";
    $params[] = $filter_date;
    $types .= "s";
}

$where_sql = $where ? "WHERE " . implode(" AND ", $where) : "";

// Umumiy sonini hisoblash
$count_stmt = $conn->prepare("SELECT COUNT(*) FROM jurnal j LEFT JOIN users u ON j.user_id = u.id $where_sql");
if ($types) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_rows = $count_stmt->get_result()->fetch_row()[0];
$total_pages = ceil($total_rows / $limit);

// Jurnal yozuvlarini olish
$sql = "SELECT j.*, u.login, u.fio 
        FROM jurnal j 
        LEFT JOIN users u ON j.user_id = u.id 
        $where_sql 
        ORDER BY j.created_at DESC 
        LIMIT $offset, $limit";

$stmt = $conn->prepare($sql);
if ($types) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

include 'includes/header.php';
?>

<style>
    .jurnal-quti {
        background: #fff;
        border: 1px solid #D8E0EC;
        border-radius: 12px;
        padding: 25px;
        margin-bottom: 20px;
        box-shadow: 0 4px 12px rgba(23,35,59,0.05);
    }

    .jurnal-quti h3 {
        margin-bottom: 15px;
        font-size: 18px;
        color: #17233B;
        padding-bottom: 10px;
        border-bottom: 1px solid #e5e7eb;
    }

    .boshqaruv {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
        margin-bottom: 25px;
    }

    .boshqaruv .left h2 { font-size: 24px; color: #17233B; margin-bottom: 5px; }
    .boshqaruv .left p { font-size: 13px; color: #6b7280; }

    .filtr-form {
        display: flex;
        gap: 10px;
        align-items: center;
        flex-wrap: wrap;
    }

    .filtr-form input,
    .filtr-form select {
        padding: 10px;
        border: 1px solid #D8E0EC;
        border-radius: 6px;
        font-size: 14px;
        color: #17233B;
    }

    .btn {
        padding: 10px 15px;
        border: 1px solid #D8E0EC;
        border-radius: 8px;
        background: #fff;
        font-size: 14px;
        color: #17233B;
        cursor: pointer;
        transition: 0.2s;
        text-decoration: none;
        display: inline-block;
    }
    .btn:hover { background: #f3f4f6; }
    .btn-primary { background: #1c5cab; color: #fff; border-color: #1c5cab; }
    .btn-primary:hover { background: #12395C; }

    .table-responsive { overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; min-width: 800px; }
    th, td { padding: 12px 15px; border: 1px solid #e5e7eb; font-size: 13px; text-align: center; }
    th { background: #f3f4f6; color: #17233B; font-weight: 600; }
    td { color: #374151; }
    tr:hover { background: #f9fafb; }

    .badge {
        padding: 6px 10px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 600;
        display: inline-block;
    }

    .badge-kirish { background: #dcfce7; color: #166534; }
    .badge-chiqish { background: #f3f4f6; color: #374151; }
    .badge-xato { background: #fee2e2; color: #991b1b; }
    .badge-hisobot { background: #e0f2fe; color: #075985; }
    .badge-boshqa { background: #fef9c3; color: #854d0e; }

    .amal-turi {
        text-align: left;
        font-weight: 600;
        font-size: 13px;
    }

    .tafsilot {
        text-align: left;
        font-size: 12px;
        color: #6b7280;
        font-family: monospace;
        max-width: 300px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .sahifalash {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 10px;
        margin-top: 20px;
    }

    .sahifa-tugma {
        padding: 8px 12px;
        border: 1px solid #D8E0EC;
        border-radius: 6px;
        background: #fff;
        font-size: 13px;
        color: #17233B;
        cursor: pointer;
        text-decoration: none;
    }

    .sahifa-tugma:hover { background: #f3f4f6; }
    .sahifa-tugma.faol { background: #1c5cab; color: #fff; border-color: #1c5cab; }
</style>

<div class="content">
    <div class="boshqaruv">
        <div class="left">
            <h2>Tizim jurnali</h2>
            <p>Barcha amallar tarixi (IP manzil bilan birga)</p>
        </div>
        <div class="filtr-form">
            <!-- Filtrlash formasi -->
            <form method="GET" action="jurnal.php" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <input type="text" name="user" placeholder="Foydalanuvchi" value="<?php echo htmlspecialchars($filter_user); ?>">
                <select name="action">
                    <option value="">Barcha amallar</option>
                    <option value="kirish" <?php echo $filter_action == 'kirish' ? 'selected' : ''; ?>>Kirish</option>
                    <option value="chiqish" <?php echo $filter_action == 'chiqish' ? 'selected' : ''; ?>>Chiqish</option>
                    <option value="kirish_xato" <?php echo $filter_action == 'kirish_xato' ? 'selected' : ''; ?>>Kirish xatosi</option>
                    <option value="hisobot_saqlash" <?php echo $filter_action == 'hisobot_saqlash' ? 'selected' : ''; ?>>Hisobot saqlash</option>
                    <option value="foydalanuvchi_qoshish" <?php echo $filter_action == 'foydalanuvchi_qoshish' ? 'selected' : ''; ?>>Foydalanuvchi qo'shish</option>
                    <option value="foydalanuvchi_tahrirlash" <?php echo $filter_action == 'foydalanuvchi_tahrirlash' ? 'selected' : ''; ?>>Foydalanuvchi tahrirlash</option>
                    <option value="foydalanuvchi_ochirish" <?php echo $filter_action == 'foydalanuvchi_ochirish' ? 'selected' : ''; ?>>Foydalanuvchi o'chirish</option>
                </select>
                <input type="date" name="date" value="<?php echo $filter_date; ?>">
                <button type="submit" class="btn btn-primary">Filtr</button>
                <a href="jurnal.php" class="btn">Tozalash</a>
            </form>
        </div>
    </div>

    <div class="jurnal-quti">
        <h3>Jurnal yozuvlari (Jami: <?php echo $total_rows; ?>)</h3>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Vaqt</th>
                        <th>Foydalanuvchi</th>
                        <th>Amal</th>
                        <th>Tafsilot</th>
                        <th>IP manzil</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="6" style="padding: 40px; text-align: center; color: #6b7280;">
                                <b>Jurnal bo'sh</b><br>
                                Hech qanday amal qayd etilmagan.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): 
                            // Amal turiga qarab badge rangi
                            $badge_class = 'badge-boshqa';
                            if (strpos($log['action'], 'kirish') !== false && strpos($log['action'], 'xato') === false) $badge_class = 'badge-kirish';
                            elseif ($log['action'] == 'chiqish') $badge_class = 'badge-chiqish';
                            elseif (strpos($log['action'], 'xato') !== false) $badge_class = 'badge-xato';
                            elseif (strpos($log['action'], 'hisobot') !== false) $badge_class = 'badge-hisobot';
                            
                            // Foydalanuvchi nomi (agar o'chirilgan bo'lsa)
                            $user_name = $log['login'] ?? 'Noma’lum (o‘chirilgan)';
                        ?>
                        <tr>
                            <td><?php echo $log['id']; ?></td>
                            <td style="font-size: 12px;"><?php echo date('d.m.Y H:i:s', strtotime($log['created_at'])); ?></td>
                            <td style="text-align: left;">
                                <b><?php echo htmlspecialchars($user_name); ?></b>
                                <?php if ($log['fio']): ?>
                                    <div style="font-size: 11px; color: #6b7280;"><?php echo htmlspecialchars($log['fio']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($log['action']); ?></span></td>
                            <td class="tafsilot" title="<?php echo htmlspecialchars($log['details'] ?? ''); ?>"><?php echo htmlspecialchars($log['details'] ?? ''); ?></td>
                            <td style="font-family: monospace; font-size: 12px;"><?php echo htmlspecialchars($log['ip_address'] ?? '—'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Sahifalash -->
        <?php if ($total_pages > 1): ?>
        <div class="sahifalash">
            <?php if ($current_page > 1): ?>
                <a href="?page=<?php echo $current_page - 1; ?>&user=<?php echo $filter_user; ?>&action=<?php echo $filter_action; ?>&date=<?php echo $filter_date; ?>" class="sahifa-tugma">← Oldingi</a>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <?php if ($i == $current_page): ?>
                    <span class="sahifa-tugma faol"><?php echo $i; ?></span>
                <?php elseif ($i >= $current_page - 2 && $i <= $current_page + 2): ?>
                    <a href="?page=<?php echo $i; ?>&user=<?php echo $filter_user; ?>&action=<?php echo $filter_action; ?>&date=<?php echo $filter_date; ?>" class="sahifa-tugma"><?php echo $i; ?></a>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($current_page < $total_pages): ?>
                <a href="?page=<?php echo $current_page + 1; ?>&user=<?php echo $filter_user; ?>&action=<?php echo $filter_action; ?>&date=<?php echo $filter_date; ?>" class="sahifa-tugma">Keyingi →</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>