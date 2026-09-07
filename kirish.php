<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'includes/auth.php';

// O'zbekiston vaqti
date_default_timezone_set('Asia/Tashkent');

// Til tanlash (Lotin / Kirill)
$til = $_GET['til'] ?? $_COOKIE['til'] ?? 'lot';
if ($til === 'kr') {
    setcookie('til', 'kr', time() + 31536000, '/');
} else {
    setcookie('til', 'lot', time() + 31536000, '/');
}

// Login jarayoni
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login']);
    $parol = $_POST['parol'];
    
    if (login($login, $parol)) {
        // ROLGA QARAB YO'NALTIRISH (MUHIM O'ZGARISH!)
        if ($_SESSION['role'] === 'tuman') {
            header("Location: hodim_dashboard.php");
        } else {
            header("Location: index.php");
        }
        exit;
    } else {
        $error = "Login yoki parol noto'g'ri!";
    }
}

// TO'G'RI KIRILL ALIFBOSI (O'zbek lotin -> O'zbek kirill)
function kirill($matn) {
    // 1-QADAM: Eng uzun birikmalarni AVVAL almashtiramiz
    // (Bu yerda "sh" va "ch" alohida harflarga bo'linib ketmasligi uchun avval yozilgan)
    $birikmalar = [
        "Sh" => "Ш", "sh" => "ш",   // "sh" birinchi!
        "Ch" => "Ч", "ch" => "ч",   // "ch" ikkinchi!
        "O'" => "Ў", "o'" => "ў", 
        "G'" => "Ғ", "g'" => "ғ",
        "Ya" => "Я", "ya" => "я", 
        "Yo" => "Ё", "yo" => "ё",
        "Yu" => "Ю", "yu" => "ю", 
        "Ye" => "Е", "ye" => "е",
        "Ts" => "Ц", "ts" => "ц"
    ];
    
    foreach ($birikmalar as $lot => $kir) {
        $matn = str_replace($lot, $kir, $matn);
    }
    
    // 2-QADAM: Qolgan alohida harflarni almashtiramiz
    $harflar = [
        'a'=>'а','b'=>'б','d'=>'д','e'=>'е','f'=>'ф','g'=>'г','h'=>'ҳ','i'=>'и','j'=>'ж','k'=>'к','l'=>'л','m'=>'м',
        'n'=>'н','o'=>'о','p'=>'п','q'=>'қ','r'=>'р','s'=>'с','t'=>'т','u'=>'у','v'=>'в','x'=>'х','y'=>'й','z'=>'з',
        'c'=>'с','w'=>'в',
        'A'=>'А','B'=>'Б','D'=>'Д','E'=>'Е','F'=>'Ф','G'=>'Г','H'=>'Ҳ','I'=>'И','J'=>'Ж','K'=>'К','L'=>'Л','M'=>'М',
        'N'=>'Н','O'=>'О','P'=>'П','Q'=>'Қ','R'=>'Р','S'=>'С','T'=>'Т','U'=>'У','V'=>'В','X'=>'Х','Y'=>'Й','Z'=>'З',
        'C'=>'С','W'=>'В'
    ];
    
    foreach ($harflar as $lot => $kir) {
        $matn = str_replace($lot, $kir, $matn);
    }
    
    // 3-QADAM: Apostrofni almashtiramiz
    $matn = str_replace("'", "ъ", $matn);
    
    return $matn;
}

