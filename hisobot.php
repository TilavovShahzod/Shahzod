<?php
require_once 'includes/auth.php';
check_admin();

$sana = $_GET['sana'] ?? date('Y-m-d');

// OY BOSHINI ANIQLASH
$oy_boshi = date('Y-m-01', strtotime($sana));

// O'CHIRISH TUGMASI ISHLASHI
if (isset($_GET['delete'])) {
    $delete_tuman_id = (int)$_GET['delete'];
    
    // Faqat shu sana va shu tuman uchun hisobotni o'chirish
    $stmt = $conn->prepare("DELETE FROM hisobotlar WHERE tuman_id = ? AND sana = ?");
    $stmt->bind_param("is", $delete_tuman_id, $sana);
    
    if ($stmt->execute()) {
        // Jurnalga yozish
        $tuman_nomi = "Tuman ID: $delete_tuman_id";
        $res = $conn->query("SELECT * FROM tumanlar WHERE id = $delete_tuman_id");
        if ($row = $res->fetch_assoc()) $tuman_nomi = $row['nom'];
        
        jurnal('hisobot_ochirish', "Sana: $sana, Tuman: $tuman_nomi");
        
        // Sahifani yangilash
        header("Location: tumanlar.php?sana=$sana&deleted=1");
        exit;
    } else {
        $error = "O'chirishda xatolik: " . $conn->error;
    }
}

// 1. Tumanlar ro'yxatini olish
$tumanlar = [];
$res = $conn->query("SELECT * FROM tumanlar ORDER BY id ASC");
while ($row = $res->fetch_assoc()) $tumanlar[] = $row;

