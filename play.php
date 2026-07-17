<?php
ob_start();
$nonce = base64_encode(random_bytes(16));
$base  = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/').'/';

$vid = $_GET['id'] ?? '';
if (!preg_match('/^[a-zA-Z0-9_-]{11}$/', $vid)) {
    $vid = '';
}

$csp = "default-src 'self'; script-src 'self' 'nonce-$nonce'; style-src 'self' 'nonce-$nonce' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https://i.ytimg.com; media-src 'self'; connect-src 'self'; frame-src https://www.youtube-nocookie.com/; base-uri 'self'; form-action 'self'; frame-ancestors 'none'";

header("Content-Security-Policy: $csp");
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: no-referrer");
header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Macrocosm · Play</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <base href="<?= htmlspecialchars($base, ENT_QUOTES) ?>">
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@600;800&display=swap" rel="stylesheet">
  <link rel="icon" href="favicon.ico" type="image/x-icon">

  <style nonce="<?= $nonce ?>">
    *{margin:0;padding:0;box-sizing:border-box}
    html,body{height:100%;font-family:'Orbitron',sans-serif;background:#000;color:#fff;overflow:hidden}

    video.bg{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;z-index:0}
    body::before{
      content:"";
      position:absolute;inset:0;
      background:rgba(0,0,0,.6);
      backdrop-filter:blur(20px);
      z-index:1;
      pointer-events:none;
    }

    .top{position:absolute;top:20px;left:30px;right:30px;display:flex;
         justify-content:space-between;align-items:center;z-index:3}
    .logo-link{display:inline-block;height:48px}
    .logo-link img{height:100%;width:auto}
    .nav-links { display: flex; gap: 20px; }
    .nav-link{font-size:16px;color:#fff;text-decoration:underline;cursor:pointer;
             opacity:.85;transition:opacity .3s}
    .nav-link:hover{opacity:1}

    .wrap{position:relative;z-index:2;height:100%;display:flex;flex-direction:column;
          align-items:center;justify-content:center;text-align:center; padding-top: 80px;}

    .video-container {
        position: relative;
        width: 90%;
        max-width: 1000px;
        aspect-ratio: 16 / 9;
        background: #000;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 10px 30px rgba(0,0,0,0.5);
    }

    .video-container iframe {
        position: absolute;
        top: 0; left: 0; width: 100%; height: 100%;
        border: none;
    }

    .back-btn {
        margin-top: 30px;
        padding: 10px 20px;
        background: rgba(255,255,255,0.15);
        color: #fff;
        text-decoration: none;
        border-radius: 30px;
        backdrop-filter: blur(8px);
        transition: 0.3s;
    }
    .back-btn:hover {
        background: rgba(255,255,255,0.25);
    }
    .msg { font-size: 18px; color: rgba(255,255,255,0.85); margin-top: 50px; }
  </style>
</head>
<body>
  <video class="bg" id="bg" autoplay muted playsinline>
    <source src="galaxy.mp4" type="video/mp4">
  </video>

  <div class="top">
    <a class="logo-link" href="./"><img src="favicon.ico" alt="Logo"></a>
    <div class="nav-links">
        <a class="nav-link" id="back-btn" href="#">Back</a>
        <a class="nav-link" href="find">Explore</a>
    </div>
  </div>

  <div class="wrap">
      <?php if ($vid): ?>
        <div class="video-container">
            <iframe src="https://www.youtube-nocookie.com/embed/<?= htmlspecialchars($vid) ?>?autoplay=1&modestbranding=1&rel=0&iv_load_policy=3"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                    allowfullscreen>
            </iframe>
        </div>
      <?php else: ?>
        <div class="msg">Video bulunamadı veya geçersiz.</div>
      <?php endif; ?>
  </div>

  <script nonce="<?= $nonce ?>">
    document.getElementById('bg').addEventListener('ended',e=>{
      e.target.currentTime=0;e.target.play();
    });

    document.getElementById('back-btn').addEventListener('click', function(e) {
        e.preventDefault();
        history.back();
    });
  </script>
</body>
</html>
<?php ob_end_flush(); ?>
