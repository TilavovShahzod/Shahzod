<?php
require_once 'includes/auth.php';
check_admin(); // Faqat viloyat admini kira oladi

$sana = $_GET['sana'] ?? date('Y-m-d');
$tuman_kalit = $_GET['tuman'] ?? '';

// OY BOSHINI ANIQLASH (Jami yig'indi uchun)
$oy_boshi = date('Y-m-01', strtotime($sana));

// Tumanlar ro'yxatini olish
$tumanlar = [];
$res = $conn->query("SELECT * FROM tumanlar ORDER BY id ASC");
while ($row = $res->fetch_assoc()) {
    $tumanlar[$row['kalit']] = $row;
}

// Tanlangan tuman bo'yicha OY BOSHIDAN JAMI yig'indini olish
$tanlangan = null;
if ($tuman_kalit && isset($tumanlar[$tuman_kalit])) {
    $tuman_id = $tumanlar[$tuman_kalit]['id'];
    $stmt = $conn->prepare("SELECT 
        SUM(oylik_prognoz_admin) as prognoz, SUM(soliq_jami) as soliq_jami, SUM(soliq_kunlik) as soliq_kunlik, 
        SUM(qm_reja) as qm_reja, SUM(qm_jami) as qm_jami, SUM(qm_kunlik) as qm_kunlik, 
        SUM(savdo_jami) as savdo_jami, SUM(savdo_kunlik) as savdo_kunlik, 
        SUM(bozor_soni) as bozor_soni, SUM(bozor_raqam) as bozor_raqam, SUM(bozor_vm) as bozor_vm, SUM(bozor_soliq) as bozor_soliq, 
        SUM(bt_jami) as bt_jami, SUM(bt_kunlik) as bt_kunlik, 
        SUM(norasmiy) as norasmiy, SUM(ish_orni) as ish_orni, 
        SUM(qarz_boqimanda) as qarz_boqimanda, SUM(und_jami) as und_jami, SUM(und_dsb) as und_dsb, SUM(und_mib) as und_mib, 
        SUM(und_kunlik) as und_kunlik, SUM(und_kun_dsb) as und_kun_dsb, SUM(und_kun_mib) as und_kun_mib,
        MAX(status) as status
        FROM hisobotlar 
        WHERE tuman_id = ? AND sana BETWEEN ? AND ?");
    $stmt->bind_param("iss", $tuman_id, $oy_boshi, $sana);
    $stmt->execute();
    $tanlangan = $stmt->get_result()->fetch_assoc();
}

// Jami statistika (OY BOSHIDAN)
$jami = [
    'oylik_prognoz_admin' => 0, 'soliq_jami' => 0, 'soliq_kunlik' => 0, 'soliq_qoldiq' => 0,
    'qm_reja' => 0, 'qm_jami' => 0, 'qm_kunlik' => 0, 'qm_qoldiq' => 0,
    'savdo_jami' => 0, 'savdo_kunlik' => 0, 'savdo_farq' => 0,
    'bozor_soni' => 0, 'bozor_raqam' => 0, 'bozor_vm' => 0, 'bozor_soliq' => 0,
    'bt_jami' => 0, 'bt_kunlik' => 0, 'bt_farq' => 0,
    'norasmiy' => 0, 'ish_orni' => 0,
    'qarz_boqimanda' => 0, 'und_jami' => 0, 'und_dsb' => 0, 'und_mib' => 0,
    'und_kunlik' => 0, 'und_kun_dsb' => 0, 'und_kun_mib' => 0
];

$hisobot_bor_tumanlar = 0;
$topshirilgan_tumanlar = 0;

// Har bir tuman uchun oy boshidan yig'indini olish
foreach ($tumanlar as $kalit => $t) {
    $tuman_id = $t['id'];
    $stmt = $conn->prepare("SELECT 
        SUM(oylik_prognoz_admin) as prognoz, SUM(soliq_jami) as soliq_jami, SUM(soliq_kunlik) as soliq_kunlik, 
        SUM(qm_reja) as qm_reja, SUM(qm_jami) as qm_jami, SUM(qm_kunlik) as qm_kunlik, 
        SUM(savdo_jami) as savdo_jami, SUM(savdo_kunlik) as savdo_kunlik, 
        SUM(bozor_soni) as bozor_soni, SUM(bozor_raqam) as bozor_raqam, SUM(bozor_vm) as bozor_vm, SUM(bozor_soliq) as bozor_soliq, 
        SUM(bt_jami) as bt_jami, SUM(bt_kunlik) as bt_kunlik, 
        SUM(norasmiy) as norasmiy, SUM(ish_orni) as ish_orni, 
        SUM(qarz_boqimanda) as qarz_boqimanda, SUM(und_jami) as und_jami, SUM(und_dsb) as und_dsb, SUM(und_mib) as und_mib, 
        SUM(und_kunlik) as und_kunlik, SUM(und_kun_dsb) as und_kun_dsb, SUM(und_kun_mib) as und_kun_mib,
        MAX(status) as status
        FROM hisobotlar 
        WHERE tuman_id = ? AND sana BETWEEN ? AND ?");
    $stmt->bind_param("iss", $tuman_id, $oy_boshi, $sana);
    $stmt->execute();
    $h = $stmt->get_result()->fetch_assoc();
    
    if ($h && ($h['soliq_jami'] > 0 || $h['bozor_soni'] > 0 || $h['und_jami'] > 0)) {
        $hisobot_bor_tumanlar++;
        if ($h['status'] == 'yuborilgan') $topshirilgan_tumanlar++;
        
        $jami['oylik_prognoz_admin'] += $h['prognoz'] ?? 0;
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
        $jami['norasmiy'] += $h['norasmiy'] ?? 0;
        $jami['ish_orni'] += $h['ish_orni'] ?? 0;
        $jami['qarz_boqimanda'] += $h['qarz_boqimanda'] ?? 0;
        $jami['und_jami'] += $h['und_jami'] ?? 0;
        $jami['und_dsb'] += $h['und_dsb'] ?? 0;
        $jami['und_mib'] += $h['und_mib'] ?? 0;
        $jami['und_kunlik'] += $h['und_kunlik'] ?? 0;
        $jami['und_kun_dsb'] += $h['und_kun_dsb'] ?? 0;
        $jami['und_kun_mib'] += $h['und_kun_mib'] ?? 0;
    }
}

include 'includes/header.php';
?>

<style>
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
        margin-bottom: 25px;
        padding: 20px;
        background: #fff;
        border-radius: 10px;
        border: 1px solid #e5e7eb;
    }

    .page-header h2 { font-size: 24px; color: #17233B; margin: 0; }
    .page-header p { font-size: 13px; color: #6b7280; margin: 5px 0 0; }

    .btn {
        padding: 8px 16px;
        border: 1px solid #D8E0EC;
        border-radius: 6px;
        background: #fff;
        font-size: 14px;
        cursor: pointer;
        transition: 0.2s;
        text-decoration: none;
        display: inline-block;
        color: #17233B;
    }
    .btn:hover { background: #f3f4f6; }
    .btn-primary { background: #1c5cab; color: #fff; border-color: #1c5cab; }
    .btn-primary:hover { background: #12395C; }
    .btn-success { background: #16a34a; color: #fff; border-color: #16a34a; }
    .btn-success:hover { background: #15803d; }

    .tuman-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 12px;
        margin-bottom: 25px;
    }

    .tuman-card {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        padding: 14px 16px;
        cursor: pointer;
        transition: 0.2s;
        text-decoration: none;
        color: inherit;
        display: block;
        position: relative;
    }

    .tuman-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.08); border-color: #1c5cab; }
    .tuman-card.active { border: 2px solid #1c5cab; background: #eff6ff; }
    
    .tuman-card .t-nom { font-weight: 700; font-size: 15px; margin-bottom: 6px; }
    .tuman-card .t-status { font-size: 12px; color: #6b7280; margin-bottom: 4px; }
    .tuman-card .t-soliq { font-size: 16px; font-weight: 700; color: #1c5cab; }
    .tuman-card .t-soliq small { font-size: 11px; color: #6b7280; font-weight: 400; }
    
    .indikator {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        display: inline-block;
        margin-right: 5px;
    }
    .indikator.yashil { background: #16a34a; }
    .indikator.sariq { background: #d97706; }
    .indikator.qizil { background: #dc2626; }
    .indikator.kulrang { background: #9ca3af; }
    .indikator.ko'k { background: #1c5cab; }

    .stats-panel {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        padding: 20px 25px;
        margin-bottom: 20px;
    }

    .stats-panel h3 {
        font-size: 17px;
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 1px solid #e5e7eb;
        color: #17233B;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 12px;
        margin-bottom: 15px;
    }

    .stats-grid .item {
        background: #f8fafc;
        border-radius: 8px;
        padding: 12px;
        text-align: center;
    }
    .stats-grid .item b { font-size: 20px; color: #17233B; display: block; }
    .stats-grid .item span { font-size: 12px; color: #6b7280; }

    .table-wrap { overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; font-size: 13px; min-width: 700px; }
    th, td { padding: 10px 12px; border: 1px solid #e5e7eb; text-align: center; }
    th { background: #f3f4f6; color: #17233B; font-weight: 600; }
    tr:hover { background: #f9fafb; }
    
    .badge {
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        display: inline-block;
    }
    .badge-success { background: #dcfce7; color: #166534; }
    .badge-warning { background: #fef3c7; color: #92400e; }
    .badge-danger { background: #fee2e2; color: #991b1b; }
    .badge-secondary { background: #f3f4f6; color: #6b7280; }

    .text-success { color: #16a34a; }
    .text-danger { color: #dc2626; }
    .text-warning { color: #d97706; }
    .fw-bold { font-weight: 700; }

    .divider { height: 1px; background: #e5e7eb; margin: 18px 0; }

    .sana-input {
        padding: 8px 12px;
        border: 1px solid #D8E0EC;
        border-radius: 6px;
        font-size: 14px;
        background: #fff;
    }

    @media (max-width: 768px) {
        .tuman-grid { grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); }
        .stats-grid { grid-template-columns: repeat(2, 1fr); }
    }
</style>

<div class="content">
    <!-- HEADER -->
    <div class="page-header">
        <div>
            <h2>📊 Tumanlar hisoboti — Oy boshidan jami</h2>
            <p>
                <?php echo date('d.m.Y', strtotime($oy_boshi)); ?> — <?php echo date('d-m-Y', strtotime($sana)); ?> | 
                <?php echo count($tumanlar); ?> ta tuman, 
                <?php echo $hisobot_bor_tumanlar; ?> ta hisobot, 
                <?php echo $topshirilgan_tumanlar; ?> ta topshirilgan
            </p>
        </div>
        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
            <input type="date" class="sana-input" value="<?php echo $sana; ?>" 
                   max="<?php echo date('Y-m-d'); ?>" 
                   onchange="location.href='?sana='+this.value<?php echo $tuman_kalit ? '+\'&tuman='.$tuman_kalit.'\'' : ''; ?>">
            <button class="btn btn-primary" onclick="location.href='?sana=<?php echo date('Y-m-d'); ?>'">📅 Bugun</button>
            <a href="export_excel.php?sana=<?php echo $sana; ?>" class="btn btn-success">📥 Excel</a>
        </div>
    </div>

    <!-- TUMAN KARTALARI -->
    <div class="tuman-grid">
        <?php foreach ($tumanlar as $kalit => $t): 
            $tuman_id = $t['id'];
            $stmt = $conn->prepare("SELECT 
                SUM(oylik_prognoz_admin) as prognoz, SUM(soliq_jami) as soliq_jami, SUM(soliq_kunlik) as soliq_kunlik, 
                SUM(bozor_soni) as bozor_soni, SUM(bozor_raqam) as bozor_raqam, SUM(bozor_vm) as bozor_vm, SUM(bozor_soliq) as bozor_soliq, 
                SUM(und_jami) as und_jami,
                MAX(status) as status
                FROM hisobotlar 
                WHERE tuman_id = ? AND sana BETWEEN ? AND ?");
            $stmt->bind_param("iss", $tuman_id, $oy_boshi, $sana);
            $stmt->execute();
            $h = $stmt->get_result()->fetch_assoc();

            $holat = 'Kiritilmagan';
            $rang = 'kulrang';
            $badge = 'badge-secondary';
            if ($h && ($h['soliq_jami'] > 0 || $h['bozor_soni'] > 0)) {
                if ($h['status'] == 'yuborilgan') { 
                    $holat = 'Topshirilgan'; 
                    $rang = 'yashil'; 
                    $badge = 'badge-success';
                } elseif ($h['status'] == 'qoralama') { 
                    $holat = 'Qoralama'; 
                    $rang = 'sariq'; 
                    $badge = 'badge-warning';
                } else { 
                    $holat = 'Kiritilgan'; 
                    $rang = 'ko\'k'; 
                    $badge = 'badge-secondary';
                }
            }
            $foiz = ($h && $h['prognoz'] > 0) ? ($h['soliq_jami'] / $h['prognoz']) * 100 : 0;
        ?>
        <a href="?sana=<?php echo $sana; ?>&tuman=<?php echo $kalit; ?>" 
           class="tuman-card <?php echo $tuman_kalit == $kalit ? 'active' : ''; ?>">
            <div class="t-nom"><?php echo htmlspecialchars($t['nom']); ?></div>
            <div class="t-status">
                <span class="indikator <?php echo $rang; ?>"></span>
                <span class="badge <?php echo $badge; ?>"><?php echo $holat; ?></span>
            </div>
            <div class="t-soliq">
                <?php echo number_format($h['soliq_jami'] ?? 0, 2); ?> 
                <small>mln / <?php echo number_format($foiz, 0); ?>%</small>
            </div>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- TANLANGAN TUMAN -->
    <?php if ($tuman_kalit && isset($tumanlar[$tuman_kalit])): 
        $t = $tumanlar[$tuman_kalit];
        $h = $tanlangan;
    ?>
        <div class="stats-panel" style="border-color: #1c5cab; border-width: 2px;">
            <h3>🏛️ <?php echo htmlspecialchars($t['nom']); ?> — Oy boshidan jami (<?php echo date('d.m.Y', strtotime($oy_boshi)); ?> - <?php echo date('d.m.Y', strtotime($sana)); ?>)</h3>

            <div class="stats-grid">
                <div class="item">
                    <b><?php echo number_format($h['prognoz'] ?? 0, 2); ?></b>
                    <span>📌 Oylik prognoz</span>
                </div>
                <div class="item">
                    <b class="text-success"><?php echo number_format($h['soliq_jami'] ?? 0, 2); ?></b>
                    <span>💰 Jami tushum</span>
                </div>
                <div class="item">
                    <b><?php echo number_format($h['soliq_kunlik'] ?? 0, 2); ?></b>
                    <span>📅 Kunlik tushum</span>
                </div>
                <div class="item">
                    <b class="<?php echo ($h['soliq_qoldiq'] ?? 0) <= 0 ? 'text-success' : 'text-danger'; ?>">
                        <?php echo number_format(abs($h['soliq_qoldiq'] ?? 0), 2); ?>
                    </b>
                    <span>📉 Qoldiq (<?php echo ($h['soliq_qoldiq'] ?? 0) <= 0 ? 'ortiqcha' : 'kam'; ?>)</span>
                </div>
                <div class="item">
                    <b><?php echo number_format($h['und_jami'] ?? 0, 2); ?></b>
                    <span>💳 Undirilgan qarz</span>
                </div>
                <div class="item">
                    <b><?php echo number_format($h['bozor_raqam'] ?? 0); ?>/<?php echo number_format($h['bozor_soni'] ?? 0); ?></b>
                    <span>📱 Bozorlar (raqamli/jami)</span>
                </div>
            </div>

            <?php if ($h): ?>
            <div class="divider"></div>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; font-size: 13px;">
                <div><b>Qo'shimcha manba:</b> <?php echo number_format($h['qm_jami'] ?? 0, 2); ?> mln</div>
                <div><b>Savdo tushumi:</b> <?php echo number_format($h['savdo_jami'] ?? 0, 2); ?> mln</div>
                <div><b>Bandlik:</b> <?php echo number_format($h['bt_jami'] ?? 0); ?> ta</div>
                <div><b>Norasmiy band:</b> <?php echo number_format($h['norasmiy'] ?? 0); ?> kishi</div>
                <div><b>Yangi ish o'rinlari:</b> <?php echo number_format($h['ish_orni'] ?? 0); ?> ta</div>
                <div><b>Holat:</b> 
                    <?php 
                    if ($h['status'] == 'yuborilgan') echo '<span class="badge badge-success">Topshirilgan</span>';
                    elseif ($h['status'] == 'qoralama') echo '<span class="badge badge-warning">Qoralama</span>';
                    else echo '<span class="badge badge-secondary">Kiritilgan</span>';
                    ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="stats-panel" style="text-align: center; padding: 50px; color: #9ca3af;">
            <h3 style="border: none;">👆 Tuman tanlanmagan</h3>
            <p>Yuqoridagi tuman kartalaridan birini bosib, batafsil tahlilni ko'ring.</p>
        </div>
    <?php endif; ?>

    <!-- VILOYAT JAMI -->
    <div class="stats-panel">
        <h3>📊 Viloyat bo'yicha jami natijalar — Oy boshidan (<?php echo date('d.m.Y', strtotime($oy_boshi)); ?> - <?php echo date('d.m.Y', strtotime($sana)); ?>)</h3>

        <div class="stats-grid">
            <div class="item"><b class="text-success"><?php echo number_format($jami['soliq_jami'], 2); ?></b><span>💰 Jami tushum</span></div>
            <div class="item"><b><?php echo number_format($jami['soliq_kunlik'], 2); ?></b><span>📅 Kunlik tushum</span></div>
            <div class="item"><b><?php echo number_format($jami['oylik_prognoz_admin'], 2); ?></b><span>📌 Jami prognoz</span></div>
            <div class="item"><b class="<?php echo $jami['soliq_qoldiq'] <= 0 ? 'text-success' : 'text-danger'; ?>"><?php echo number_format(abs($jami['soliq_qoldiq']), 2); ?></b><span>📉 Qoldiq</span></div>
            <div class="item"><b><?php echo number_format($jami['bozor_soni']); ?></b><span>🏪 Jami bozorlar</span></div>
            <div class="item"><b><?php echo number_format($jami['bozor_raqam']); ?></b><span>📱 Raqamlashtirilgan</span></div>
            <div class="item"><b><?php echo number_format($jami['und_jami'], 2); ?></b><span>💳 Undirilgan qarz</span></div>
            <div class="item"><b><?php echo number_format($jami['bt_jami']); ?></b><span>👷 Bandlik</span></div>
            <div class="item"><b><?php echo number_format($jami['norasmiy']); ?></b><span>📋 Norasmiy band</span></div>
            <div class="item"><b><?php echo number_format($jami['ish_orni']); ?></b><span>🆕 Yangi ish o'rinlari</span></div>
        </div>

        <div class="divider"></div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th style="text-align: left;">Tuman</th>
                        <th>Prognoz</th>
                        <th>Jami tushum</th>
                        <th>Kunlik</th>
                        <th>Qoldiq</th>
                        <th>Bozorlar</th>
                        <th>Raqamli</th>
                        <th>Qarz</th>
                        <th>Holat</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach ($tumanlar as $kalit => $t): 
                        $tuman_id = $t['id'];
                        $stmt = $conn->prepare("SELECT 
                            SUM(oylik_prognoz_admin) as prognoz, SUM(soliq_jami) as soliq_jami, SUM(soliq_kunlik) as soliq_kunlik, 
                            SUM(bozor_soni) as bozor_soni, SUM(bozor_raqam) as bozor_raqam, 
                            SUM(und_jami) as und_jami,
                            MAX(status) as status
                            FROM hisobotlar 
                            WHERE tuman_id = ? AND sana BETWEEN ? AND ?");
                        $stmt->bind_param("iss", $tuman_id, $oy_boshi, $sana);
                        $stmt->execute();
                        $h = $stmt->get_result()->fetch_assoc();
                        
                        if ($h && ($h['soliq_jami'] > 0 || $h['bozor_soni'] > 0)) {
                            $holat = $h['status'] == 'yuborilgan' ? '<span class="badge badge-success">Topshirilgan</span>' : ($h['status'] == 'qoralama' ? '<span class="badge badge-warning">Qoralama</span>' : '<span class="badge badge-secondary">Kiritilgan</span>');
                        } else {
                            $holat = '<span class="badge badge-danger">Kiritilmagan</span>';
                        }
                    ?>
                    <tr>
                        <td><?php echo $i++; ?></td>
                        <td style="text-align: left; font-weight: 600;"><?php echo htmlspecialchars($t['nom']); ?></td>
                        <td><?php echo number_format($h['prognoz'] ?? 0, 2); ?></td>
                        <td class="text-success fw-bold"><?php echo number_format($h['soliq_jami'] ?? 0, 2); ?></td>
                        <td><?php echo number_format($h['soliq_kunlik'] ?? 0, 2); ?></td>
                        <td class="<?php echo ($h['soliq_qoldiq'] ?? 0) <= 0 ? 'text-success' : 'text-danger'; ?>">
                            <?php echo number_format(abs($h['soliq_qoldiq'] ?? 0), 2); ?>
                        </td>
                        <td><?php echo number_format($h['bozor_soni'] ?? 0); ?></td>
                        <td><?php echo number_format($h['bozor_raqam'] ?? 0); ?></td>
                        <td><?php echo number_format($h['und_jami'] ?? 0, 2); ?></td>
                        <td><?php echo $holat; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background: #f3f4f6; font-weight: 700;">
                        <td colspan="2">JAMI</td>
                        <td><?php echo number_format($jami['oylik_prognoz_admin'], 2); ?></td>
                        <td class="text-success"><?php echo number_format($jami['soliq_jami'], 2); ?></td>
                        <td><?php echo number_format($jami['soliq_kunlik'], 2); ?></td>
                        <td class="<?php echo $jami['soliq_qoldiq'] <= 0 ? 'text-success' : 'text-danger'; ?>">
                            <?php echo number_format(abs($jami['soliq_qoldiq']), 2); ?>
                        </td>
                        <td><?php echo number_format($jami['bozor_soni']); ?></td>
                        <td><?php echo number_format($jami['bozor_raqam']); ?></td>
                        <td><?php echo number_format($jami['und_jami'], 2); ?></td>
                        <td><?php echo $hisobot_bor_tumanlar . '/' . count($tumanlar); ?> ta</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>