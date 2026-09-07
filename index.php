<?php
require_once 'includes/auth.php';
check_admin();

$sana = $_GET['sana'] ?? date('Y-m-d');

// Admin prognozni saqlash (POST so'rovi)
if (isset($_POST['save_admin_prognoz'])) {
    foreach ($_POST['admin_prognoz'] as $tuman_id => $prognoz_val) {
        $tuman_id = (int)$tuman_id;
        $prognoz_val = (float)$prognoz_val;
        
        $stmt = $conn->prepare("SELECT id FROM hisobotlar WHERE tuman_id = ? AND sana = ?");
        $stmt->bind_param("is", $tuman_id, $sana);
        $stmt->execute();
        $mavjud = $stmt->get_result()->fetch_assoc();
        
        if ($mavjud) {
            $stmt = $conn->prepare("UPDATE hisobotlar SET oylik_prognoz_admin = ? WHERE id = ?");
            $stmt->bind_param("di", $prognoz_val, $mavjud['id']);
        } else {
            $stmt = $conn->prepare("INSERT INTO hisobotlar (tuman_id, sana, oylik_prognoz_admin) VALUES (?, ?, ?)");
            $stmt->bind_param("isd", $tuman_id, $sana, $prognoz_val);
        }
        $stmt->execute();
    }
    header("Location: index.php?sana=$sana&prognoz_saved=1");
    exit;
}

// Jami statistika
$stats = [
    'prognoz' => 0,
    'soliq_jami' => 0,
    'soliq_kunlik' => 0,
    'soliq_qoldiq' => 0,
    'topshirgan' => 0,
    'topshirmagan' => 13,
    'ogohlar' => 0
];

// Jami soliq prognozi (Endi ADMIN kiritganidan olinadi)
$res = $conn->query("SELECT SUM(oylik_prognoz_admin) as p FROM hisobotlar WHERE sana = '$sana'");
if($row = $res->fetch_assoc()) {
    $stats['prognoz'] = $row['p'] ?? 0;
}

// Jami soliq tushumi
$res = $conn->query("SELECT SUM(soliq_jami) as j, SUM(soliq_kunlik) as k, SUM(soliq_qoldiq) as q FROM hisobotlar WHERE sana = '$sana'");
if($row = $res->fetch_assoc()) {
    $stats['soliq_jami'] = $row['j'] ?? 0;
    $stats['soliq_kunlik'] = $row['k'] ?? 0;
    $stats['soliq_qoldiq'] = $row['q'] ?? 0;
}

// Hisobot topshirgan tumanlar
$res = $conn->query("SELECT COUNT(DISTINCT tuman_id) as cnt FROM hisobotlar WHERE sana = '$sana' AND status IN ('yuborilgan', 'tuzatildi')");
$stats['topshirgan'] = $res->fetch_assoc()['cnt'] ?? 0;
$stats['topshirmagan'] = 13 - $stats['topshirgan'];

// Nazorat ogohlari soni
$res = $conn->query("SELECT COUNT(*) as cnt FROM hisobotlar h WHERE h.sana = '$sana' AND h.bozor_raqam > h.bozor_soni");
$stats['ogohlar'] = $res->fetch_assoc()['cnt'] ?? 0;

// Tumanlar ro'yxati va holati
$tumanlar = [];
$res = $conn->query("SELECT * FROM tumanlar");
while($row = $res->fetch_assoc()) $tumanlar[$row['kalit']] = $row;

