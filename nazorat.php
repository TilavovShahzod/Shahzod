<?php
require_once 'includes/auth.php';
check_admin();

$sana = $_GET['sana'] ?? date('Y-m-d');

// OYLIK REJA KIRITISH (ADMIN UCHUN) - VERGULNI NOKTAGA O'GIRISH
if (isset($_POST['save_qm_reja'])) {
    $tuman_id = (int)$_POST['tuman_id'];
    $qm_reja = str_replace(',', '.', $_POST['qm_reja']); // Vergulni nuqtaga aylantirish
    $qm_reja = (float)$qm_reja;
    
    // Mavjud yozuvni tekshirish
    $stmt = $conn->prepare("SELECT id FROM hisobotlar WHERE tuman_id = ? AND sana = ?");
    $stmt->bind_param("is", $tuman_id, $sana);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    
    if ($res) {
        // Yangilash
        $stmt = $conn->prepare("UPDATE hisobotlar SET qm_reja = ? WHERE tuman_id = ? AND sana = ?");
        $stmt->bind_param("dis", $qm_reja, $tuman_id, $sana);
    } else {
        // Yangi yozish
        $stmt = $conn->prepare("INSERT INTO hisobotlar (tuman_id, sana, qm_reja) VALUES (?, ?, ?)");
        $stmt->bind_param("isd", $tuman_id, $sana, $qm_reja);
    }
    
    if ($stmt->execute()) {
        header("Location: nazorat.php?sana=$sana&saved_qm=1");
        exit;
    }
}

// BUGUNGI KUN UCHUN HISOBOTNI O'CHIRISH
if (isset($_GET['delete_today'])) {
    $delete_tuman_id = (int)$_GET['delete_today'];
    
    $stmt = $conn->prepare("DELETE FROM hisobotlar WHERE tuman_id = ? AND sana = ?");
    $stmt->bind_param("is", $delete_tuman_id, $sana);
    
    if ($stmt->execute()) {
        $tuman_nomi = "Tuman ID: $delete_tuman_id";
        $res = $conn->query("SELECT * FROM tumanlar WHERE id = $delete_tuman_id");
        if ($row = $res->fetch_assoc()) $tuman_nomi = $row['nom'];
        
        jurnal('kunlik_hisobot_ochirish', "Sana: $sana, Tuman: $tuman_nomi");
        header("Location: nazorat.php?sana=$sana&deleted_today=1");
        exit;
    } else {
        $error = "O'chirishda xatolik: " . $conn->error;
    }
}

// Barcha tumanlar ro'yxatini olish
$tumanlar = [];
$res = $conn->query("SELECT * FROM tumanlar ORDER BY id ASC");
while ($row = $res->fetch_assoc()) $tumanlar[$row['kalit']] = $row;

