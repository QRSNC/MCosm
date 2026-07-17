<?php
/*****************************************************************
 * list.php — YouTube arama + AI + yerel filtre + Vision AI
 *****************************************************************/
ob_start();
require_once __DIR__ . '/send_ai.php';

$YT_KEY = env_get('YOUTUBE_API_KEY');

/* ---- Güvenlik başlıkları ---- */
$nonce = base64_encode(random_bytes(16));
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'nonce-$nonce' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; script-src 'self' 'nonce-$nonce'; img-src 'self' data: https://i.ytimg.com; connect-src 'self' https://www.googleapis.com; object-src 'none'; frame-ancestors 'none'; form-action 'self'; base-uri 'self'");
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: no-referrer");

function fetch_json_curl($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $output = curl_exec($ch);
    curl_close($ch);
    return $output ? json_decode($output, true) : null;
}

/* ---- Sorgu + filtreler ---- */
$userQ   = trim($_GET['q'] ?? '');
$aiRes   = filter_query($userQ);
$blocked = $aiRes['banned'];
$q       = $aiRes['query'];

$bannedWords = ['war','fight','politics','terror','racism','savaş','kavga','siyasi','terör','ırkçılık','clickbait','drama','tiktok','brainrot','dopamine','sex'];
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
    $msg = 'Bu arama AI filtrelerimiz veya güvenlik politikamız tarafından engellendi.';
} elseif ($q === '') {
    $msg = 'Bir arama terimi girin.';
} elseif (!$YT_KEY) {
    $msg = 'YouTube API anahtarı yok.';
} else {
    $searchUrl = 'https://www.googleapis.com/youtube/v3/search?part=snippet&type=video&maxResults=20&q='
               . urlencode($q) . '&key=' . $YT_KEY;

    $searchData = fetch_json_curl($searchUrl);

    if ($searchData && isset($searchData['items'])) {
        $items = $searchData['items'];
        $ids = array_column($items,'id');
        $ids = array_map(fn($x)=>$x['videoId']??'',$ids);
        $ids = array_filter($ids);

        if ($ids) {
            $detailUrl = 'https://www.googleapis.com/youtube/v3/videos?part=contentDetails&id='
                       . implode(',',$ids) . '&key=' . $YT_KEY;

            $detData = fetch_json_curl($detailUrl);

            if ($detData && isset($detData['items'])) {
                $durMap = [];
                foreach ($detData['items'] as $d)
                    $durMap[$d['id']] = $d['contentDetails']['duration'] ?? '';

                $iso2sec = function($iso){ try { $i=new DateInterval($iso); return (new DateTime('@0'))->add($i)->getTimestamp(); } catch(Throwable){ return 0; } };

                $preliminary_videos = [];
                foreach ($items as $it) {
                    $id = $it['id']['videoId'] ?? '';
                    if(!$id || !isset($durMap[$id])) continue;
                    if ($iso2sec($durMap[$id]) <= 60) continue; // Shorts atla

                    $preliminary_videos[] = [
                        'id' => $id,
                        'title' => $it['snippet']['title'],
                        'thumb' => $it['snippet']['thumbnails']['medium']['url'] ?? ''
                    ];
                }

                if ($preliminary_videos) {
                    // Vision AI filter
                    $safe_ids = filter_videos($preliminary_videos);

                    foreach ($preliminary_videos as $v) {
                        if (in_array($v['id'], $safe_ids)) {
                            $videos[] = $v;
                        }
                    }

                    if (!$videos) $msg = 'Tüm sonuçlar AI görsel/başlık filtresi tarafından engellendi.';
                } else {
                    $msg = 'Uygun sonuç yok (Shorts elendi).';
                }
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
.search-in{width:100%;padding:14px 20px 14px 58px;font-size:18px;border:none;border-radius:30px;background:rgba(255,255,255,.15);backdrop-filter:blur(8px);color:#fff;outline:none}
.search-in:focus{background:rgba(255,255,255,.25)}
.btn{position:absolute;left:14px;top:50%;transform:translateY(-50%);height:30px;width:30px;border:none;background:none;cursor:pointer;padding:0}
.btn img{width:100%;height:100%;object-fit:contain}

/* --- video kartları --- */
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:24px;width:90%;max-width:1200px; padding-bottom: 50px;}
.card{background:rgba(255,255,255,.06);backdrop-filter:blur(6px);border-radius:16px;padding:12px;text-decoration:none;color:#fff;transition:.3s; border: 1px solid rgba(255,255,255,0.05);}
.card:hover{background:rgba(255,255,255,.12); transform: translateY(-3px);}
.thumb{width:100%;border-radius:12px; aspect-ratio: 16/9; object-fit: cover;}
.title{margin-top:12px;font-size:14px;line-height:1.4em;height:40px;overflow:hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;}

.msg{margin-top:60px;font-size:18px;text-align:center;color:rgba(255,255,255,.85); max-width: 80%; line-height: 1.5;}

/* --- loading overlay --- */
#loading{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.8);backdrop-filter:blur(10px);z-index:99;flex-direction:column;align-items:center;justify-content:center;color:#fff;}
.spinner{width:50px;height:50px;border:4px solid rgba(255,255,255,0.2);border-top-color:#fff;border-radius:50%;animation:spin 1s linear infinite;margin-bottom:20px;}
@keyframes spin{to{transform:rotate(360deg)}}
</style>
</head>
<body>
<video class="bg" autoplay muted playsinline><source src="galaxy.mp4" type="video/mp4"></video>

<div id="loading">
    <div class="spinner"></div>
    <h2>AI is analyzing...</h2>
    <p style="margin-top:10px; color:rgba(255,255,255,0.7); font-size:14px;">Filtering out clickbait and brainrot.</p>
</div>

<div class="top">
  <a class="logo" href="./"><img src="favicon.ico" alt="Logo"></a>
  <a class="explore" href="find">Explore</a>
</div>

<div class="wrap">
  <form class="search-box" id="search-form" action="list" method="get">
    <button class="btn" type="submit"><img src="icon.png" alt="search"></button>
    <input class="search-in" name="q" placeholder="Search..." value="<?= htmlspecialchars($userQ) ?>" autocomplete="off" required>
  </form>

  <?php if ($msg): ?>
    <div class="msg"><?= htmlspecialchars($msg) ?></div>
  <?php else: ?>
    <div class="grid">
      <?php foreach ($videos as $v): ?>
        <a class="card" href="play?id=<?= urlencode($v['id']) ?>">
          <img class="thumb" src="<?= htmlspecialchars($v['thumb']) ?>" alt="">
          <div class="title"><?= htmlspecialchars(html_entity_decode($v['title'], ENT_QUOTES)) ?></div>
        </a>
      <?php endforeach ?>
    </div>
  <?php endif ?>
</div>

<script nonce="<?= $nonce ?>">
document.querySelector('video.bg').addEventListener('ended', e=>{e.target.currentTime=0;e.target.play();});
document.getElementById('search-form').addEventListener('submit', function() {
    document.getElementById('loading').style.display = 'flex';
});
</script>
</body>
</html>
<?php ob_end_flush(); ?>