$tuman_holati = [];
$res = $conn->query("SELECT t.kalit, h.status, h.soliq_jami, h.prognoz, h.soliq_kunlik, h.oylik_prognoz_admin, h.bozor_soni, h.bozor_raqam, h.bozor_vm, h.bozor_soliq, h.und_jami, h.und_dsb, h.und_mib FROM tumanlar t LEFT JOIN hisobotlar h ON t.id = h.tuman_id AND h.sana = '$sana'");
while($row = $res->fetch_assoc()) {
    $holat = $row['status'] ?? 'yoq';
    if ($holat == 'qoralama') $holat = 'yoq'; 
    
    $tuman_holati[$row['kalit']] = [
        'holat' => $holat,
        'soliq_jami' => $row['soliq_jami'] ?? 0,
        'prognoz' => $row['oylik_prognoz_admin'] ?? 0,
        'soliq_kunlik' => $row['soliq_kunlik'] ?? 0,
        'bozor_soni' => $row['bozor_soni'] ?? 0,
        'bozor_raqam' => $row['bozor_raqam'] ?? 0,
        'bozor_vm' => $row['bozor_vm'] ?? 0,
        'bozor_soliq' => $row['bozor_soliq'] ?? 0,
        'und_jami' => $row['und_jami'] ?? 0,
        'und_dsb' => $row['und_dsb'] ?? 0,
        'und_mib' => $row['und_mib'] ?? 0,
    ];
}

// Jami bozorlar
$jami_bozor = ['mavjud' => 0, 'raqam' => 0, 'vm' => 0, 'soliq' => 0];
foreach($tuman_holati as $t) {
    $jami_bozor['mavjud'] += $t['bozor_soni'];
    $jami_bozor['raqam'] += $t['bozor_raqam'];
    $jami_bozor['vm'] += $t['bozor_vm'];
    $jami_bozor['soliq'] += $t['bozor_soliq'];
}

// Jami undirilgan qarz
$jami_und = ['jami' => 0, 'dsb' => 0, 'mib' => 0];
foreach($tuman_holati as $t) {
    $jami_und['jami'] += $t['und_jami'];
    $jami_und['dsb'] += $t['und_dsb'];
    $jami_und['mib'] += $t['und_mib'];
}

// Oxirgi 7 kun dinamikasi
$dinamika = [];
$res = $conn->query("SELECT sana, SUM(soliq_kunlik) as s FROM hisobotlar WHERE sana >= DATE_SUB('$sana', INTERVAL 7 DAY) GROUP BY sana ORDER BY sana");
while($row = $res->fetch_assoc()) $dinamika[] = ['sana' => $row['sana'], 'qiymat' => $row['s'] ?? 0];

include 'includes/header.php';
?>

<style>
/* Zamonaviy va tartibli CSS */
:root {
    --bg: #f4f6f9;
    --white: #ffffff;
    --border: #e5e7eb;
    --text-dark: #111827;
    --text-gray: #6b7280;
    --blue: #2563eb;
    --green: #16a34a;
    --red: #dc2626;
    --yellow: #d97706;
    --sidebar-bg: #1e293b;
    --shadow: 0 1px 3px rgba(0,0,0,0.1), 0 1px 2px rgba(0,0,0,0.06);
}

body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: var(--bg);
    margin: 0;
    color: var(--text-dark);
}

.content { padding: 20px; }