// Tumanlar bo'yicha hisobot holatini olish
$holatlar = [];
$res = $conn->query("SELECT 
    t.kalit, h.status, h.prognoz, h.oylik_prognoz_admin,
    h.soliq_jami, h.soliq_kunlik, h.soliq_qoldiq,
    h.qm_reja, h.qm_jami, h.qm_kunlik, h.qm_qoldiq,
    h.savdo_jami, h.savdo_kunlik, h.savdo_farq,
    h.bozor_soni, h.bozor_raqam, h.bozor_vm, h.bozor_soliq,
    h.bt_jami, h.bt_kunlik, h.bt_farq,
    h.und_jami, h.und_dsb, h.und_mib
    FROM tumanlar t LEFT JOIN hisobotlar h ON t.id = h.tuman_id AND h.sana = '$sana'");
while ($row = $res->fetch_assoc()) {
    $holatlar[$row['kalit']] = $row;
}

// STATISTIKA UCHUN HISOBLASH
$topshirilgan_soni = 0;
$kiritilmagan_soni = 0;
foreach ($holatlar as $h) {
    if (isset($h['status']) && $h['status'] == 'yuborilgan') {
        $topshirilgan_soni++;
    } else {
        $kiritilmagan_soni++;
    }
}

// Nazorat qoidalari
$qoidalar = [
    ['nom' => 'Hisobot topshirish', 'tavsif' => 'Har kuni 18:00 gacha hisobot topshirilishi shart', 'turi' => 'majburiy', 'ikona' => '📋', 'foiz_chegara' => 100],
    ['nom' => 'Bozor raqamlashtirish', 'tavsif' => 'Mavjud bozorlarning kamida 70% raqamlashtirilgan bo\'lishi kerak', 'turi' => 'foiz', 'ikona' => '📱', 'foiz_chegara' => 70],
    ['nom' => 'Soliq prognozining bajarilishi', 'tavsif' => 'Prognozning kamida 80% bajarilishi kerak', 'turi' => 'foiz', 'ikona' => '💰', 'foiz_chegara' => 80],
    ['nom' => 'Undirilgan qarz', 'tavsif' => 'Jami qarzning kamida 50% undirilishi kerak', 'turi' => 'foiz', 'ikona' => '💳', 'foiz_chegara' => 50],
    ['nom' => 'VMga ulanish', 'tavsif' => 'Raqamlashtirilgan bozorlarning kamida 60% VMga ulangan bo\'lishi kerak', 'turi' => 'foiz', 'ikona' => '🖥️', 'foiz_chegara' => 60],
    ['nom' => 'Soliq organiga ulanish', 'tavsif' => 'Raqamlashtirilgan bozorlarning kamida 50% soliq organiga ulangan bo\'lishi kerak', 'turi' => 'foiz', 'ikona' => '🏛️', 'foiz_chegara' => 50],
    ['nom' => 'Qo\'shimcha manba bajarilishi', 'tavsif' => 'Qo\'shimcha manba rejasining kamida 80% bajarilishi kerak', 'turi' => 'foiz', 'ikona' => '📊', 'foiz_chegara' => 80],
];

// Har bir qoida bo'yicha nomuvofiqliklar
$nomuvofiqliklar = [];
foreach ($holatlar as $kalit => $h) {
    if (!isset($h['status']) || !$h['status']) $nomuvofiqliklar[$kalit]['hisobot'] = 'Kiritilmagan';
    elseif ($h['status'] == 'qoralama') $nomuvofiqliklar[$kalit]['hisobot'] = 'Qoralama';
    else $nomuvofiqliklar[$kalit]['hisobot'] = 'Topshirilgan';

    $nomuvofiqliklar[$kalit]['bozor_foiz'] = ($h['bozor_soni'] ?? 0) > 0 ? ($h['bozor_raqam'] ?? 0) / $h['bozor_soni'] * 100 : 0;
    
    $prognoz = $h['oylik_prognoz_admin'] ?? $h['prognoz'] ?? 0;
    $nomuvofiqliklar[$kalit]['soliq_foiz'] = $prognoz > 0 ? ($h['soliq_jami'] ?? 0) / $prognoz * 100 : 0;
    
    $jami_undirilgan = ($h['und_dsb'] ?? 0) + ($h['und_mib'] ?? 0);
    $nomuvofiqliklar[$kalit]['qarz_foiz'] = $jami_undirilgan > 0 ? ($jami_undirilgan / ($h['und_jami'] ?? 1)) * 100 : 0;
    
    $nomuvofiqliklar[$kalit]['vm_foiz'] = ($h['bozor_raqam'] ?? 0) > 0 ? ($h['bozor_vm'] ?? 0) / $h['bozor_raqam'] * 100 : 0;
    $nomuvofiqliklar[$kalit]['soliq_foiz2'] = ($h['bozor_raqam'] ?? 0) > 0 ? ($h['bozor_soliq'] ?? 0) / $h['bozor_raqam'] * 100 : 0;
    $nomuvofiqliklar[$kalit]['qm_foiz'] = ($h['qm_reja'] ?? 0) > 0 ? ($h['qm_jami'] ?? 0) / $h['qm_reja'] * 100 : 0;
}

// Har bir qoida bo'yicha ogohlantirishlar soni
$qoida_ogohlantirishlar = [];
$jami_ogohlantirishlar = 0;
foreach ($qoidalar as $q) {
    $qoida_nomi = $q['nom'];
    $qoida_ogohlantirishlar[$qoida_nomi] = 0;
    
    foreach ($nomuvofiqliklar as $kalit => $n) {
        if ($qoida_nomi == 'Hisobot topshirish' && $n['hisobot'] != 'Topshirilgan') $qoida_ogohlantirishlar[$qoida_nomi]++;
        elseif ($qoida_nomi == 'Bozor raqamlashtirish' && $n['bozor_foiz'] < 70) $qoida_ogohlantirishlar[$qoida_nomi]++;
        elseif ($qoida_nomi == 'Soliq prognozining bajarilishi' && $n['soliq_foiz'] < 80) $qoida_ogohlantirishlar[$qoida_nomi]++;
        elseif ($qoida_nomi == 'Undirilgan qarz' && $n['qarz_foiz'] < 50) $qoida_ogohlantirishlar[$qoida_nomi]++;
        elseif ($qoida_nomi == 'VMga ulanish' && $n['vm_foiz'] < 60) $qoida_ogohlantirishlar[$qoida_nomi]++;
        elseif ($qoida_nomi == 'Soliq organiga ulanish' && $n['soliq_foiz2'] < 50) $qoida_ogohlantirishlar[$qoida_nomi]++;
        elseif ($qoida_nomi == 'Qo\'shimcha manba bajarilishi' && $n['qm_foiz'] < 80) $qoida_ogohlantirishlar[$qoida_nomi]++;
    }
    $jami_ogohlantirishlar += $qoida_ogohlantirishlar[$qoida_nomi];
}

$tuman_ogohlantirishlari = [];
foreach ($nomuvofiqliklar as $kalit => $n) {
    $ogoh = 0;
    if ($n['hisobot'] != 'Topshirilgan') $ogoh++;
    if ($n['bozor_foiz'] < 70) $ogoh++;
    if ($n['soliq_foiz'] < 80) $ogoh++;
    if ($n['qarz_foiz'] < 50) $ogoh++;
    if ($n['vm_foiz'] < 60) $ogoh++;
    if ($n['soliq_foiz2'] < 50) $ogoh++;
    if ($n['qm_foiz'] < 80) $ogoh++;
    $tuman_ogohlantirishlari[$kalit] = $ogoh;
}

include 'includes/header.php';
?>

<style>
    :root {
        --bg: #f0f4ff;
        --white: #ffffff;
        --border: #e2e8f0;
        --text-dark: #0f172a;
        --text-gray: #64748b;
        --blue: #3b82f6;
        --dark-blue: #1e293b;
        --green: #22c55e;
        --red: #ef4444;
        --yellow: #eab308;
        --purple: #8b5cf6;
        --shadow: 0 10px 15px -3px rgba(0,0,0,0.08), 0 4px 6px -2px rgba(0,0,0,0.03);
        --shadow-hover: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04);
        --radius: 20px;
        --radius-sm: 12px;
    }

    * { box-sizing: border-box; }

    .content {
        padding: 20px 30px 40px;
        max-width: 2150px;
        margin: 0 auto;
    }

    .boshqaruv {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 20px;
        margin-bottom: 30px;
        padding: 28px 32px;
        background: var(--white);
        border-radius: var(--radius);
        box-shadow: var(--shadow);
        border: 1px solid rgba(59, 130, 246, 0.08);
        position: relative;
        overflow: hidden;
    }

    .boshqaruv::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0;
        height: 4px;
        background: linear-gradient(90deg, #3b82f6, #8b5cf6, #3b82f6);
        background-size: 200% 100%;
        animation: gradientMove 3s ease-in-out infinite;
    }

    @keyframes gradientMove {
        0%, 100% { background-position: 0% 50%; }
        50% { background-position: 100% 50%; }
    }

    .boshqaruv h2 {
        font-size: 34px;
        font-weight: 800;
        color: var(--text-dark);
        letter-spacing: -0.5px;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .boshqaruv h2 span {
        background: linear-gradient(135deg, #3b82f6, #8b5cf6);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    .boshqaruv p {
        font-size: 17px;
        color: var(--text-gray);
        margin-top: 4px;
        font-weight: 500;
    }

    .boshqaruv .date-wrapper {
        display: flex;
        align-items: center;
        gap: 14px;
        background: #f8fafc;
        padding: 8px 20px 8px 24px;
        border-radius: 50px;
        border: 1px solid var(--border);
        transition: all 0.3s;
    }

    .boshqaruv .date-wrapper:hover {
        border-color: var(--blue);
        box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.08);
    }

    .boshqaruv .date-wrapper label {
        font-size: 15px;
        font-weight: 600;
        color: var(--text-gray);
        display: flex;
        align-items: center;
        gap: 6px;
    }

    input[type="date"] {
        padding: 10px 14px;
        border: none;
        border-radius: 10px;
        font-size: 16px;
        font-weight: 600;
        background: transparent;
        color: var(--text-dark);
        cursor: pointer;
        transition: all 0.3s;
        min-width: 160px;
    }

    input[type="date"]:focus { outline: none; }
    input[type="date"]::-webkit-calendar-picker-indicator { cursor: pointer; opacity: 0.6; transition: all 0.3s; }
    input[type="date"]::-webkit-calendar-picker-indicator:hover { opacity: 1; transform: scale(1.1); }

    .success-message {
        background: linear-gradient(135deg, #dcfce7, #bbf7d0);
        border: 1px solid #86efac;
        color: #166534;
        padding: 18px 24px;
        border-radius: var(--radius-sm);
        margin-bottom: 24px;
        font-size: 16px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 12px;
        animation: slideDown 0.4s ease;
        box-shadow: 0 4px 12px rgba(34, 197, 94, 0.15);
    }

    .xato-quti {
        background: linear-gradient(135deg, #fee2e2, #fecaca);
        border: 1px solid #fca5a5;
        color: #991b1b;
        padding: 18px 24px;
        border-radius: var(--radius-sm);
        margin-bottom: 24px;
        font-size: 16px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 12px;
        animation: slideDown 0.4s ease;
        box-shadow: 0 4px 12px rgba(239, 68, 68, 0.15);
    }

    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .stat-kartalar {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
        margin-bottom: 30px;
    }

    .stat-karta {
        flex: 1;
        min-width: 200px;
        border-radius: var(--radius);
        padding: 28px 20px;
        text-align: center;
        color: #ffffff;
        position: relative;
        overflow: hidden;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        cursor: default;
        border: none;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    }

    .stat-karta:hover {
        transform: translateY(-6px) scale(1.02);
        box-shadow: var(--shadow-hover);
    }

    .stat-karta::after {
        content: '';
        position: absolute;
        top: -50%; right: -50%;
        width: 100%; height: 100%;
        background: rgba(255,255,255,0.06);
        border-radius: 50%;
        transform: scale(0);
        transition: transform 0.6s ease;
    }

    .stat-karta:hover::after { transform: scale(2); }

    .stat-karta b {
        font-size: 48px;
        font-weight: 800;
        display: block;
        margin-bottom: 6px;
        position: relative;
        z-index: 1;
        color: #ffffff;
    }

    .stat-karta span {
        font-size: 17px;
        opacity: 0.92;
        font-weight: 500;
        position: relative;
        z-index: 1;
        letter-spacing: 0.3px;
        color: #ffffff;
    }

    .stat-karta .stat-icon {
        position: absolute;
        top: 12px; right: 16px;
        font-size: 38px;
        opacity: 0.2;
        z-index: 0;
    }

    .stat-karta.tuman-soni { background: linear-gradient(135deg, #0a1628, #1a2a5e); }
    .stat-karta.topshirilgan { background: linear-gradient(135deg, #0a1628, #1a2a5e); }
    .stat-karta.ogohlantirish { background: linear-gradient(135deg, #dc2626, #b91c1c); }
    .stat-karta.kiritilmagan { background: linear-gradient(135deg, #d97706, #b45309); }

    .tooltip {
        position: relative;
        display: inline-block;
        cursor: help;
    }

    .tooltip .tooltip-text {
        visibility: hidden;
        width: 280px;
        background: #1e293b;
        color: #f1f5f9;
        text-align: left;
        border-radius: 12px;
        padding: 18px 20px;
        position: absolute;
        z-index: 100;
        bottom: calc(100% + 14px);
        left: 50%;
        transform: translateX(-50%);
        opacity: 0;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        font-size: 14px;
        line-height: 1.7;
        box-shadow: 0 20px 40px rgba(0,0,0,0.3);
        font-weight: 400;
        pointer-events: none;
    }

    .tooltip .tooltip-text::after {
        content: "";
        position: absolute;
        top: 100%; left: 50%;
        margin-left: -6px;
        border-width: 6px;
        border-style: solid;
        border-color: #1e293b transparent transparent transparent;
    }

    .tooltip:hover .tooltip-text {
        visibility: visible;
        opacity: 1;
        transform: translateX(-50%) translateY(-4px);
    }

    .qoida-ro'yxat {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .qoida {
        background: var(--white);
        border-radius: var(--radius);
        padding: 26px 24px 22px;
        border: 1px solid var(--border);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        box-shadow: var(--shadow);
    }

    .qoida:hover {
        border-color: var(--blue);
        transform: translateY(-4px);
        box-shadow: var(--shadow-hover);
    }

    .qoida .ikona {
        font-size: 38px;
        margin-bottom: 12px;
        display: block;
    }

    .qoida .qoida-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 8px;
    }

    .qoida b {
        font-size: 19px;
        font-weight: 700;
        color: var(--text-dark);
        display: block;
        line-height: 1.3;
    }

    .qoida p {
        font-size: 15px;
        color: var(--text-gray);
        line-height: 1.6;
        margin-bottom: 14px;
        flex: 1;
    }

    .qoida .turi {
        display: inline-block;
        font-size: 12px;
        font-weight: 700;
        color: white;
        background: var(--blue);
        padding: 4px 14px;
        border-radius: 20px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .qoida .turi.majburiy { background: linear-gradient(135deg, #ef4444, #dc2626); }
    .qoida .turi.foiz { background: linear-gradient(135deg, #3b82f6, #2563eb); }

    .qoida .qoida-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 10px;
        padding-top: 14px;
        border-top: 1px solid var(--border);
    }

    .qoida .ogoh-soni {
        font-size: 16px;
        font-weight: 700;
        padding: 4px 14px;
        border-radius: 20px;
        background: #f1f5f9;
        transition: all 0.3s;
    }

    .qoida .ogoh-soni.zero { color: var(--green); background: #dcfce7; }
    .qoida .ogoh-soni.positive { color: var(--red); background: #fee2e2; }

    .qoida .chegara-label {
        font-size: 14px;
        color: var(--text-gray);
        font-weight: 600;
        background: #f8fafc;
        padding: 4px 12px;
        border-radius: 12px;
    }

    .nazorat-quti {
        background: var(--white);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 28px 28px 32px;
        margin-bottom: 30px;
        box-shadow: var(--shadow);
        transition: all 0.3s;
    }

    .nazorat-quti:hover { box-shadow: var(--shadow-hover); }

    .nazorat-quti .table-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 22px;
        flex-wrap: wrap;
        gap: 12px;
    }

    .nazorat-quti h3 {
        font-size: 22px;
        font-weight: 700;
        color: var(--text-dark);
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .nazorat-quti h3 .badge-count {
        font-size: 15px;
        font-weight: 600;
        color: var(--text-gray);
        background: #f1f5f9;
        padding: 2px 14px;
        border-radius: 20px;
    }

    .table-responsive {
        overflow-x: auto;
        border-radius: var(--radius-sm);
        border: 1px solid var(--border);
    }

    table {
        width: 100%;
        border-collapse: collapse;
        min-width: 1400px;
        font-size: 15px;
    }

    th, td {
        padding: 16px 18px;
        border: 1px solid var(--border);
        text-align: center;
        vertical-align: middle;
    }

    th {
        background: #f8fafc;
        color: var(--text-dark);
        font-weight: 700;
        text-transform: uppercase;
        font-size: 13px;
        letter-spacing: 0.5px;
        position: sticky;
        top: 0;
        z-index: 10;
        border-bottom: 2px solid var(--border);
    }

    tr:hover { background: #f8fafc; }

    .tuman-nomi {
        font-weight: 700;
        color: var(--text-dark);
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 16px;
    }

    .tuman-nomi .tuman-badge {
        font-size: 12px;
        font-weight: 600;
        color: var(--text-gray);
        background: #f1f5f9;
        padding: 1px 10px;
        border-radius: 10px;
    }

    .badge {
        padding: 6px 16px;
        border-radius: 20px;
        font-size: 14px;
        font-weight: 600;
        display: inline-block;
        white-space: nowrap;
    }

    .badge-yashil { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
    .badge-sariq { background: #fef9c3; color: #854d0e; border: 1px solid #fde68a; }
    .badge-qizil { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
    .badge-kulrang { background: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0; }

    .progress-wrap {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 4px;
        min-width: 80px;
    }

    .progress-wrap .value {
        font-size: 15px;
        font-weight: 700;
        color: var(--text-dark);
    }

    .progress-bar {
        width: 100%;
        max-width: 120px;
        height: 8px;
        background: #e2e8f0;
        border-radius: 10px;
        overflow: hidden;
    }

    .progress-bar div {
        height: 100%;
        border-radius: 10px;
        transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .btn-delete-today {
        background: linear-gradient(135deg, #ef4444, #dc2626);
        color: white;
        border: none;
        padding: 8px 16px;
        font-size: 13px;
        font-weight: 700;
        border-radius: 8px;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 2px 8px rgba(239, 68, 68, 0.25);
        white-space: nowrap;
    }

    .btn-delete-today:hover {
        transform: translateY(-2px) scale(1.02);
        box-shadow: 0 6px 20px rgba(239, 68, 68, 0.35);
    }

    .text-muted { color: #94a3b8; font-size: 14px; }

    .footer-note {
        margin-top: 30px;
        padding: 20px 28px;
        background: var(--white);
        border-radius: var(--radius);
        border: 1px solid var(--border);
        font-size: 15px;
        color: var(--text-gray);
        line-height: 1.8;
        box-shadow: var(--shadow);
    }

    .footer-note .rule-tag {
        display: inline-block;
        background: #f1f5f9;
        padding: 2px 12px;
        border-radius: 12px;
        font-size: 14px;
        font-weight: 600;
        color: var(--text-dark);
        margin: 0 2px;
    }

    /* ===== OYLIK REJA KIRITISH BO'LIMI UCHUN CHIROYLI STYLE ===== */
    .reja-input {
        width: 140px;
        padding: 10px 14px;
        border: 2px solid var(--border);
        border-radius: 10px;
        font-size: 16px;
        font-weight: 600;
        text-align: center;
        color: var(--text-dark);
        background: #f8fafc;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .reja-input:focus {
        border-color: var(--blue);
        background: #fff;
        outline: none;
        box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
    }

    .reja-input:hover {
        border-color: #94a3b8;
    }

    .btn-save-reja {
        background: linear-gradient(135deg, #3b82f6, #2563eb);
        color: white;
        border: none;
        padding: 10px 24px;
        font-size: 14px;
        font-weight: 700;
        border-radius: 10px;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.25);
        white-space: nowrap;
    }

    .btn-save-reja:hover {
        transform: translateY(-2px) scale(1.02);
        box-shadow: 0 6px 20px rgba(59, 130, 246, 0.35);
    }

    .badge-status {
        font-size: 13px;
        font-weight: 600;
        padding: 6px 12px;
        border-radius: 20px;
    }

    @media (max-width: 768px) {
        .content { padding: 12px 16px 30px; }
        .boshqaruv { padding: 20px; flex-direction: column; align-items: stretch; gap: 16px; }
        .boshqaruv h2 { font-size: 26px; }
        .boshqaruv .date-wrapper { padding: 6px 12px; justify-content: space-between; border-radius: 12px; }
        .stat-karta { min-width: 150px; padding: 18px 12px; }
        .stat-karta b { font-size: 32px; }
        .qoida-ro'yxat { grid-template-columns: 1fr; }
        .nazorat-quti { padding: 16px; }
        .nazorat-quti .table-header { flex-direction: column; align-items: flex-start; }
        th, td { padding: 10px 8px; font-size: 13px; }
        .btn-delete-today { font-size: 11px; padding: 5px 10px; }
    }

    @media (max-width: 480px) {
        .stat-kartalar { gap: 10px; }
        .stat-karta { min-width: 120px; padding: 14px 10px; }
        .stat-karta b { font-size: 28px; }
        .stat-karta span { font-size: 14px; }
    }
</style>

<div class="content">
    <div class="boshqaruv">
        <div>
            <h2>📊 <span>Nazorat qoidalari</span></h2>
            <p>Viloyat bo'yicha barcha tumanlardagi nomuvofiqliklarni tekshirish</p>
        </div>
        <div class="date-wrapper">
            <label>📅 <span style="font-weight:400;">Sana:</span></label>
            <input type="date" value="<?php echo $sana; ?>" max="<?php echo date('Y-m-d'); ?>" 
                   onchange="location.href='?sana='+this.value">
        </div>
    </div>

    <?php if(isset($_GET['deleted_today'])): ?>
        <div class="success-message">✅ Kunlik hisobot muvaffaqiyatli o'chirildi!</div>
    <?php elseif(isset($_GET['saved_qm'])): ?>
        <div class="success-message">✅ Oylik reja muvaffaqiyatli saqlandi!</div>
    <?php elseif(isset($error)): ?>
        <div class="xato-quti">❌ <?php echo $error; ?></div>
    <?php endif; ?>

    <!-- ===== STATISTIKA ===== -->
    <div class="stat-kartalar">
        <div class="stat-karta tuman-soni">
            <span class="stat-icon">🏙️</span>
            <b><?php echo count($holatlar); ?></b>
            <span>Tumanlar soni</span>
        </div>
        <div class="stat-karta topshirilgan">
            <span class="stat-icon">✅</span>
            <b><?php echo $topshirilgan_soni; ?></b>
            <span>Topshirilgan</span>
        </div>
        <div class="stat-karta ogohlantirish tooltip">
            <span class="stat-icon">⚠️</span>
            <b><?php echo $jami_ogohlantirishlar; ?></b>
            <span>Jami ogohlantirishlar</span>
            <div class="tooltip-text">
                <strong style="display:block;margin-bottom:8px;">📋 Qoidalar bo'yicha:</strong>
                <?php 
                $has_warnings = false;
                foreach ($qoida_ogohlantirishlar as $qoida => $soni) {
                    if ($soni > 0) {
                        echo '<div style="display:flex;justify-content:space-between;padding:2px 0;">';
                        echo '<span>' . htmlspecialchars($qoida) . '</span>';
                        echo '<span style="font-weight:700;color:#fca5a5;">' . $soni . ' ta</span>';
                        echo '</div>';
                        $has_warnings = true;
                    }
                }
                if (!$has_warnings) {
                    echo '<span style="color:#86efac;">✅ Barcha qoidalar bajarilgan</span>';
                }
                ?>
            </div>
        </div>
        <div class="stat-karta kiritilmagan">
            <span class="stat-icon">⏳</span>
            <b><?php echo $kiritilmagan_soni; ?></b>
            <span>Kiritilmagan</span>
        </div>
    </div>

    <!-- NAZORAT QOIDALARI -->
    <div class="qoida-ro'yxat">
        <?php foreach ($qoidalar as $q): 
            $ogoh = $qoida_ogohlantirishlar[$q['nom']] ?? 0;
        ?>
        <div class="qoida">
            <div>
                <span class="ikona"><?php echo $q['ikona']; ?></span>
                <div class="qoida-header">
                    <b><?php echo htmlspecialchars($q['nom']); ?></b>
                    <span class="turi <?php echo $q['turi']; ?>"><?php echo htmlspecialchars($q['turi']); ?></span>
                </div>
                <p><?php echo htmlspecialchars($q['tavsif']); ?></p>
            </div>
            <div class="qoida-footer">
                <span class="chegara-label">🎯 <?php echo $q['foiz_chegara']; ?>%</span>
                <span class="ogoh-soni <?php echo $ogoh == 0 ? 'zero' : 'positive'; ?>">
                    <?php echo $ogoh == 0 ? '✅ Bajarilgan' : '⚠️ ' . $ogoh . ' ta'; ?>
                </span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- JADVAL -->
    <div class="nazorat-quti">
        <div class="table-header">
            <h3>
                📋 Barcha tumanlar bo'yicha nazorat jadvali
                <span class="badge-count"><?php echo count($holatlar); ?> tuman</span>
            </h3>
            <div class="table-actions">
                <span style="font-size:15px;color:var(--text-gray);font-weight:500;">📌 <?php echo date('d.m.Y', strtotime($sana)); ?></span>
            </div>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th style="width:45px;">#</th>
                        <th style="text-align:left;min-width:120px;">Tuman</th>
                        <th style="min-width:100px;">Hisobot holati</th>
                        <th style="min-width:100px;">📱 Raqamlashtirish</th>
                        <th style="min-width:100px;">💰 Soliq</th>
                        <th style="min-width:100px;">💳 Qarz</th>
                        <th style="min-width:100px;">🖥️ VMga ulanish</th>
                        <th style="min-width:100px;">🏛️ Soliq org.</th>
                        <th style="min-width:100px;">📊 Qo'shimcha manba</th>
                        <th style="min-width:80px;">⚠️ Ogoh</th>
                        <th style="min-width:130px;">Amallar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach ($holatlar as $kalit => $h): 
                        $hisobot_holati = $nomuvofiqliklar[$kalit]['hisobot'];
                        $bozor_foiz = $nomuvofiqliklar[$kalit]['bozor_foiz'];
                        $soliq_foiz = $nomuvofiqliklar[$kalit]['soliq_foiz'];
                        $qarz_foiz = $nomuvofiqliklar[$kalit]['qarz_foiz'];
                        $vm_foiz = $nomuvofiqliklar[$kalit]['vm_foiz'];
                        $soliq_foiz2 = $nomuvofiqliklar[$kalit]['soliq_foiz2'];
                        $qm_foiz = $nomuvofiqliklar[$kalit]['qm_foiz'];
                        
                        $ogoh = $tuman_ogohlantirishlari[$kalit] ?? 0;
                        
                        if ($ogoh == 0) {
                            $ogoh_rang = 'badge-yashil';
                            $ogoh_text = '✅ 0';
                        } elseif ($ogoh <= 2) {
                            $ogoh_rang = 'badge-sariq';
                            $ogoh_text = '⚠️ ' . $ogoh;
                        } else {
                            $ogoh_rang = 'badge-qizil';
                            $ogoh_text = '🚨 ' . $ogoh;
                        }
                        
                        $holat_badge = '';
                        if ($hisobot_holati == 'Topshirilgan') $holat_badge = 'badge-yashil';
                        elseif ($hisobot_holati == 'Qoralama') $holat_badge = 'badge-sariq';
                        else $holat_badge = 'badge-qizil';
                        
                        $tuman_id = $tumanlar[$kalit]['id'] ?? 0;
                        $tuman_nomi = $tumanlar[$kalit]['nom'] ?? $kalit;
                    ?>
                    <tr>
                        <td style="font-weight:600;color:var(--text-gray);font-size:15px;"><?php echo $i++; ?></td>
                        <td style="text-align:left;">
                            <div class="tuman-nomi">
                                <?php echo htmlspecialchars($tuman_nomi); ?>
                                <span class="tuman-badge"><?php echo $kalit; ?></span>
                            </div>
                        </td>
                        <td><span class="badge <?php echo $holat_badge; ?>"><?php echo $hisobot_holati; ?></span></td>
                        <td>
                            <div class="progress-wrap">
                                <span class="value" style="color:<?php echo $bozor_foiz >= 70 ? 'var(--green)' : 'var(--red)'; ?>;"><?php echo number_format($bozor_foiz, 1); ?>%</span>
                                <div class="progress-bar">
                                    <div style="width: <?php echo min(100, $bozor_foiz); ?>%; background: <?php echo $bozor_foiz >= 70 ? 'var(--green)' : 'var(--red)'; ?>;"></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="progress-wrap">
                                <span class="value" style="color:<?php echo $soliq_foiz >= 80 ? 'var(--green)' : 'var(--red)'; ?>;"><?php echo number_format($soliq_foiz, 1); ?>%</span>
                                <div class="progress-bar">
                                    <div style="width: <?php echo min(100, $soliq_foiz); ?>%; background: <?php echo $soliq_foiz >= 80 ? 'var(--green)' : 'var(--red)'; ?>;"></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="progress-wrap">
                                <span class="value" style="color:<?php echo $qarz_foiz >= 50 ? 'var(--green)' : 'var(--red)'; ?>;"><?php echo number_format($qarz_foiz, 1); ?>%</span>
                                <div class="progress-bar">
                                    <div style="width: <?php echo min(100, $qarz_foiz); ?>%; background: <?php echo $qarz_foiz >= 50 ? 'var(--green)' : 'var(--red)'; ?>;"></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="progress-wrap">
                                <span class="value" style="color:<?php echo $vm_foiz >= 60 ? 'var(--green)' : 'var(--red)'; ?>;"><?php echo number_format($vm_foiz, 1); ?>%</span>
                                <div class="progress-bar">
                                    <div style="width: <?php echo min(100, $vm_foiz); ?>%; background: <?php echo $vm_foiz >= 60 ? 'var(--green)' : 'var(--red)'; ?>;"></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="progress-wrap">
                                <span class="value" style="color:<?php echo $soliq_foiz2 >= 50 ? 'var(--green)' : 'var(--red)'; ?>;"><?php echo number_format($soliq_foiz2, 1); ?>%</span>
                                <div class="progress-bar">
                                    <div style="width: <?php echo min(100, $soliq_foiz2); ?>%; background: <?php echo $soliq_foiz2 >= 50 ? 'var(--green)' : 'var(--red)'; ?>;"></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="progress-wrap">
                                <span class="value" style="color:<?php echo $qm_foiz >= 80 ? 'var(--green)' : 'var(--red)'; ?>;"><?php echo number_format($qm_foiz, 1); ?>%</span>
                                <div class="progress-bar">
                                    <div style="width: <?php echo min(100, $qm_foiz); ?>%; background: <?php echo $qm_foiz >= 80 ? 'var(--green)' : 'var(--red)'; ?>;"></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="badge <?php echo $ogoh_rang; ?>"><?php echo $ogoh_text; ?></span>
                        </td>
                        <td>
                            <?php if ($h && isset($h['status'])): ?>
                                <a href="?sana=<?php echo $sana; ?>&delete_today=<?php echo $tuman_id; ?>" 
                                   class="btn-delete-today"
                                   onclick="return confirm('Rostdan ham «<?php echo htmlspecialchars($tuman_nomi); ?>» tumanining <?php echo date('d.m.Y', strtotime($sana)); ?> sanadagi hisobotini o\'chirmoqchimisiz?')">
                                   🗑️ O'chirish
                                </a>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- OYLIK REJA KIRITISH BO'LIMI (ADMIN UCHUN) -->
    <div class="nazorat-quti">
        <div class="table-header">
            <h3>
                📊 Qo'shimcha manba - Oylik reja kiritish
                <span class="badge-count">Admin tomonidan kiritiladi</span>
            </h3>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th style="text-align:left; min-width:160px;">Tuman</th>
                        <th style="min-width:180px;">Oylik reja (mlrd)</th>
                        <th style="min-width:140px;">Amallar</th>
                        <th style="min-width:120px;">Holat</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tumanlar as $kalit => $tuman): 
                        $qm_reja = $holatlar[$kalit]['qm_reja'] ?? 0;
                        $holat_class = $qm_reja > 0 ? 'badge-yashil' : 'badge-kulrang';
                        $holat_text = $qm_reja > 0 ? 'Kiritilgan' : 'Kiritilmagan';
                    ?>
                    <tr>
                        <td style="text-align:left;">
                            <strong style="font-size:15px; color:var(--text-dark);"><?php echo htmlspecialchars($tuman['nom']); ?></strong>
                        </td>
                        <td>
                            <form method="POST" style="display:flex; gap:8px; justify-content:center; align-items:center;">
                                <input type="hidden" name="tuman_id" value="<?php echo $tuman['id']; ?>">
                                <input type="text" 
                                       name="qm_reja" 
                                       value="<?php echo $qm_reja > 0 ? number_format($qm_reja, 2, ',', ' ') : ''; ?>" 
                                       class="reja-input" 
                                       placeholder="0,00"
                                       inputmode="decimal"
                                       oninput="this.value = this.value.replace(/[^0-9,]/g, '')">
                                <button type="submit" name="save_qm_reja" class="btn-save-reja">Saqlash</button>
                            </form>
                        </td>
                        <td>
                            <span class="badge badge-status <?php echo $holat_class; ?>"><?php echo $holat_text; ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- FOOTER -->
    <div class="footer-note">
        <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:6px;">
            <span style="font-weight:700;color:var(--text-dark);font-size:16px;">📌 Qoidalar:</span>
            <span class="rule-tag">📋 Hisobot topshirish</span>
            <span class="rule-tag">📱 Bozor raqamlashtirish (70%)</span>
            <span class="rule-tag">💰 Soliq bajarilishi (80%)</span>
            <span class="rule-tag">💳 Undirilgan qarz (50%)</span>
            <span class="rule-tag">🖥️ VMga ulanish (60%)</span>
            <span class="rule-tag">🏛️ Soliq organiga ulanish (50%)</span>
            <span class="rule-tag">📊 Qo'shimcha manba (80%)</span>
        </div>
        <div style="display:flex;flex-wrap:wrap;gap:16px;font-size:14px;color:#94a3b8;">
            <span>🗑️ "O'chirish" tugmasi faqat tanlangan sana uchun hisobotni o'chiradi</span>
            <span>📅 Tuman xodimi qayta yuklashi mumkin</span>
        </div>
    </div>
</div>

<script>
    // Sahifa yuklanganda barcha inputlarni tozalash (faqat raqam va vergul)
    document.querySelectorAll('.reja-input').forEach(input => {
        input.addEventListener('input', function() {
            // Faqat raqam va vergul qabul qilish
            this.value = this.value.replace(/[^0-9,]/g, '');
        });
    });
</script>

<?php include 'includes/footer.php'; ?>