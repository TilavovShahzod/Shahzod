<?php
require_once 'includes/auth.php';
check_admin(); // Faqat viloyat admini kira oladi

// Xabarlar
$message = "";
$error = "";

// Foydalanuvchini o'chirish
if (isset($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    if ($stmt->execute()) {
        $message = "Foydalanuvchi muvaffaqiyatli o'chirildi!";
        jurnal('foydalanuvchi_ochirish', "ID: $delete_id");
    } else {
        $error = "O'chirishda xatolik: " . $conn->error;
    }
}

// Forma yuborilganda (Yangi qo'shish yoki Tahrirlash)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? 0;
    $login = trim($_POST['login']);
    $password = trim($_POST['password']);
    $role = $_POST['role'] ?? 'tuman';
    $fio = trim($_POST['fio']);
    $hudud_id = $_POST['hudud_id'] ?? null;
    $active = isset($_POST['active']) ? 1 : 0;

    // Login mavjudligini tekshirish
    $stmt = $conn->prepare("SELECT * FROM users WHERE login = ? AND id != ?");
    $stmt->bind_param("si", $login, $id);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    
    if ($existing) {
        $error = "Bu login allaqachon mavjud!";
    } elseif (empty($login)) {
        $error = "Login bo'sh bo'lishi mumkin emas!";
    } else {
        if ($id > 0) {
            // Tahrirlash
            if (!empty($password)) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET login = ?, password_hash = ?, role = ?, fio = ?, hudud_id = ?, active = ? WHERE id = ?");
                $stmt->bind_param("ssssiii", $login, $hash, $role, $fio, $hudud_id, $active, $id);
            } else {
                // Parol o'zgartirilmagan bo'lsa
                $stmt = $conn->prepare("UPDATE users SET login = ?, role = ?, fio = ?, hudud_id = ?, active = ? WHERE id = ?");
                $stmt->bind_param("sssiii", $login, $role, $fio, $hudud_id, $active, $id);
            }
            if ($stmt->execute()) {
                $message = "Foydalanuvchi muvaffaqiyatli tahrirlandi!";
                jurnal('foydalanuvchi_tahrirlash', "Login: $login");
            } else {
                $error = "Tahrirlashda xatolik: " . $conn->error;
            }
        } else {
            // Yangi qo'shish
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (login, password_hash, role, fio, hudud_id, active) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssii", $login, $hash, $role, $fio, $hudud_id, $active);
            if ($stmt->execute()) {
                $message = "Yangi foydalanuvchi muvaffaqiyatli qo'shildi!";
                jurnal('foydalanuvchi_qoshish', "Login: $login");
            } else {
                $error = "Qo'shishda xatolik: " . $conn->error;
            }
        }
    }
}

// Barcha foydalanuvchilarni olish
$users = [];
$res = $conn->query("SELECT * FROM users ORDER BY id DESC");
while ($row = $res->fetch_assoc()) $users[] = $row;

// Tumanlar ro'yxati (dropdown uchun)
$tumanlar = [];
$res = $conn->query("SELECT * FROM tumanlar ORDER BY id ASC");
while ($row = $res->fetch_assoc()) $tumanlar[] = $row;

include 'includes/header.php';
?>