// Matnni tanlangan tilga o'tkazish
function matn($lotin) {
    global $til;
    if ($til === 'kr') {
        return kirill($lotin);
    }
    return $lotin;
}
?>
<!DOCTYPE html>
<html lang="uz">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?php echo matn('Kirish · Jizzax hisobot tizimi'); ?></title>
<link rel="icon" href="/favicon.png" type="image/png">
<style>
:root{
  --fon0:#EEF2F8; --fon1:#FFFFFF; --fon3:#E7EDF6;
  --chiz:#D8E0EC; --chiz2:#B9C7DE;
  --matn0:#17233B; --matn1:#46587A; --matn2:#5B6C8B;
  --aksent:#1c5cab; --aksent2:#2a78d6;
  --xavf:#c22f2f; --ogoh:#9a6b00;
  --yon-fon:#12395C; --yon-fon2:#1B4B75;
  --soya:0 8px 24px rgba(23,35,59,.14);
  --mono:ui-monospace,"JetBrains Mono","Cascadia Mono",Consolas,monospace;
}
*{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%}
body{
  font:20px/1.5 system-ui,-apple-system,"Segoe UI",sans-serif;
  color:var(--matn0); background:var(--fon0);
  display:grid; grid-template-columns:minmax(0,1.05fr) minmax(0,.95fr);
}
/* --- chap: rasmiy maydon --- */
.yon{
  background:linear-gradient(160deg,var(--yon-fon2) 0%,var(--yon-fon) 62%,#0C2A45 100%);
  color:#fff; padding:50px 60px 30px; display:flex; flex-direction:column;
  justify-content:space-between; position:relative; overflow:hidden;
  text-align:center;
}
.yon::after{
  content:""; position:absolute; inset:auto -140px -180px auto;
  width:520px; height:520px; border-radius:50%;
  background:radial-gradient(circle,rgba(140,197,255,.16),transparent 68%);
}
.yon-tepa{
  display:flex; align-items:center; justify-content:center; gap:17px;
  margin-bottom:45px; text-align:center;
}
.yon-tepa .gerb{
  width:60px; height:60px; object-fit:contain;
  filter:drop-shadow(0 2px 6px rgba(0,0,0,.35));
}
.yon-tepa b{
  font-size:17px; font-weight:700; letter-spacing:.02em; line-height:1.4;
  text-transform:uppercase; display:block;
}
.yon h1{
  font-size:clamp(26px,3.5vw,40px); font-weight:800; line-height:1.25;
  letter-spacing:-.015em; max-width:100%; position:relative; z-index:1;
  margin:0 auto; text-transform:uppercase;
}
.yon h1 span{color:#8CC5FF}
.yon p{
  margin:18px auto 0; max-width:50ch; color:#C3DBF2; font-size:15px;
  position:relative; z-index:1; line-height:1.7; text-align:center;
}
.belgilar{
  display:flex; gap:35px; justify-content:center; margin-top:45px;
  position:relative; z-index:1; flex-wrap:wrap;
}
.belgi b{display:block;font-family:var(--mono);font-size:28px;font-weight:700;color:#fff}
.belgi span{font-size:13px;color:#8FB3D6;letter-spacing:.04em;text-transform:uppercase;margin-top:4px}
.yon-past{font-size:13px;color:#8FB3D6;position:relative;z-index:1;line-height:1.7;margin-top:30px;text-align:center}

/* --- O'NG TEPA BURCHAK: SANA VA SOAT --- */
.sana-soat{
  position:fixed; top:15px; right:20px; z-index:100;
  background:rgba(255,255,255,.95); border:1px solid #6988b6;
  border-radius:10px; padding:8px 14px; text-align:center;
  box-shadow:0 4px 12px rgba(23,35,59,.1);
  font-family:var(--mono); font-size:14px; color:#17233B;
}
.sana-soat .kun{font-size:12px; color:#5B6C8B; text-transform:uppercase; letter-spacing:.05em; display:block}
.sana-soat .vaqt{font-size:18px; font-weight:700; color:#1c5cab; display:block; margin-top:2px}
.sana-soat .vaqt span{font-size:12px; color:#5B6C8B; font-weight:400}

/* --- o'ng: forma --- */
.ong{display:flex;align-items:center;justify-content:center;padding:50px 40px}
.karta{width:100%;max-width:420px}
.karta h2{font-size:24px;font-weight:700;letter-spacing:-.01em}
.karta .izoh{color:var(--matn2);font-size:14px;margin-top:6px;margin-bottom:24px}
label{display:block;font-size:14px;font-weight:600;color:var(--matn1);
  margin-bottom:6px;letter-spacing:.01em}
input{
  width:100%; padding:12px 14px; font:15px/1.4 inherit; color:var(--matn0);
  background:var(--fon1); border:1px solid var(--chiz2); border-radius:9px;
  transition:border-color .14s, box-shadow .14s;
}
input:focus{outline:none;border-color:var(--aksent2);
  box-shadow:0 0 0 3px rgba(42,120,214,.16)}
.maydon{margin-bottom:15px}
button{
  width:100%; padding:13px 16px; font:600 15px/1 inherit; color:#fff;
  background:var(--aksent); border:0; border-radius:9px; cursor:pointer;
  transition:background .14s; margin-top:6px;
}
button:hover{background:#17508f}
button:active{transform:translateY(1px)}
.xato-quti{
  background:#FDECEC; border:1px solid #F3C4C4; border-left:3px solid var(--xavf);
  color:#8E2020; padding:10px 13px; border-radius:7px; font-size:14px;
  margin-bottom:16px; display:flex; gap:8px; align-items:flex-start;
}
.xato-quti::before{content:"!";font-family:var(--mono);font-weight:700;
  color:var(--xavf);flex:none;width:16px;height:16px;border:1.5px solid var(--xavf);
  border-radius:50%;display:grid;place-items:center;font-size:11px;margin-top:1px}
.til-tanla{display:flex;gap:0;margin-top:14px;border:1px solid var(--chiz2);
  border-radius:9px;overflow:hidden}
.til-tanla a{flex:1;text-align:center;padding:9px 7px;font-size:14px;
  color:var(--matn1);text-decoration:none;background:var(--fon1);
  border-right:1px solid var(--chiz);transition:background .12s}
.til-tanla a:last-child{border-right:0}
.til-tanla a:hover{background:var(--fon3)}
.til-tanla a.faol{background:var(--aksent);color:#fff;font-weight:600}
.ogohlantirish{
  margin-top:20px; padding-top:18px; border-top:1px solid var(--chiz);
  font-size:13px; color:var(--matn2); line-height:1.7;
}
.ogohlantirish b{color:var(--ogoh)}
@media(max-width:900px){
  body{grid-template-columns:1fr;grid-template-rows:auto 1fr}
  .yon{padding:28px 26px 26px}
  .yon h1{font-size:24px;max-width:none}
  .yon p,.belgilar{display:none}
  .yon-past{display:none}
  .sana-soat{top:10px;right:10px;padding:6px 10px;font-size:12px}
}
</style>
</head>
<body>

<!-- O'NG TEPA BURCHAK: SANA VA SOAT -->
<div class="sana-soat" id="sanaSoat">
  <span class="kun" id="kunNomi"></span>
  <span class="vaqt" id="soat">--:--:-- <span>UZT</span></span>
</div>

<section class="yon">
  <div class="yon-tepa">
    <img src="1.png" alt="Gerb" class="gerb">
    <b><?php echo matn('Yashirin iqtisodiyotga qarshi kurashish departamenti'); ?></b>
  </div>
  <div>
    <h1>
      <?php echo matn('Yashirin iqtisodiyotni'); ?><br>
      <span><?php echo matn('qisqartirish'); ?></span><br>
      <?php echo matn("bo'yicha kunlik hisobot"); ?>
    </h1>
    <p><?php echo matn("Tumanlar ishchi guruhlari ma'lumotni bevosita tizimga kiritadi, viloyat paneli 13 tumanni bir joyda ko'radi."); ?></p>
    <div class="belgilar">
      <div class="belgi"><b>13</b><span><?php echo matn('Tuman'); ?></span></div>
      <div class="belgi"><b>28</b><span><?php echo matn("Ko'rsatkich"); ?></span></div>
      <div class="belgi"><b>16</b><span><?php echo matn('Nazorat qoidasi'); ?></span></div>
    </div>
  </div>
  <div class="yon-past">
    <?php echo matn("Tizimdagi barcha amallar jurnalga yoziladi."); ?><br>
    <?php echo matn("Hisob ma'lumotlari shaxsiy — boshqa shaxsga berilmaydi."); ?>
  </div>
</section>

<section class="ong">
  <form class="karta" method="post" action="" autocomplete="off">
    <h2><?php echo matn('TIZIMGA KIRISH'); ?></h2>
    <div class="izoh"><?php echo matn("Hisob ma'lumotlari viloyat prokuraturasi tomonidan beriladi."); ?></div>
    
    <?php if(isset($error)): ?>
    <div class="xato-quti"><?php echo matn($error); ?></div>
    <?php endif; ?>

    <div class="maydon">
      <label for="login"><?php echo matn('Login'); ?></label>
      <input id="login" name="login" required autofocus autocapitalize="off"
             autocorrect="off" spellcheck="false" maxlength="40" value="<?php echo isset($_POST['login']) ? htmlspecialchars($_POST['login']) : ''; ?>">
    </div>
    <div class="maydon">
      <label for="parol"><?php echo matn('Parol'); ?></label>
      <input id="parol" name="parol" type="password" required maxlength="128">
    </div>
    <button type="submit"><?php echo matn('Kirish'); ?></button>
    
    <div class="til-tanla">
      <a href="?til=lot" class="<?php echo $til === 'lot' ? 'faol' : ''; ?>">Lotin</a>
      <a href="?til=kr" class="<?php echo $til === 'kr' ? 'faol' : ''; ?>">Кирилл</a>
    </div>
    
    <div class="ogohlantirish">
      <b><?php echo matn('Diqqat.'); ?></b> <?php echo matn("Tizimga ruxsatsiz kirish va ma'lumotlarni o'zgartirish qonun hujjatlarida belgilangan javobgarlikka sabab bo'ladi. Kirish urinishlari IP manzil bilan qayd etiladi."); ?>
    </div>
  </form>
</section>

<script>
// O'zbekiston vaqti bo'yicha jonli soat
function yangilaSoat() {
    const now = new Date();
    
    // O'zbekiston vaqti (UTC+5)
    const uztTime = new Date(now.toLocaleString('en-US', { timeZone: 'Asia/Tashkent' }));
    
    const kunlar = ['Yakshanba', 'Dushanba', 'Seshanba', 'Chorshanba', 'Payshanba', 'Juma', 'Shanba'];
    const oylar = ['Yanvar', 'Fevral', 'Mart', 'Aprel', 'May', 'Iyun', 'Iyul', 'Avgust', 'Sentabr', 'Oktabr', 'Noyabr', 'Dekabr'];
    
    const kun = kunlar[uztTime.getDay()];
    const oy = oylar[uztTime.getMonth()];
    const sana = uztTime.getDate();
    const yil = uztTime.getFullYear();
    
    const soat = String(uztTime.getHours()).padStart(2, '0');
    const minut = String(uztTime.getMinutes()).padStart(2, '0');
    const sekund = String(uztTime.getSeconds()).padStart(2, '0');
    
    document.getElementById('kunNomi').textContent = kun + ', ' + sana + ' ' + oy + ' ' + yil;
    document.getElementById('soat').innerHTML = soat + ':' + minut + ':' + sekund + ' <span></span>';
}

// Har soniyada yangilash
setInterval(yangilaSoat, 1000);
yangilaSoat(); // Darhol ko'rsatish
</script>

</body>
</html>