// 2. OY BOSHIDAN SHU KUNGACHA BARCHA HISOBOTLARNI OLIB YIG'AMIZ
$hisobotlar = [];
$res = $conn->query("SELECT * FROM hisobotlar WHERE sana BETWEEN '$oy_boshi' AND '$sana'");
while ($row = $res->fetch_assoc()) {
    $tuman_id = $row['tuman_id'];
    
    // Har bir tuman uchun ustunlarni yig'indisini olamiz
    if (!isset($hisobotlar[$tuman_id])) {
        $hisobotlar[$tuman_id] = [
            'oylik_prognoz_admin' => 0,
            'soliq_jami' => 0,
            'soliq_kunlik' => 0,
            'soliq_qoldiq' => 0,
            'qm_reja' => 0, 'qm_jami' => 0, 'qm_kunlik' => 0, 'qm_qoldiq' => 0,
            'savdo_jami' => 0, 'savdo_kunlik' => 0, 'savdo_farq' => 0,
            'bozor_soni' => 0, 'bozor_raqam' => 0, 'bozor_vm' => 0, 'bozor_soliq' => 0,
            'bt_jami' => 0, 'bt_kunlik' => 0, 'bt_farq' => 0,
            // Bandlik maydonlari
            'bandlik_jami' => 0, 'bandlik_kunlik' => 0, 'bandlik_farq' => 0,
            'norasmiy_band_jami' => 0, 
            'yangi_ish_orni_jami' => 0,
            // Qarz maydonlari
            'qarz_boqimanda' => 0, 'und_jami' => 0, 'und_dsb' => 0, 'und_mib' => 0,
            'und_kunlik' => 0, 'und_kun_dsb' => 0
        ];
    }
    
    // Har bir ustunni qo'shamiz
    $hisobotlar[$tuman_id]['oylik_prognoz_admin'] += $row['oylik_prognoz_admin'] ?? 0;
    $hisobotlar[$tuman_id]['soliq_jami'] += $row['soliq_jami'] ?? 0;
    $hisobotlar[$tuman_id]['soliq_kunlik'] += $row['soliq_kunlik'] ?? 0;
    $hisobotlar[$tuman_id]['soliq_qoldiq'] += $row['soliq_qoldiq'] ?? 0;
    $hisobotlar[$tuman_id]['qm_reja'] += $row['qm_reja'] ?? 0;
    $hisobotlar[$tuman_id]['qm_jami'] += $row['qm_jami'] ?? 0;
    $hisobotlar[$tuman_id]['qm_kunlik'] += $row['qm_kunlik'] ?? 0;
    $hisobotlar[$tuman_id]['qm_qoldiq'] += $row['qm_qoldiq'] ?? 0;
    $hisobotlar[$tuman_id]['savdo_jami'] += $row['savdo_jami'] ?? 0;
    $hisobotlar[$tuman_id]['savdo_kunlik'] += $row['savdo_kunlik'] ?? 0;
    $hisobotlar[$tuman_id]['savdo_farq'] += $row['savdo_farq'] ?? 0;
    $hisobotlar[$tuman_id]['bozor_soni'] += $row['bozor_soni'] ?? 0;
    $hisobotlar[$tuman_id]['bozor_raqam'] += $row['bozor_raqam'] ?? 0;
    $hisobotlar[$tuman_id]['bozor_vm'] += $row['bozor_vm'] ?? 0;
    $hisobotlar[$tuman_id]['bozor_soliq'] += $row['bozor_soliq'] ?? 0;
    $hisobotlar[$tuman_id]['bt_jami'] += $row['bt_jami'] ?? 0;
    $hisobotlar[$tuman_id]['bt_kunlik'] += $row['bt_kunlik'] ?? 0;
    $hisobotlar[$tuman_id]['bt_farq'] += $row['bt_farq'] ?? 0;
    
    // Bandlik maydonlari (yangilangan nomlar bilan)
    $hisobotlar[$tuman_id]['bandlik_jami'] += $row['bandlik_jami'] ?? 0;
    $hisobotlar[$tuman_id]['bandlik_kunlik'] += $row['bandlik_kunlik'] ?? 0;
    $hisobotlar[$tuman_id]['bandlik_farq'] += $row['bandlik_farq'] ?? 0;
    $hisobotlar[$tuman_id]['norasmiy_band_jami'] += $row['norasmiy_band_jami'] ?? 0;
    $hisobotlar[$tuman_id]['yangi_ish_orni_jami'] += $row['yangi_ish_orni_jami'] ?? 0;
    
    // Qarz maydonlari
    $hisobotlar[$tuman_id]['qarz_boqimanda'] += $row['qarz_boqimanda'] ?? 0;
    $hisobotlar[$tuman_id]['und_jami'] += $row['und_jami'] ?? 0;
    $hisobotlar[$tuman_id]['und_dsb'] += $row['und_dsb'] ?? 0;
    $hisobotlar[$tuman_id]['und_mib'] += $row['und_mib'] ?? 0;
    $hisobotlar[$tuman_id]['und_kunlik'] += $row['und_kunlik'] ?? 0;
    $hisobotlar[$tuman_id]['und_kun_dsb'] += $row['und_kun_dsb'] ?? 0;
}

// 3. Jami hisob-kitob (Jami qator uchun)
$jami = [
    'oylik_prognoz_admin' => 0, 'soliq_jami' => 0, 'soliq_kunlik' => 0, 'soliq_qoldiq' => 0,
    'qm_reja' => 0, 'qm_jami' => 0, 'qm_kunlik' => 0, 'qm_qoldiq' => 0,
    'savdo_jami' => 0, 'savdo_kunlik' => 0, 'savdo_farq' => 0,
    'bozor_soni' => 0, 'bozor_raqam' => 0, 'bozor_vm' => 0, 'bozor_soliq' => 0,
    'bt_jami' => 0, 'bt_kunlik' => 0, 'bt_farq' => 0,
    // Bandlik
    'bandlik_jami' => 0, 'bandlik_kunlik' => 0, 'bandlik_farq' => 0,
    'norasmiy_band_jami' => 0, 'yangi_ish_orni_jami' => 0,
    // Qarz
    'qarz_boqimanda' => 0, 'und_jami' => 0, 'und_dsb' => 0, 'und_mib' => 0,
    'und_kunlik' => 0, 'und_kun_dsb' => 0
];

