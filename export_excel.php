<?php
require_once 'includes/auth.php';
check_admin();

$sana = $_GET['sana'] ?? date('Y-m-d');

// OY BOSHINI ANIQLASH
$oy_boshi = date('Y-m-01', strtotime($sana));

// Tumanlar ro'yxatini olish
$tumanlar = [];
$res = $conn->query("SELECT * FROM tumanlar ORDER BY id ASC");
while ($row = $res->fetch_assoc()) $tumanlar[] = $row;

// Oy boshidan shu sanagacha bo'lgan barcha hisobotlarni yig'indisini olish
$hisobotlar = [];
$res = $conn->query("SELECT * FROM hisobotlar WHERE sana BETWEEN '$oy_boshi' AND '$sana'");
while ($row = $res->fetch_assoc()) {
    $tuman_id = $row['tuman_id'];
    
    // Har bir tuman uchun yig'indilarni jamlab boramiz
    if (!isset($hisobotlar[$tuman_id])) {
        $hisobotlar[$tuman_id] = array_fill_keys([
            'prognoz', 'soliq_jami', 'soliq_kunlik', 'soliq_qoldiq',
            'qm_reja', 'qm_jami', 'qm_kunlik', 'qm_qoldiq',
            'savdo_jami', 'savdo_kunlik', 'savdo_farq',
            'bozor_soni', 'bozor_raqam', 'bozor_vm', 'bozor_soliq',
            'bt_jami', 'bt_kunlik', 'bt_farq',
            'norasmiy', 'ish_orni',
            'qarz_boqimanda', 'und_jami', 'und_dsb', 'und_mib',
            'und_kunlik', 'und_kun_dsb', 'und_kun_mib'
        ], 0);
    }
    
    // Yig'indilarni qo'shamiz
    foreach ($hisobotlar[$tuman_id] as $key => $val) {
        $hisobotlar[$tuman_id][$key] += $row[$key] ?? 0;
    }
    
    // Prognozni ADMIN kiritganidan olamiz (agar mavjud bo'lsa)
    if (isset($row['oylik_prognoz_admin']) && $row['oylik_prognoz_admin'] > 0) {
        $hisobotlar[$tuman_id]['prognoz'] = $row['oylik_prognoz_admin'];
    }
}

// JAMI YIG'INDI
$jami = array_fill_keys([
    'prognoz','soliq_jami','soliq_kunlik','soliq_qoldiq',
    'qm_reja','qm_jami','qm_kunlik','qm_qoldiq',
    'savdo_jami','savdo_kunlik','savdo_farq',
    'bozor_soni','bozor_raqam','bozor_vm','bozor_soliq',
    'bt_jami','bt_kunlik','bt_farq',
    'norasmiy','ish_orni',
    'qarz_boqimanda','und_jami','und_dsb','und_mib',
    'und_kunlik','und_kun_dsb','und_kun_mib'
], 0);

foreach ($hisobotlar as $h) {
    foreach ($jami as $key => $val) {
        $jami[$key] += $h[$key] ?? 0;
    }
}

// Excel formatida yuklab olish
header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="Yashirin_iqtisodiyot_' . $sana . '.xls"');
header('Pragma: no-cache');
header('Expires: 0');

echo "\xEF\xBB\xBF"; // UTF-8 BOM (Excel to'g'ri ochishi uchun)
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    table {
        border-collapse: collapse;
        width: 100%;
        font-family: 'Cambria', 'Times New Roman', serif;
        font-size: 14px;
    }
    th, td {
        border: 1px solid #000;
        padding: 6px;
        text-align: center;
        vertical-align: middle;
        white-space: nowrap;
        font-family: 'Cambria', 'Times New Roman', serif;
        font-size: 14px;
    }
    .main-title {
        background: #dce6f1;
        font-size: 16px;
        font-weight: bold;
        text-align: center;
    }
    .group-title {
        background: #dce6f1;
        font-weight: bold;
        text-align: center;
    }
    .sub-title {
        background: #f2f2f2;
        font-weight: 600;
        text-align: center;
    }
    .col-num {
        background: #f2f2f2;
        font-weight: bold;
        font-size: 12px;
    }
    .total-row {
        background: #dce6f1;
        font-weight: 900;
    }
    .first-col {
        text-align: left;
        padding-left: 10px;
        font-weight: bold;
        min-width: 150px;
    }