<style>
    .admin-quti {
        background: #fff;
        border: 1px solid #D8E0EC;
        border-radius: 12px;
        padding: 25px;
        margin-bottom: 20px;
        box-shadow: 0 4px 12px rgba(23,35,59,0.05);
    }

    .admin-quti h3 {
        margin-bottom: 15px;
        font-size: 18px;
        color: #17233B;
        padding-bottom: 10px;
        border-bottom: 1px solid #e5e7eb;
    }

    .form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
    }

    .form-group {
        margin-bottom: 12px;
    }

    .form-group label {
        font-size: 13px;
        color: #6b7280;
        font-weight: 600;
        margin-bottom: 5px;
        display: block;
    }

    .form-group input,
    .form-group select {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid #D8E0EC;
        border-radius: 6px;
        font-size: 14px;
        color: #17233B;
        transition: 0.2s;
    }

    .form-group input:focus,
    .form-group select:focus {
        border-color: #1c5cab;
        outline: none;
        box-shadow: 0 0 0 3px rgba(28,92,171,0.1);
    }

    .form-group.checkbox {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-top: 25px;
    }

    .form-group.checkbox input {
        width: auto;
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
    .btn-success { background: #16a34a; color: #fff; border-color: #16a34a; }
    .btn-success:hover { background: #15803d; }
    .btn-danger { background: #dc2626; color: #fff; border-color: #dc2626; }
    .btn-danger:hover { background: #b91c1c; }

    .table-responsive { overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; min-width: 700px; }
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

    .badge-admin { background: #dcfce7; color: #166534; }
    .badge-tuman { background: #e0f2fe; color: #075985; }
    .badge-yashil { background: #dcfce7; color: #166534; }
    .badge-qizil { background: #fee2e2; color: #991b1b; }

    .tahrirlash-btn, .ochirish-btn {
        padding: 6px 10px;
        border-radius: 6px;
        font-size: 12px;
        cursor: pointer;
        text-decoration: none;
        display: inline-block;
        margin-right: 4px;
    }

    .tahrirlash-btn { background: #f3f4f6; color: #374151; border: 1px solid #e5e7eb; }
    .ochirish-btn { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
</style>

<div class="content">
    <div class="boshqaruv" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 25px;">
        <div class="left">
            <h2 style="font-size: 24px; color: #17233B; margin-bottom: 5px;">Foydalanuvchilar</h2>
            <p style="font-size: 13px; color: #6b7280;">Barcha tizim foydalanuvchilarini boshqarish</p>
        </div>
        <div>
            <button class="btn btn-primary" onclick="document.getElementById('yangi').style.display='block'">Yangi foydalanuvchi</button>
        </div>
    </div>

    <?php if (isset($message)): ?>
        <div class="success-quti" style="background: #E8F6E8; color: #0a6b0a; border: 1px solid #a7f3d0; padding: 12px; border-radius: 8px; margin-bottom: 15px; font-size: 14px;"><?php echo $message; ?></div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="xato-quti" style="background: #FDECEC; color: #8E2020; border: 1px solid #F3C4C4; padding: 12px; border-radius: 8px; margin-bottom: 15px; font-size: 14px;"><?php echo $error; ?></div>
    <?php endif; ?>

    <!-- Yangi foydalanuvchi qo'shish formasi -->
    <div class="admin-quti" id="yangi" style="display: none;">
        <h3>Yangi foydalanuvchi qo'shish</h3>
        <form method="POST" action="">
            <input type="hidden" name="id" value="0">
            <div class="form-grid">
                <div class="form-group">
                    <label>Login</label>
                    <input type="text" name="login" required>
                </div>
                <div class="form-group">
                    <label>Parol</label>
                    <input type="password" name="password" required>
                </div>
                <div class="form-group">
                    <label>F.I.Sh</label>
                    <input type="text" name="fio" required>
                </div>
                <div class="form-group">
                    <label>Rol</label>
                    <select name="role" required>
                        <option value="tuman">Tuman xodimi</option>
                        <option value="viloyat">Viloyat administratori</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Tuman (agar tuman xodimi bo'lsa)</label>
                    <select name="hudud_id">
                        <option value="">-- Tanlanmagan --</option>
                        <?php foreach ($tumanlar as $t): ?>
                            <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['nom']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group checkbox">
                    <input type="checkbox" name="active" id="active" checked>
                    <label for="active" style="margin: 0;">Faol</label>
                </div>
            </div>
            <div style="margin-top: 20px; display: flex; gap: 10px;">
                <button type="submit" class="btn btn-success">Saqlash</button>
                <button type="button" class="btn" onclick="document.getElementById('yangi').style.display='none'">Yopish</button>
            </div>
        </form>
    </div>

    <!-- Tahrirlash formasi (dinamik) -->
    <div class="admin-quti" id="tahrirlash" style="display: none;">
        <h3>Foydalanuvchini tahrirlash</h3>
        <form method="POST" action="">
            <input type="hidden" name="id" id="edit_id">
            <div class="form-grid">
                <div class="form-group">
                    <label>Login</label>
                    <input type="text" name="login" id="edit_login" required>
                </div>
                <div class="form-group">
                    <label>Parol (o'zgartirish uchun)</label>
                    <input type="password" name="password" placeholder="Bo'sh qoldirish mumkin">
                </div>
                <div class="form-group">
                    <label>F.I.Sh</label>
                    <input type="text" name="fio" id="edit_fio" required>
                </div>
                <div class="form-group">
                    <label>Rol</label>
                    <select name="role" id="edit_role" required>
                        <option value="tuman">Tuman xodimi</option>
                        <option value="viloyat">Viloyat administratori</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Tuman (agar tuman xodimi bo'lsa)</label>
                    <select name="hudud_id" id="edit_hudud_id">
                        <option value="">-- Tanlanmagan --</option>
                        <?php foreach ($tumanlar as $t): ?>
                            <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['nom']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group checkbox">
                    <input type="checkbox" name="active" id="edit_active">
                    <label for="edit_active" style="margin: 0;">Faol</label>
                </div>
            </div>
            <div style="margin-top: 20px; display: flex; gap: 10px;">
                <button type="submit" class="btn btn-success">Yangilash</button>
                <button type="button" class="btn" onclick="document.getElementById('tahrirlash').style.display='none'">Yopish</button>
            </div>
        </form>
    </div>

    <!-- Barcha foydalanuvchilar ro'yxati -->
    <div class="admin-quti">
        <h3>Mavjud foydalanuvchilar</h3>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Login</th>
                        <th>F.I.Sh</th>
                        <th>Rol</th>
                        <th>Tuman</th>
                        <th>Faollik</th>
                        <th>Amallar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): 
                        $rol_text = $u['role'] == 'viloyat' ? 'Viloyat admini' : 'Tuman xodimi';
                        $rol_badge = $u['role'] == 'viloyat' ? 'badge-admin' : 'badge-tuman';
                        $faollik = $u['active'] == 1 ? 'Faol' : 'Bloklangan';
                        $faollik_badge = $u['active'] == 1 ? 'badge-yashil' : 'badge-qizil';
                        
                        // Tuman nomini topish
                        $tuman_nomi = '—';
                        if ($u['hudud_id']) {
                            foreach ($tumanlar as $t) {
                                if ($t['id'] == $u['hudud_id']) $tuman_nomi = $t['nom'];
                            }
                        }
                    ?>
                    <tr>
                        <td><?php echo $u['id']; ?></td>
                        <td style="text-align: left; font-weight: 600;"><?php echo htmlspecialchars($u['login']); ?></td>
                        <td style="text-align: left;"><?php echo htmlspecialchars($u['fio']); ?></td>
                        <td><span class="badge <?php echo $rol_badge; ?>"><?php echo $rol_text; ?></span></td>
                        <td><?php echo htmlspecialchars($tuman_nomi); ?></td>
                        <td><span class="badge <?php echo $faollik_badge; ?>"><?php echo $faollik; ?></span></td>
                        <td>
                            <!-- Tahrirlash tugmasi -->
                            <button class="tahrirlash-btn" onclick="tahrirlash(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars($u['login']); ?>', '<?php echo htmlspecialchars($u['fio']); ?>', '<?php echo $u['role']; ?>', <?php echo $u['hudud_id'] ?? 'null'; ?>, <?php echo $u['active']; ?>)">Tahrirlash</button>
                            
                            <!-- O'chirish tugmasi -->
                            <a href="?delete=<?php echo $u['id']; ?>" class="ochirish-btn" onclick="return confirm('Rostdan ham o\'chirmoqchimisiz?')">O'chirish</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    // Tahrirlash formasi ma'lumotlarini to'ldirish
    function tahrirlash(id, login, fio, role, hudud_id, active) {
        document.getElementById('tahrirlash').style.display = 'block';
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_login').value = login;
        document.getElementById('edit_fio').value = fio;
        document.getElementById('edit_role').value = role;
        
        // Tumanni tanlash
        var hududSelect = document.getElementById('edit_hudud_id');
        if (hudud_id) {
            hududSelect.value = hudud_id;
        } else {
            hududSelect.value = '';
        }
        
        // Faollikni belgilash
        document.getElementById('edit_active').checked = active === 1;
        
        // Formaga scroll qilish
        document.getElementById('tahrirlash').scrollIntoView({ behavior: 'smooth' });
    }
</script>

<?php include 'includes/footer.php'; ?>