foreach ($hisobotlar as $h) {
    $jami['oylik_prognoz_admin'] += $h['oylik_prognoz_admin'] ?? 0;
    $jami['soliq_jami'] += $h['soliq_jami'] ?? 0;
    $jami['soliq_kunlik'] += $h['soliq_kunlik'] ?? 0;
    $jami['soliq_qoldiq'] += $h['soliq_qoldiq'] ?? 0;
    $jami['qm_reja'] += $h['qm_reja'] ?? 0;
    $jami['qm_jami'] += $h['qm_jami'] ?? 0;
    $jami['qm_kunlik'] += $h['qm_kunlik'] ?? 0;
    $jami['qm_qoldiq'] += $h['qm_qoldiq'] ?? 0;
    $jami['savdo_jami'] += $h['savdo_jami'] ?? 0;
    $jami['savdo_kunlik'] += $h['savdo_kunlik'] ?? 0;
    $jami['savdo_farq'] += $h['savdo_farq'] ?? 0;
    $jami['bozor_soni'] += $h['bozor_soni'] ?? 0;
    $jami['bozor_raqam'] += $h['bozor_raqam'] ?? 0;
    $jami['bozor_vm'] += $h['bozor_vm'] ?? 0;
    $jami['bozor_soliq'] += $h['bozor_soliq'] ?? 0;
    $jami['bt_jami'] += $h['bt_jami'] ?? 0;
    $jami['bt_kunlik'] += $h['bt_kunlik'] ?? 0;
    $jami['bt_farq'] += $h['bt_farq'] ?? 0;
    
    // Bandlik
    $jami['bandlik_jami'] += $h['bandlik_jami'] ?? 0;
    $jami['bandlik_kunlik'] += $h['bandlik_kunlik'] ?? 0;
    $jami['bandlik_farq'] += $h['bandlik_farq'] ?? 0;
    $jami['norasmiy_band_jami'] += $h['norasmiy_band_jami'] ?? 0;
    $jami['yangi_ish_orni_jami'] += $h['yangi_ish_orni_jami'] ?? 0;
    
    // Qarz
    $jami['qarz_boqimanda'] += $h['qarz_boqimanda'] ?? 0;
    $jami['und_jami'] += $h['und_jami'] ?? 0;
    $jami['und_dsb'] += $h['und_dsb'] ?? 0;
    $jami['und_mib'] += $h['und_mib'] ?? 0;
    $jami['und_kunlik'] += $h['und_kunlik'] ?? 0;
    $jami['und_kun_dsb'] += $h['und_kun_dsb'] ?? 0;
}

include 'includes/header.php';
?>

