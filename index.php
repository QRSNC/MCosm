<?php
ob_start();

/* ---------- SECURITY HEADERS & CSP ---------- */
$nonce = base64_encode(random_bytes(16));
$base  = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/').'/';

$csp = "default-src 'self'; ".
       "script-src 'self' 'nonce-$nonce'; ".
       "style-src  'self' 'nonce-$nonce' https://fonts.googleapis.com; ".
       "font-src   'self' https://fonts.gstatic.com; ".
       "img-src    'self' data:; ".
       "media-src  'self'; ".
       "connect-src 'self'; ".
       "object-src 'none'; ".
       "base-uri   'self'; ".
       "form-action 'self'; ".
       "frame-ancestors 'none'";

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
  <title>Macrocosm</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <base href="<?= htmlspecialchars($base, ENT_QUOTES) ?>">
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@600;800&display=swap" rel="stylesheet">
  <link rel="icon" href="favicon.ico" type="image/x-icon">

  <style nonce="<?= $nonce ?>">
    *{margin:0;padding:0;box-sizing:border-box}
    html,body{height:100%;font-family:'Orbitron',sans-serif;background:#000;color:#fff;overflow:hidden}

    /* Background video */
    video.bg{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;z-index:0}

    /* Blur overlay — click‑through */
    body::before{
      content:"";
      position:absolute;inset:0;
      background:rgba(0,0,0,.4);
      backdrop-filter:blur(16px);
      z-index:1;
      pointer-events:none;
    }

    /* Top bar */
    .top{position:absolute;top:20px;left:30px;right:30px;display:flex;
         justify-content:space-between;align-items:center;z-index:3}
    .logo-link{display:inline-block;height:48px}
    .logo-link img{height:100%;width:auto}
    .explore{font-size:16px;color:#fff;text-decoration:underline;cursor:pointer;
             opacity:.85;transition:opacity .3s}
    .explore:hover{opacity:1}

    /* Center content */
    .wrap{position:relative;z-index:2;height:100%;display:flex;flex-direction:column;
          align-items:center;justify-content:center;text-align:center}
    h1{font-size:88px;font-weight:800;letter-spacing:2px;margin-bottom:10px}
    .slogan{font-size:18px;color:rgba(255,255,255,.6);margin-bottom:40px}

    /* Search box */
    .search-box{position:relative;width:80%;max-width:500px}
    .search-input{
      width:100%;padding:14px 20px 14px 58px;font-size:18px;border:none;border-radius:30px;
      background:rgba(255,255,255,.15);backdrop-filter:blur(8px);
      color:#fff;outline:none;transition:background .3s}
    .search-input::placeholder{color:rgba(255,255,255,.6)}
    .search-input:focus{background:rgba(255,255,255,.25)}
    .search-btn{
      position:absolute;left:14px;top:50%;transform:translateY(-50%);
      height:30px;width:30px;border:none;background:none;cursor:pointer;z-index:3;padding:0}
    .search-btn img{height:100%;width:100%;display:block}

    @media(max-width:600px){
      h1{font-size:50px}.logo-link{height:36px}.search-btn{height:24px;width:24px}
      .search-input{padding-left:50px}.slogan{font-size:14px}
    }
  </style>
</head>
<body>

  <!-- Background video -->
  <video class="bg" id="bg" autoplay muted playsinline>
    <source src="galaxy.mp4" type="video/mp4">
  </video>

  <!-- Top navigation -->
  <div class="top">
    <a class="logo-link" href="./"><img src="favicon.ico" alt="Logo"></a>
    <a class="explore" href="find">Explore</a>
  </div>

  <!-- Center content -->
  <div class="wrap">
    <h1>Macrocosm</h1>
    <div class="slogan">Now it's time to reflect.</div>

    <form class="search-box" action="list" method="get">
      <button class="search-btn" type="submit"><img src="icon.png" alt=""></button>
      <input class="search-input" name="q" type="text" placeholder="Search..." autocomplete="off" required>
    </form>
  </div>

  <script nonce="<?= $nonce ?>">
    /* Smooth video loop */
    document.getElementById('bg').addEventListener('ended',e=>{
      e.target.currentTime=0;e.target.play();
    });
  </script>
</body>
</html>
<?php ob_end_flush(); ?>