</style>
</head>
<body>
    <table>
        <thead>
            <!-- 1-QATOR: ASOSIY SARLAVHA -->
            <tr class="main-title">
                <th colspan="28" style="font-size: 18px;">
                    Yashirin iqtisodiyotni qisqartirish bo'yicha ishchi guruhlar tomonidan amalga oshirilgan ishlar bo'yicha MA'LUMOT — Oy boshidan (<?php echo date('d.m.Y', strtotime($oy_boshi)); ?> - <?php echo date('d-m-Y', strtotime($sana)); ?>) (mln so'm)
                </th>
            </tr>
            
            <!-- 2-QATOR: BO'LIMLAR (GURUHLAR) -->
            <tr class="group-title">
                <th rowspan="2">T/r</th>
                <th rowspan="2">Hudud nomi</th>
                <th colspan="8">Soliq tushumi</th>
                <th colspan="4">Qo'shimcha manba</th>
                <th colspan="3">Savdo tushumi</th>
                <th colspan="7">Bozorlar</th>
                <th colspan="5">Bandlik</th>
            </tr>
            
            <!-- 3-QATOR: KICHIK USTUNLAR -->
            <tr class="sub-title">
                <!-- Soliq (8 ta ustun) -->
                <th>Oylik prognoz</th>
                <th>Jami soliq tushumi</th>
                <th>shundan kunlik tushum</th>
                <th>Oy oxiriga qoldiq</th>
                <th>Jami boqimanda qarzdorlik</th>
                <th>Jami undirilgan soliq qarzi</th>
                <th>shundan DSB orqali</th>
                <th>shundan MIB orqali</th>
                
                <!-- Qo'shimcha (4 ta ustun) -->
                <th>Qo'shimcha manba bo'yicha oylik reja</th>
                <th>Jami soliq tushumi</th>
                <th>shundan kunlik tushum</th>
                <th>Oy oxiriga qoldiq</th>
                
                <!-- Savdo (3 ta ustun) -->
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
            
            <!-- 4-QATOR: USTUN RAQAMLARI (1 dan 28 gacha) -->
            <tr class="col-num">
                <th>1</th>
                <th>2</th>
                <th>3</th>
                <th>4</th>
                <th>5</th>
                <th>6</th>
                <th>7</th>
                <th>8</th>
                <th>9</th>
                <th>10</th>
                <th>11</th>
                <th>12</th>
                <th>13</th>
                <th>14</th>
                <th>15</th>
                <th>16</th>
                <th>17</th>
                <th>18</th>
                <th>19</th>
                <th>20</th>
                <th>21</th>
                <th>22</th>
                <th>23</th>
                <th>24</th>
                <th>25</th>
                <th>26</th>
                <th>27</th>
                <th>28</th>
            </tr>
        </thead>
        <tbody>
            <?php $i = 1; foreach ($tumanlar as $t): 
                $h = $hisobotlar[$t['id']] ?? null;
            ?>
            <tr>
                <td><?php echo $i++; ?></td>
                <td class="first-col"><?php echo htmlspecialchars($t['nom']); ?></td>
                
                <!-- Soliq (8 ta ustun) -->
                <td><?php echo number_format($h['prognoz'] ?? 0, 2); ?></td>
                <td><?php echo number_format($h['soliq_jami'] ?? 0, 2); ?></td>
                <td><?php echo number_format($h['soliq_kunlik'] ?? 0, 2); ?></td>
                <td><?php echo number_format($h['soliq_qoldiq'] ?? 0, 2); ?></td>
                <td><?php echo number_format($h['qarz_boqimanda'] ?? 0, 2); ?></td>
                <td><?php echo number_format($h['und_jami'] ?? 0, 2); ?></td>
                <td><?php echo number_format($h['und_dsb'] ?? 0, 2); ?></td>
                <td><?php echo number_format($h['und_mib'] ?? 0, 2); ?></td>
                
                <!-- Qo'shimcha (4 ta ustun) -->
                <td><?php echo number_format($h['qm_reja'] ?? 0, 2); ?></td>
                <td><?php echo number_format($h['qm_jami'] ?? 0, 2); ?></td>
                <td><?php echo number_format($h['qm_kunlik'] ?? 0, 2); ?></td>
                <td><?php echo number_format($h['qm_qoldiq'] ?? 0, 2); ?></td>
                
                <!-- Savdo (3 ta ustun) -->
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
                
                <!-- Bandlik (5 ta ustun) -->
                <td><?php echo number_format($h['bt_jami'] ?? 0); ?></td>
                <td><?php echo number_format($h['bt_kunlik'] ?? 0); ?></td>
                <td><?php echo number_format($h['bt_farq'] ?? 0); ?></td>
                <td><?php echo number_format($h['norasmiy'] ?? 0); ?></td>
                <td><?php echo number_format($h['ish_orni'] ?? 0); ?></td>
            </tr>
            <?php endforeach; ?>
            
            <!-- JAMI QATOR -->
            <tr class="total-row">
                <td colspan="2">JAMI</td>
                <?php foreach ($jami as $val): ?>
                    <td><?php echo number_format($val, 2); ?></td>
                <?php endforeach; ?>
            </tr>
        </tbody>
    </table>
</body>
</html>
<?php exit; ?>