<style>
    .page-controls {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding: 15px;
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 8px;
    }

    .btn {
        padding: 10px 18px;
        border: 1px solid #ccc;
        background: #fff;
        border-radius: 5px;
        cursor: pointer;
        font-size: 15px;
        transition: 0.2s;
    }
    .btn:hover { background: #f0f0f0; }
    .btn-primary { background: #1c5cab; color: white; border: none; }
    .btn-success { background: #16a34a; color: white; border: none; }
    
    .btn-edit {
        background: #1c5cab;
        color: white;
        border: none;
        padding: 6px 12px;
        font-size: 13px;
        border-radius: 4px;
        cursor: pointer;
        text-decoration: none;
    }
    .btn-edit:hover { background: #12395C; }

    .btn-danger {
        background: #dc2626;
        color: white;
        border: none;
        padding: 6px 12px;
        font-size: 13px;
        border-radius: 4px;
        cursor: pointer;
    }
    .btn-danger:hover { background: #b91c1c; }

    .success-message {
        background: #dcfce7;
        border: 1px solid #86efac;
        color: #166534;
        padding: 12px;
        border-radius: 8px;
        margin-bottom: 15px;
        font-size: 14px;
    }

    .xato-quti {
        background: #fee2e2;
        border: 1px solid #fecaca;
        color: #991b1b;
        padding: 12px;
        border-radius: 8px;
        margin-bottom: 15px;
        font-size: 14px;
    }

    .table-wrapper {
        background: #fff;
        border: 2px solid #000;
        overflow-x: auto;
        margin-bottom: 20px;
        padding: 5px;
    }

    table.report-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 14px;
        font-family: 'Times New Roman', Times, serif;
        min-width: 3100px;
    }

    .report-table thead tr.main-title th {
        background: #f0f0f0;
        border: 2px solid #000;
        padding: 15px;
        font-size: 18px;
        font-weight: bold;
        text-align: center;
        color: #000;
    }

    .report-table thead tr.group-title th {
        background: #e8e8e8;
        border: 2px solid #000;
        padding: 12px;
        font-size: 15px;
        font-weight: bold;
        text-align: center;
        color: #000;
    }

    .report-table thead tr.sub-title th {
        background: #fafafa;
        border: 2px solid #000;
        padding: 12px;
        font-size: 13px;
        font-weight: 700;
        text-align: center;
    }

    .report-table tbody td {
        border: 1px solid #000;
        padding: 12px 8px;
        text-align: center;
        font-size: 13px;
        color: #000;
    }

    .report-table td:first-child, 
    .report-table th:first-child { 
        text-align: center; 
        background: #fcfcfc;
        font-weight: bold;
        font-size: 14px;
        width: 50px;
    }

    .report-table td:nth-child(2) {
        text-align: left;
        padding-left: 15px;
        font-weight: 700;
        font-size: 14px;
    }

    .report-table tbody tr.total-row td {
        background: #e0e0e0;
        font-weight: 900;
        font-size: 14px;
        border-top: 3px solid #000;
        border-bottom: 3px solid #000;
    }

    @media print {
        body * { visibility: hidden; }
        .table-wrapper, .table-wrapper * { visibility: visible; }
        .table-wrapper { 
            position: absolute; 
            left: 0; 
            top: 0; 
            width: 100%; 
            border: none; 
            padding: 0;
            margin: 0;
            overflow: visible;
        }
        .page-controls, .sidebar, .content-header, .btn-danger, .btn-edit { display: none !important; }
        
        .report-table { min-width: 100%; font-size: 12px; }
        .report-table thead tr.main-title th { font-size: 16px; }
        .report-table thead tr.group-title th { font-size: 13px; }
        .report-table thead tr.sub-title th { font-size: 11px; }
        .report-table tbody td { font-size: 11px; padding: 8px 4px; }
        
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
    }
</style>

<div class="page-controls">
    <div>
        <h1 style="margin: 0; font-size: 24px;">Tumanlar jadvali</h1>
        <p style="margin: 5px 0 0; color: #666; font-size: 14px;">Rasmiy hisobot jadvali</p>
    </div>
    <div style="display: flex; gap: 10px;">
        <input type="date" value="<?php echo $sana; ?>" max="<?php echo date('Y-m-d'); ?>" 
               onchange="location.href='?sana='+this.value" style="padding: 10px; border: 1px solid #ccc; border-radius: 5px; font-size: 15px;">
        <button class="btn btn-primary" onclick="window.print()">🖨️ Chop etish</button>
        <a href="export_excel.php?sana=<?php echo $sana; ?>" class="btn btn-success">📥 Excel</a>
    </div>
</div>

<?php if(isset($_GET['deleted'])): ?>
    <div class="success-message">✅ Hisobot muvaffaqiyatli o'chirildi! Endi tuman xodimi qayta yuborishi mumkin.</div>
<?php elseif(isset($error)): ?>
    <div class="xato-quti"><?php echo $error; ?></div>
<?php endif; ?>

<div class="table-wrapper">
    <table class="report-table">
        <thead>
            <tr class="main-title">
                <th colspan="34">
                    <h3>Yashirin iqtisodiyotni qisqartirish bo'yicha ishchi guruhlar tomonidan amalga oshirilgan ishlar bo'yicha</h3> <h1>MA'LUMOT</h1> <i><?php echo date('d-m-Y', strtotime($sana)); ?> (mlrd so'm)</i>
                </th>
            </tr>
            <tr class="group-title">
                <th rowspan="2">T/r</th>
                <th rowspan="2">Hudud nomi</th>
                <th colspan="8">Soliq tushumi</th>
                <th colspan="4">Qo'shimcha manba</th>
                <th colspan="3">Savdo tushumi</th>
                <th colspan="7">Bozorlar</th>
                <th colspan="5">Bandlik</th>
                <th rowspan="2">Amallar</th>
            </tr>
            <tr class="sub-title">
                <!-- Soliq tushumi (8 ta ustun) -->
                <th>Oylik prognoz</th>
                <th>Jami soliq tushumi</th>
                <th>shundan kunlik tushum</th>
                <th>Oy oxiriga qoldiq</th>
                <th>Jami boqimanda qarzdorlik</th>
                <th>Jami undirilgan soliq qarzi</th>
                <th>shundan DSB orqali</th>
                <th>shundan MIB orqali</th>
                
                <!-- Qo'shimcha manba (4 ta ustun) -->
                <th>Qo'shimcha manba bo'yicha oylik reja</th>
                <th>Jami soliq tushumi</th>
                <th>shundan kunlik tushum</th>
                <th>Oy oxiriga qoldiq</th>
                
                <!-- Savdo tushumi (3 ta ustun) -->
                <th>Jami savdo tushumi</th>
                <th>shundan kunlik tushum</th>
                <th>O'tgan kunga nisbatan farq</th>
                
                <!-- Bozorlar (7 ta ustun) -->
                <th>Mavjud bozorlar</th>
                <th>Bozor raqamlashtirilgani</th>
                <th>Vaziyatlar markaziga integratsiya qilingani</th>
                <th>Soliq organiga integratsiya qilingani</th>
                <th>Bozordagi jami tushum</th>
                <th>shundan kunlik tushum</th>
                <th>O'tgan kunga nisbatan farq</th>
                
                <!-- Bandlik (5 ta ustun) -->
                <th>Jami bandlik</th>
                <th>Kunlik bandlik</th>
                <th>O'tgan kunga nisbatan farq</th>
                <th>Jami norasmiy band aholi</th>
                <th>Jami yangilangan yangi ish o'rni</th>
            </tr>
        </thead>
        <tbody>
            <?php $i = 1; foreach ($tumanlar as $t): 
                $h = $hisobotlar[$t['id']] ?? null;
                
                // QOLDIQNI AVTOMATIK HISOBLASH
                if ($h) {
                    $qoldiq = ($h['oylik_prognoz_admin'] ?? 0) - ($h['soliq_jami'] ?? 0);
                } else {
                    $qoldiq = 0;
                }
            ?>
            <tr>
                <td><?php echo $i++; ?></td>
                <td><?php echo htmlspecialchars($t['nom']); ?></td>
                
                <!-- Soliq tushumi (8 ta ustun) -->
                <td><?php echo number_format($h['oylik_prognoz_admin'] ?? 0, 2); ?></td>
                <td><?php echo number_format($h['soliq_jami'] ?? 0, 2); ?></td>
                <td><?php echo number_format($h['soliq_kunlik'] ?? 0, 2); ?></td>
                <td><?php echo number_format($qoldiq, 2); ?></td>
                <td><?php echo number_format($h['qarz_boqimanda'] ?? 0, 2); ?></td>
                <td><?php echo number_format($h['und_jami'] ?? 0, 2); ?></td>
                <td><?php echo number_format($h['und_dsb'] ?? 0, 2); ?></td>
                <td><?php echo number_format($h['und_mib'] ?? 0, 2); ?></td>
                
                <!-- Qo'shimcha manba (4 ta ustun) -->
                <td><?php echo number_format($h['qm_reja'] ?? 0, 2); ?></td>
                <td><?php echo number_format($h['qm_jami'] ?? 0, 2); ?></td>
                <td><?php echo number_format($h['qm_kunlik'] ?? 0, 2); ?></td>
                <td><?php echo number_format($h['qm_qoldiq'] ?? 0, 2); ?></td>
                
                <!-- Savdo tushumi (3 ta ustun) -->
                <td><?php echo number_format($h['savdo_jami'] ?? 0, 2); ?></td>
                <td><?php echo number_format($h['savdo_kunlik'] ?? 0, 2); ?></td>
                <td><?php echo number_format($h['savdo_farq'] ?? 0, 2); ?></td>
                
                <!-- Bozorlar (7 ta ustun) -->
                <td><?php echo number_format($h['bozor_soni'] ?? 0); ?></td>
                <td><?php echo number_format($h['bozor_raqam'] ?? 0); ?></td>
                <td><?php echo number_format($h['bozor_vm'] ?? 0); ?></td>
                <td><?php echo number_format($h['bozor_soliq'] ?? 0); ?></td>
                <td><?php echo number_format($h['bt_jami'] ?? 0); ?></td>
                <td><?php echo number_format($h['bt_kunlik'] ?? 0); ?></td>
                <td><?php echo number_format($h['bt_farq'] ?? 0); ?></td>
                
                <!-- Bandlik (5 ta ustun) - TO'G'RILANGAN -->
                <td><?php echo number_format($h['bandlik_jami'] ?? 0); ?></td>
                <td><?php echo number_format($h['bandlik_kunlik'] ?? 0); ?></td>
                <td><?php echo number_format($h['bandlik_farq'] ?? 0); ?></td>
                <td><?php echo number_format($h['norasmiy_band_jami'] ?? 0); ?></td>
                <td><?php echo number_format($h['yangi_ish_orni_jami'] ?? 0); ?></td>
                
                <!-- Amallar ustuni -->
                <td>
                    <?php if ($h): ?>
                        <a href="kiritish.php?sana=<?php echo $sana; ?>&tuman_id=<?php echo $t['id']; ?>" 
                           class="btn-edit">✏️ Tahrirlash</a>
                        <br><br>
                        <a href="?sana=<?php echo $sana; ?>&delete=<?php echo $t['id']; ?>" 
                           class="btn-danger" 
                           onclick="return confirm('Rostdan ham <?php echo $t['nom']; ?> tumanining <?php echo $sana; ?> sanadagi hisobotini o\'chirmoqchimisiz?')">
                           🗑️ O'chirish
                        </a>
                    <?php else: ?>
                        <span style="color: #999; font-size: 12px;">—</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>

            <!-- Jami qator - TO'G'RILANGAN -->
            <tr class="total-row">
                <td colspan="2">JAMI</td>
                <td><?php echo number_format($jami['oylik_prognoz_admin'], 2); ?></td>
                <td><?php echo number_format($jami['soliq_jami'], 2); ?></td>
                <td><?php echo number_format($jami['soliq_kunlik'], 2); ?></td>
                <td><?php echo number_format($jami['oylik_prognoz_admin'] - $jami['soliq_jami'], 2); ?></td>
                <td><?php echo number_format($jami['qarz_boqimanda'], 2); ?></td>
                <td><?php echo number_format($jami['und_jami'], 2); ?></td>
                <td><?php echo number_format($jami['und_dsb'], 2); ?></td>
                <td><?php echo number_format($jami['und_mib'], 2); ?></td>
                
                <td><?php echo number_format($jami['qm_reja'], 2); ?></td>
                <td><?php echo number_format($jami['qm_jami'], 2); ?></td>
                <td><?php echo number_format($jami['qm_kunlik'], 2); ?></td>
                <td><?php echo number_format($jami['qm_qoldiq'], 2); ?></td>
                
                <td><?php echo number_format($jami['savdo_jami'], 2); ?></td>
                <td><?php echo number_format($jami['savdo_kunlik'], 2); ?></td>
                <td><?php echo number_format($jami['savdo_farq'], 2); ?></td>
                
                <td><?php echo number_format($jami['bozor_soni']); ?></td>
                <td><?php echo number_format($jami['bozor_raqam']); ?></td>
                <td><?php echo number_format($jami['bozor_vm']); ?></td>
                <td><?php echo number_format($jami['bozor_soliq']); ?></td>
                <td><?php echo number_format($jami['bt_jami']); ?></td>
                <td><?php echo number_format($jami['bt_kunlik']); ?></td>
                <td><?php echo number_format($jami['bt_farq']); ?></td>
                
                <!-- Bandlik JAMI - TO'G'RILANGAN -->
                <td><?php echo number_format($jami['bandlik_jami']); ?></td>
                <td><?php echo number_format($jami['bandlik_kunlik']); ?></td>
                <td><?php echo number_format($jami['bandlik_farq']); ?></td>
                <td><?php echo number_format($jami['norasmiy_band_jami']); ?></td>
                <td><?php echo number_format($jami['yangi_ish_orni_jami']); ?></td>
                
                <td></td>
            </tr>
        </tbody>
    </table>
</div>

<?php include 'includes/footer.php'; ?>