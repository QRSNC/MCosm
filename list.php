<?php
/*****************************************************************
 * list.php — YouTube arama + AI + yerel filtre
 *****************************************************************/
ob_start();
require_once __DIR__ . '/send_ai.php';          // filter_query() + env_get()

$YT_KEY = env_get('YOUTUBE_API_KEY');           // .env'den anahtar

/* ---- Güvenlik başlıkları ---- */
$nonce = base64_encode(random_bytes(16));
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'nonce-$nonce' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; script-src 'self' 'nonce-$nonce'; img-src 'self' data: https://i.ytimg.com; connect-src 'self' https://www.googleapis.com; object-src 'none'; frame-ancestors 'none'; form-action 'self'; base-uri 'self'");
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: no-referrer");

/* ---- Sorgu + filtreler ---- */
$userQ   = trim($_GET['q'] ?? '');
$aiRes   = filter_query($userQ);            // ['banned'=>bool,'query'=>...]
$blocked = $aiRes['banned'];
$q       = $aiRes['query'];

$bannedWords = ['war','fight','politics','terror','racism','savaş','kavga','siyasi','terör','ırkçılık'];
if (!$blocked && $q !== '') {
    $lc = mb_strtolower($q,'UTF-8');
    foreach ($bannedWords as $bw) {
        if (str_contains($lc, $bw)) { $blocked = true; break; }
    }
}

/* ---- YouTube API ---- */
$videos = [];
$msg    = '';

if ($blocked) {
    $msg = 'Bu sorgu AI filtresi tarafından engellendi.';
} elseif ($q === '') {
    $msg = 'Bir arama terimi girin.';
} elseif (!$YT_KEY) {
    $msg = 'YouTube API anahtarı yok.';
} else {
    $searchUrl = 'https://www.googleapis.com/youtube/v3/search?part=snippet&type=video&maxResults=15&q='
               . urlencode($q) . '&key=' . $YT_KEY;
    $resp = @file_get_contents($searchUrl);
    if ($resp) {
        $items = json_decode($resp,true)['items'] ?? [];
        $ids = array_column($items,'id');
        $ids = array_map(fn($x)=>$x['videoId']??'',$ids);
        $ids = array_filter($ids);

        if ($ids) {
            $detailUrl = 'https://www.googleapis.com/youtube/v3/videos?part=contentDetails&id='
                       . implode(',',$ids) . '&key=' . $YT_KEY;
            $detRaw = @file_get_contents($detailUrl);
            if ($detRaw) {
                $durMap = [];
                foreach (json_decode($detRaw,true)['items'] ?? [] as $d)
                    $durMap[$d['id']] = $d['contentDetails']['duration'] ?? '';

                $iso2sec = function($iso){ try { $i=new DateInterval($iso); return (new DateTime('@0'))->add($i)->getTimestamp(); } catch(Throwable){ return 0; } };
                foreach ($items as $it) {
                    $id = $it['id']['videoId'] ?? '';
                    if(!$id || !isset($durMap[$id])) continue;
                    if ($iso2sec($durMap[$id]) <= 60) continue; // Shorts atla
                    $videos[] = [
                        'id'=>$id,
                        'title'=>$it['snippet']['title'],
                        'thumb'=>$it['snippet']['thumbnails']['medium']['url'] ?? ''
                    ];
                }
                if (!$videos) $msg = 'Sonuç yok (Shorts veya filtre).';
            } else $msg = 'Detay isteği hatası.';
        } else $msg = 'Sonuç bulunamadı.';
    } else $msg = 'Arama isteği hatası.';
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<title>Macrocosm · Sonuçlar</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@600;800&display=swap" rel="stylesheet">
<link rel="icon" href="favicon.ico">
<style nonce="<?= $nonce ?>">
*{margin:0;padding:0;box-sizing:border-box}
html,body{height:100%;font-family:'Orbitron',sans-serif;background:#000;color:#fff;overflow:hidden}
video.bg{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;z-index:0}
body::before{content:"";position:absolute;inset:0;background:rgba(0,0,0,.45);backdrop-filter:blur(14px);z-index:1;pointer-events:none}

/* --- üst bar --- */
.top{position:absolute;top:20px;left:30px;right:30px;display:flex;justify-content:space-between;align-items:center;z-index:3}
.logo{height:48px}.logo img{height:100%}
.explore{color:#fff;text-decoration:underline;font-size:16px;cursor:pointer}

/* --- içerik --- */
.wrap{position:relative;z-index:2;height:100%;display:flex;flex-direction:column;align-items:center;padding-top:140px;overflow-y:auto;scrollbar-width:none}
.wrap::-webkit-scrollbar{display:none}

/* --- arama kutusu --- */
.search-box{position:relative;width:80%;max-width:500px;margin-bottom:40px}
.search-in{width:100%;padding:14px 20px 14px 58px;font-size:18px;border:none;border-radius:30px;background:rgba(255,255,255,.15);backdrop-filter:blur(8px);color:#fff}
.btn{position:absolute;left:14px;top:50%;transform:translateY(-50%);height:30px;width:30px;border:none;background:none;cursor:pointer;padding:0}
.btn img{width:100%;height:100%;object-fit:contain}

/* --- video kartları --- */
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:24px;width:90%;max-width:1200px}
.card{background:rgba(255,255,255,.06);backdrop-filter:blur(6px);border-radius:16px;padding:12px;text-decoration:none;color:#fff;transition:.3s}
.card:hover{background:rgba(255,255,255,.12)}
.thumb{width:100%;border-radius:12px}
.title{margin-top:8px;font-size:14px;line-height:1.3em;height:40px;overflow:hidden}

.msg{margin-top:60px;font-size:18px;text-align:center;color:rgba(255,255,255,.85)}
</style>
</head>
<body>
<video class="bg" autoplay muted playsinline><source src="galaxy.mp4" type="video/mp4"></video>

<div class="top">
  <a class="logo" href="./"><img src="favicon.ico" alt="Logo"></a>
  <a class="explore" href="find">Explore</a>
</div>

<div class="wrap">
  <form class="search-box" action="list" method="get">
    <button class="btn" type="submit"><img src="icon.png" alt="search"></button>
    <input class="search-in" name="q" placeholder="Search..." value="<?= htmlspecialchars($userQ) ?>" autocomplete="off">
  </form>

  <?php if ($msg): ?>
    <div class="msg"><?= htmlspecialchars($msg) ?></div>
  <?php else: ?>
    <div class="grid">
      <?php foreach ($videos as $v): ?>
        <a class="card" href="play?id=<?= urlencode($v['id']) ?>">
          <img class="thumb" src="<?= htmlspecialchars($v['thumb']) ?>" alt="">
          <div class="title"><?= htmlspecialchars($v['title']) ?></div>
        </a>
      <?php endforeach ?>
    </div>
  <?php endif ?>
</div>

<script nonce="<?= $nonce ?>">
document.querySelector('video.bg').addEventListener('ended', e=>{e.target.currentTime=0;e.target.play();});
</script>
</body>
</html>
<?php ob_end_flush(); ?>