.top-controls {
    display: flex;
    gap: 10px;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.top-controls input[type="date"] {
    padding: 8px 12px;
    border: 1px solid var(--border);
    border-radius: 8px;
    font-size: 18px;
    background: var(--white);
    color: var(--text-dark);
}

.btn {
    padding: 8px 15px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.2s;
    text-decoration: none;
    display: inline-block;
}

.btn-blue { background: var(--blue); color: white; }
.btn-blue:hover { background: #1d4ed8; }
.btn-green { background: var(--green); color: white; }
.btn-green:hover { background: #15803d; }
.btn-gray { background: var(--bg); color: var(--text-dark); border: 1px solid var(--border); }
.btn-gray:hover { background: #e5e7eb; }

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 15px;
    margin-bottom: 30px;
}

.stat-card {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 15px;
    box-shadow: var(--shadow);
    position: relative;
    overflow: hidden;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 4px;
    background: var(--blue);
}

.stat-card.green::before { background: var(--green); }
.stat-card.yellow::before { background: var(--yellow); }
.stat-card.red::before { background: var(--red); }

.stat-card h3 {
    font-size: 12px;
    color: var(--text-gray);
    text-transform: uppercase;
    margin: 0 0 10px 0;
    letter-spacing: 0.5px;
}

.stat-card .value {
    font-size: 26px;
    font-weight: 700;
    color: var(--text-dark);
    margin-bottom: 5px;
}

.stat-card .value small { font-size: 13px; font-weight: 400; color: var(--text-gray); }
.stat-card .sub { font-size: 12px; color: var(--text-gray); }

.panel {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: 10px;
    box-shadow: var(--shadow);
    margin-bottom: 20px;
}

.panel-header {
    padding: 15px 20px;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.panel-header h3 { margin: 0; font-size: 16px; }
.panel-header p { margin: 3px 0 0; font-size: 13px; color: var(--text-gray); }
.panel-body { padding: 20px; }

.tuman-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 10px;
}

.tuman-chip {
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 10px;
    text-align: center;
    cursor: pointer;
    background: #f9fafb;
    transition: all 0.2s;
}

.tuman-chip:hover { border-color: var(--blue); background: white; }
.tuman-chip.icon { width: 24px; height: 24px; border-radius: 50%; margin: 0 auto 8px; display: flex; justify-content: center; align-items: center; font-size: 12px; }
.tuman-chip.done .icon { background: #dcfce7; color: var(--green); }
.tuman-chip.empty .icon { background: #f3f4f6; color: var(--text-gray); }
.tuman-chip small { font-weight: 600; display: block; margin-bottom: 5px; }
.tuman-chip .status-text { font-size: 11px; color: var(--text-gray); }

.grid-2 {
    display: grid;
    grid-template-columns: 1.2fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

@media (max-width: 900px) {
    .grid-2 { grid-template-columns: 1fr; }
}

.progress-bar {
    width: 100%;
    height: 8px;
    background: #e5e7eb;
    border-radius: 4px;
    margin-top: 5px;
    overflow: hidden;
}

.progress-bar div {
    height: 100%;
    background: var(--blue);
    border-radius: 4px;
}

.bozor-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 10px;
    margin-bottom: 15px;
}

.bozor-stat {
    padding: 15px;
    background: #f8fafc;
    border-radius: 8px;
    text-align: center;
}

.bozor-stat b { display: block; font-size: 22px; color: var(--blue); }
.bozor-stat span { font-size: 12px; color: var(--text-gray); }

.chart-container {
    height: 200px;
    display: flex;
    align-items: flex-end;
    gap: 10px;
    padding-top: 20px;
}

.chart-bar {
    flex: 1;
    background: linear-gradient(to top, var(--blue), #60a5fa);
    border-radius: 4px 4px 0 0;
    position: relative;
    min-height: 5px;
    transition: height 0.3s;
}

.chart-bar span {
    position: absolute;
    bottom: -25px;
    left: 50%;
    transform: translateX(-50%);
    font-size: 11px;
    color: var(--text-gray);
}

.empty-state {
    text-align: center;
    padding: 50px;
    color: var(--text-gray);
}

.empty-state svg { width: 50px; height: 50px; margin-bottom: 15px; color: #d1d5db; }
.empty-state b { display: block; font-size: 16px; margin-bottom: 5px; color: var(--text-dark); }

.map-container {
    background: #f8fafc;
    border-radius: 10px;
    padding: 20px;
    text-align: center;
    min-height: 300px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.pie-chart {
    width: 200px;
    height: 200px;
    border-radius: 50%;
    background: conic-gradient(
        var(--green) 0% var(--topshirgan-foiz),
        var(--red) var(--topshirgan-foiz) 100%
    );
    margin: 0 auto 15px;
    position: relative;
}

.pie-chart::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 90px;
    height: 90px;
    background: var(--white);
    border-radius: 50%;
}

.pie-chart .markaziy-raqam {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    z-index: 10;
    font-size: 18px;
    font-weight: 700;
    color: var(--text-dark);
}

.footer-note {
    margin-top: 20px;
    padding-top: 15px;
    border-top: 1px solid var(--border);
    font-size: 12px;
    color: var(--text-gray);
    line-height: 1.6;
}

/* ================= YANGI QO'SHILGAN: OYLIK PROGNOZ FORMASI ================= */
.prognoz-form-container {
    background: linear-gradient(145deg, #f8fafc, #eef2f7);
    border-radius: 24px;
    padding: 25px;
    box-shadow: inset 0 2px 4px rgba(0,0,0,0.05);
}

.prognoz-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.prognoz-item {
    background: #ffffff;
    border-radius: 16px;
    padding: 18px 20px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.06);
    transition: all 0.3s ease;
    border: 2px solid transparent;
}

.prognoz-item:hover {
    border-color: #2563eb;
    box-shadow: 0 8px 25px rgba(37, 99, 235, 0.15);
    transform: translateY(-3px);
}

.prognoz-item label {
    font-size: 15px;
    font-weight: 700;
    color: #1e293b;
    display: block;
    margin-bottom: 10px;
    letter-spacing: -0.2px;
}

.prognoz-item input {
    width: 100%;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    padding: 12px 16px;
    font-size: 18px;
    font-weight: 600;
    color: #1e293b;
    background: #f8fafc;
    transition: all 0.3s ease;
}

.prognoz-item input:focus {
    outline: none;
    border-color: #2563eb;
    background: #ffffff;
    box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
}

.prognoz-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    background: linear-gradient(135deg, #2563eb, #1e40af);
    color: white;
    border: none;
    border-radius: 50px;
    padding: 15px 30px;
    font-size: 16px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.4s ease;
    box-shadow: 0 4px 15px rgba(37, 99, 235, 0.3);
}

.prognoz-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(37, 99, 235, 0.4);
}

.prognoz-btn:active {
    transform: translateY(0);
    box-shadow: 0 2px 10px rgba(37, 99, 235, 0.3);
}
</style>

<div class="content">
    <div class="top-controls">
        <input type="date" value="<?php echo $sana; ?>" max="<?php echo date('Y-m-d'); ?>" 
               onchange="location.href='?sana='+this.value">
        <button class="btn btn-blue" onclick="location.href='?sana=<?php echo date('Y-m-d'); ?>'">Bugun</button>
        <a href="tumanlar.php?sana=<?php echo $sana; ?>" class="btn btn-green">Excel</a>
        <button class="btn btn-gray" onclick="window.print()">Chop etish</button>
    </div>

    <?php if(isset($_GET['prognoz_saved'])): ?>
        <div class="success-quti" style="background: #dcfce7; border: 1px solid #86efac; color: #166534; padding: 12px; border-radius: 8px; margin-bottom: 15px;">
            ✅ Oylik prognozlar muvaffaqiyatli saqlandi!
        </div>
    <?php endif; ?>

    <!-- STATISTIKA KARTALARI -->
    <div class="stats-grid">
        <div class="stat-card">
            <h3>Oylik prognoz</h3>
            <div class="value"><?php echo number_format($stats['prognoz'], 2); ?> <small>mlrd so'm</small></div>
            <div class="sub">13 tuman bo'yicha jami</div>
        </div>
        
        <div class="stat-card">
            <h3>Jami tushum</h3>
            <div class="value"><?php echo number_format($stats['soliq_jami'], 2); ?> <small>mlrd so'm</small></div>
            <div class="sub">
                Bajarilish: 
                <b style="color: <?php echo $stats['prognoz'] > 0 && ($stats['soliq_jami'] / $stats['prognoz']) * 100 >= 90 ? 'var(--green)' : 'var(--red)'; ?>">
                    <?php echo $stats['prognoz'] > 0 ? number_format(($stats['soliq_jami'] / $stats['prognoz']) * 100, 1) : '0'; ?>%
                </b>
            </div>
        </div>
        
        <div class="stat-card">
            <h3>Bugungi tushum</h3>
            <div class="value"><?php echo number_format($stats['soliq_kunlik'], 2); ?> <small>mlrd so'm</small></div>
            <div class="sub"><?php echo date('d-m-Y', strtotime($sana)); ?></div>
        </div>
        
        <div class="stat-card <?php echo $stats['soliq_qoldiq'] < 0 ? 'green' : 'red'; ?>">
            <h3>Oy oxiriga qoldiq</h3>
            <div class="value"><?php echo number_format(abs($stats['soliq_qoldiq']), 2); ?> <small>mlrd so'm</small></div>
            <div class="sub">
                <?php echo $stats['soliq_qoldiq'] < 0 ? 'Prognoz <b>oshirib</b> bajarilgan' : 'Prognozgacha yetmagan'; ?>
            </div>
        </div>
        
        <div class="stat-card <?php echo $stats['topshirmagan'] > 0 ? 'yellow' : 'green'; ?>">
            <h3>Hisobot topshirgan</h3>
            <div class="value"><?php echo $stats['topshirgan']; ?> / 13</div>
            <div class="sub">
                <?php if($stats['topshirmagan'] > 0): ?>
                    <b style="color: var(--red);"><?php echo $stats['topshirmagan']; ?></b> ta tuman topshirmagan
                <?php else: ?>
                    Barcha tumanlar topshirgan
                <?php endif; ?>
            </div>
        </div>
        
        <div class="stat-card <?php echo $stats['ogohlar'] > 0 ? 'red' : 'green'; ?>">
            <h3>Nazorat ogohlari</h3>
            <div class="value"><?php echo $stats['ogohlar']; ?></div>
            <div class="sub">
                <?php echo $stats['ogohlar'] > 0 ? 'Ma\'lumotlarda nomuvofiqlik' : 'Nomuvofiqlik yo\'q'; ?>
            </div>
        </div>
    </div>

    <!-- OYLIK PROGNOZ KIRITISH (ADMIN) - YANGI CHIROYLI FORMA -->
    <div class="panel">
        <div class="panel-header">
            <div>
                <h3>Oylik prognoz kiritish (Admin)</h3>
                <p>Har bir tuman uchun oylik prognozni shu yerda kiriting</p>
            </div>
        </div>
        <div class="panel-body">
            <div class="prognoz-form-container">
                <form method="POST" action="">
                    <div class="prognoz-grid">
                        <?php foreach ($tumanlar as $kalit => $t): 
                            $prognoz_val = 0;
                            $stmt = $conn->prepare("SELECT oylik_prognoz_admin FROM hisobotlar WHERE tuman_id = ? AND sana = ?");
                            $stmt->bind_param("is", $t['id'], $sana);
                            $stmt->execute();
                            $res_prog = $stmt->get_result()->fetch_assoc();
                            if ($res_prog) $prognoz_val = $res_prog['oylik_prognoz_admin'] ?? 0;
                        ?>
                        <div class="prognoz-item">
                            <label><?php echo htmlspecialchars($t['nom']); ?> (mlrd)</label>
                            <input type="number" step="0.01" name="admin_prognoz[<?php echo $t['id']; ?>]" value="<?php echo $prognoz_val; ?>">
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="submit" name="save_admin_prognoz" class="prognoz-btn">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Prognozlarni saqlash
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- HISOBOT TOPSHIRISH HOLATI -->
    <div class="panel">
        <div class="panel-header">
            <div>
                <h3>Hisobot topshirish holati</h3>
                <p>Har bir tuman uchun <?php echo date('d-m-Y', strtotime($sana)); ?> holati</p>
            </div>
        </div>
        <div class="panel-body">
            <?php 
            $topshirgan_foiz = $stats['topshirgan'] > 0 ? ($stats['topshirgan'] / 13) * 100 : 0;
            ?>
            <div class="pie-chart" style="--topshirgan-foiz: <?php echo $topshirgan_foiz; ?>%;">
                <div class="markaziy-raqam"><?php echo $stats['topshirgan']; ?>/13</div>
            </div>
            <div style="text-align: center; margin-bottom: 20px;">
                <span style="color: var(--green); font-weight: bold;"><?php echo $stats['topshirgan']; ?> ta topshirdi</span>
                <span style="margin: 0 10px; color: #ccc;">|</span>
                <span style="color: var(--red); font-weight: bold;"><?php echo $stats['topshirmagan']; ?> ta topshirmadi</span>
            </div>

            <div class="tuman-grid">
                <?php foreach($tuman_holati as $kalit => $t): ?>
                    <div class="tuman-chip <?php echo $t['holat'] == 'yuborilgan' ? 'done' : 'empty'; ?>" 
                         onclick="location.href='tumanlar.php?sana=<?php echo $sana; ?>&tuman=<?php echo $kalit; ?>'">
                        <div class="icon">
                            <?php if($t['holat'] == 'yuborilgan' || $t['holat'] == 'tuzatildi'): ?>
                                ✓
                            <?php else: ?>
                                !
                            <?php endif; ?>
                        </div>
                        <small><?php echo htmlspecialchars($tumanlar[$kalit]['nom'] ?? $kalit); ?></small>
                        <div class="status-text">
                            <?php echo ($t['holat'] == 'yuborilgan' || $t['holat'] == 'tuzatildi') ? 'Topshirilgan' : 'Kiritilmagan'; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- XARITA VA PROGNOZ -->
    <div class="grid-2">
        <div class="panel">
            <div class="panel-header">
                <div>
                    <h3>Hududiy kesim</h3>
                    <p>Prognoz bajarilishi bo'yicha</p>
                </div>
            </div>
            <div class="panel-body">
                <div class="map-container">
                    <svg viewBox="0 0 800 600" xmlns="http://www.w3.org/2000/svg" style="max-width: 100%; height: auto;">
                        <rect width="800" height="600" fill="#e5e7eb"/>
                        <g fill="#cbd5e1" stroke="#fff" stroke-width="2">
                            <rect x="50" y="50" width="150" height="100" rx="10"/>
                            <rect x="250" y="50" width="150" height="100" rx="10"/>
                            <rect x="450" y="50" width="150" height="100" rx="10"/>
                            <rect x="650" y="50" width="100" height="100" rx="10"/>
                            <rect x="50" y="200" width="150" height="100" rx="10"/>
                            <rect x="250" y="200" width="150" height="100" rx="10"/>
                            <rect x="450" y="200" width="150" height="100" rx="10"/>
                            <rect x="650" y="200" width="100" height="100" rx="10"/>
                            <rect x="50" y="350" width="150" height="100" rx="10"/>
                            <rect x="250" y="350" width="150" height="100" rx="10"/>
                            <rect x="450" y="350" width="150" height="100" rx="10"/>
                            <rect x="650" y="350" width="100" height="100" rx="10"/>
                            <rect x="100" y="500" width="200" height="80" rx="10"/>
                        </g>
                        <text x="125" y="105" text-anchor="middle" font-size="14" fill="#333">Arnasoy</text>
                        <text x="325" y="105" text-anchor="middle" font-size="14" fill="#333">Baxmal</text>
                        <text x="525" y="105" text-anchor="middle" font-size="14" fill="#333">G'allaorol</text>
                        <text x="700" y="105" text-anchor="middle" font-size="14" fill="#333">Forish</text>
                        <text x="125" y="255" text-anchor="middle" font-size="14" fill="#333">Do'stlik</text>
                        <text x="325" y="255" text-anchor="middle" font-size="14" fill="#333">Zarbdor</text>
                        <text x="525" y="255" text-anchor="middle" font-size="14" fill="#333">Zomin</text>
                        <text x="700" y="255" text-anchor="middle" font-size="14" fill="#333">Zafarobod</text>
                        <text x="125" y="405" text-anchor="middle" font-size="14" fill="#333">Mirzacho'l</text>
                        <text x="325" y="405" text-anchor="middle" font-size="14" fill="#333">Paxtakor</text>
                        <text x="525" y="405" text-anchor="middle" font-size="14" fill="#333">Jizzax shahri</text>
                        <text x="700" y="405" text-anchor="middle" font-size="14" fill="#333">Yangiobod</text>
                        <text x="200" y="545" text-anchor="middle" font-size="14" fill="#333">Sharof Rashidov</text>
                    </svg>
                </div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-header">
                <div>
                    <h3>Prognoz bajarilishi</h3>
                    <p>Ustun — bajarilish foizi, uzuq chiziq — 100% reja</p>
                </div>
            </div>
            <div class="panel-body">
                <?php if($stats['prognoz'] == 0): ?>
                    <div class="empty-state">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><path d="M8 12h8M12 8v8"/></svg>
                        <b>Prognoz kiritilmagan</b>
                        <p>Bu davrda hisobot mavjud emas</p>
                    </div>
                <?php else: ?>
                    <?php 
                    $tartiblangan = [];
                    foreach($tuman_holati as $kalit => $t) {
                        if($t['prognoz'] > 0) {
                            $foiz = ($t['soliq_jami'] / $t['prognoz']) * 100;
                            $tartiblangan[] = [
                                'nom' => $tumanlar[$kalit]['nom'] ?? $kalit,
                                'foiz' => $foiz,
                                'soliq_jami' => $t['soliq_jami'],
                                'prognoz' => $t['prognoz']
                            ];
                        }
                    }
                    usort($tartiblangan, function($a, $b) { return $b['foiz'] <=> $a['foiz']; });
                    $eng = max(120, ...array_column($tartiblangan, 'foiz'));
                    ?>
                    <div style="margin-bottom: 10px;">
                        <?php foreach($tartiblangan as $r): ?>
                        <div style="margin-bottom: 12px;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 4px; font-size: 13px;">
                                <span style="font-weight: 500;"><?php echo htmlspecialchars($r['nom']); ?></span>
                                <span style="font-family: monospace; color: <?php echo $r['foiz'] >= 100 ? 'var(--green)' : 'var(--blue)'; ?>;">
                                    <?php echo number_format($r['foiz'], 1); ?>%
                                </span>
                            </div>
                            <div class="progress-bar">
                                <div style="width: <?php echo min(100, ($r['foiz'] / $eng) * 100); ?>%;"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- BOZORLARNI RAQAMLASHTIRISH VA QARZ -->
    <div class="grid-2">
        <div class="panel">
            <div class="panel-header">
                <div>
                    <h3>Bozorlarni raqamlashtirish</h3>
                    <p>Bosqichma-bosqich qamrov (bozor soni)</p>
                </div>
            </div>
            <div class="panel-body">
                <div class="bozor-stats">
                    <div class="bozor-stat">
                        <b><?php echo number_format($jami_bozor['mavjud']); ?></b>
                        <span>Mavjud bozorlar</span>
                    </div>
                    <div class="bozor-stat">
                        <b><?php echo number_format($jami_bozor['raqam']); ?></b>
                        <span>Raqamlashtirilgan</span>
                    </div>
                    <div class="bozor-stat">
                        <b><?php echo number_format($jami_bozor['vm']); ?></b>
                        <span>Vaziyatlar markaziga ulangan</span>
                    </div>
                    <div class="bozor-stat">
                        <b><?php echo number_format($jami_bozor['soliq']); ?></b>
                        <span>Soliq organiga ulangan</span>
                    </div>
                </div>
                <div style="margin-top: 20px;">
                    <?php 
                    $foiz1 = $jami_bozor['mavjud'] > 0 ? ($jami_bozor['raqam'] / $jami_bozor['mavjud']) * 100 : 0;
                    $foiz2 = $jami_bozor['raqam'] > 0 ? ($jami_bozor['vm'] / $jami_bozor['raqam']) * 100 : 0;
                    $foiz3 = $jami_bozor['raqam'] > 0 ? ($jami_bozor['soliq'] / $jami_bozor['raqam']) * 100 : 0;
                    ?>
                    <div style="margin-bottom: 10px;">
                        <div style="font-size: 12px; color: var(--text-gray); margin-bottom: 4px;">Raqamlashtirilgan (<?php echo number_format($foiz1, 1); ?>%)</div>
                        <div class="progress-bar"><div style="width: <?php echo $foiz1; ?>%;"></div></div>
                    </div>
                    <div style="margin-bottom: 10px;">
                        <div style="font-size: 12px; color: var(--text-gray); margin-bottom: 4px;">VMga ulangan (<?php echo number_format($foiz2, 1); ?>%)</div>
                        <div class="progress-bar"><div style="width: <?php echo $foiz2; ?>%;"></div></div>
                    </div>
                    <div>
                        <div style="font-size: 12px; color: var(--text-gray); margin-bottom: 4px;">Soliq organiga ulangan (<?php echo number_format($foiz3, 1); ?>%)</div>
                        <div class="progress-bar"><div style="width: <?php echo $foiz3; ?>%;"></div></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-header">
                <div>
                    <h3>Undirilgan soliq qarzi</h3>
                    <p>Idoralar kesimida (mlrd so'm)</p>
                </div>
            </div>
            <div class="panel-body">
                <?php if($jami_und['jami'] == 0): ?>
                    <div class="empty-state">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><path d="M8 12h8M12 8v8"/></svg>
                        <b>Undirilgan qarz kiritilmagan</b>
                        <p>Bu davrda hisobot mavjud emas</p>
                    </div>
                <?php else: ?>
                    <div class="bozor-stats" style="grid-template-columns: repeat(3, 1fr);">
                        <div class="bozor-stat">
                            <b><?php echo number_format($jami_und['dsb'], 2); ?></b>
                            <span>DSB orqali</span>
                        </div>
                        <div class="bozor-stat">
                            <b><?php echo number_format($jami_und['mib'], 2); ?></b>
                            <span>MIB orqali</span>
                        </div>
                        <div class="bozor-stat">
                            <b><?php echo number_format($jami_und['jami'], 2); ?></b>
                            <span>Jami</span>
                        </div>
                    </div>
                    <div style="margin-top: 20px;">
                        <?php 
                        $dsb_foiz = $jami_und['jami'] > 0 ? ($jami_und['dsb'] / $jami_und['jami']) * 100 : 0;
                        $mib_foiz = 100 - $dsb_foiz;
                        ?>
                        <div style="display: flex; height: 30px; border-radius: 5px; overflow: hidden;">
                            <div style="width: <?php echo $dsb_foiz; ?>%; background: var(--blue);"></div>
                            <div style="width: <?php echo $mib_foiz; ?>%; background: #eb6834;"></div>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-top: 8px; font-size: 12px; color: var(--text-gray);">
                            <span><span style="display: inline-block; width: 10px; height: 10px; background: var(--blue); border-radius: 2px; margin-right: 4px;"></span>DSB (<?php echo number_format($dsb_foiz, 1); ?>%)</span>
                            <span><span style="display: inline-block; width: 10px; height: 10px; background: #eb6834; border-radius: 2px; margin-right: 4px;"></span>MIB (<?php echo number_format($mib_foiz, 1); ?>%)</span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- KUNLIK SOLIQ TUSHUMI DINAMIKASI -->
    <div class="panel">
        <div class="panel-header">
            <div>
                <h3>Kunlik soliq tushumi</h3>
                <p>Oxirgi 7 kun, viloyat bo'yicha jami (mlrd so'm)</p>
            </div>
        </div>
        <div class="panel-body">
            <?php if(empty($dinamika)): ?>
                <div class="empty-state">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><path d="M8 12h8M12 8v8"/></svg>
                    <b>Ma'lumot yetarli emas</b>
                    <p>Oxirgi 7 kun ichida hisobot kiritilmagan</p>
                </div>
            <?php else: ?>
                <div class="chart-container">
                    <?php 
                    $max = max(array_column($dinamika, 'qiymat')) ?: 1;
                    foreach($dinamika as $d): 
                        $balandlik = ($d['qiymat'] / $max) * 100;
                    ?>
                    <div class="chart-bar" style="height: <?php echo max(10, $balandlik); ?>%;" title="<?php echo number_format($d['qiymat'], 2); ?> mln">
                        <span><?php echo date('d.m', strtotime($d['sana'])); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div style="display: flex; justify-content: space-between; margin-top: 30px; font-size: 11px; color: var(--text-gray);">
                    <span>Eng past kun: <?php echo number_format(min(array_column($dinamika, 'qiymat')), 2); ?> mlrd</span>
                    <span>Eng yuqori kun: <?php echo number_format(max(array_column($dinamika, 'qiymat')), 2); ?> mlrd</span>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="footer-note">
        Ma'lumotlar tumanlar ishchi guruhlari tomonidan kiritiladi. O'lchov birligi — <b>mlrd so'm</b>. Hisoblanadigan ustunlar (oy oxiriga qoldiq, o'tgan kunga nisbatan farq) tizim tomonidan avtomatik hisoblanadi.
        <br>Ma'lumot kiritilmagan katak <b>bo'sh</b> ko'rsatiladi — nol sifatida hisoblanmaydi.
    </div>
</div>

<?php include 'includes/footer.php'; ?>