<?php
ob_start();
$nonce = base64_encode(random_bytes(16));
$base  = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/').'/';

$csp = "default-src 'self'; script-src 'self' 'nonce-$nonce'; style-src 'self' 'nonce-$nonce' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:; media-src 'self'; connect-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'";

header("Content-Security-Policy: $csp");
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: no-referrer");
header("Permissions-Policy: geolocation=(), microphone=(), camera=()");

$categories = [
    ['name' => 'Space & Universe', 'query' => 'documentary space universe science'],
    ['name' => 'Technology & AI', 'query' => 'technology artificial intelligence innovations'],
    ['name' => 'Nature & Earth', 'query' => 'nature documentary animals planet earth'],
    ['name' => 'History & Civilizations', 'query' => 'ancient history civilizations documentary'],
    ['name' => 'Science & Physics', 'query' => 'physics quantum mechanics science documentary'],
    ['name' => 'Art & Philosophy', 'query' => 'philosophy art history educational'],
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Macrocosm · Explore</title>
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
      background:rgba(0,0,0,.5);
      backdrop-filter:blur(16px);
      z-index:1;
      pointer-events:none;
    }

    .top{position:absolute;top:20px;left:30px;right:30px;display:flex;
         justify-content:space-between;align-items:center;z-index:3}
    .logo-link{display:inline-block;height:48px}
    .logo-link img{height:100%;width:auto}

    .wrap{position:relative;z-index:2;height:100%;display:flex;flex-direction:column;
          align-items:center; padding-top: 100px; overflow-y:auto; scrollbar-width:none;}
    .wrap::-webkit-scrollbar{display:none}

    h1{font-size:50px;font-weight:800;letter-spacing:2px;margin-bottom:10px; text-align:center;}
    .slogan{font-size:18px;color:rgba(255,255,255,.6);margin-bottom:40px; text-align:center;}

    .categories-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 20px;
        width: 90%;
        max-width: 900px;
        margin-bottom: 60px;
    }

    .cat-card {
        background: rgba(255,255,255,0.1);
        backdrop-filter: blur(10px);
        border-radius: 16px;
        padding: 30px 20px;
        text-align: center;
        text-decoration: none;
        color: #fff;
        font-size: 18px;
        font-weight: 600;
        transition: 0.3s;
        border: 1px solid rgba(255,255,255,0.05);
    }

    .cat-card:hover {
        background: rgba(255,255,255,0.2);
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.3);
    }
    @media(max-width:600px){
      h1{font-size:36px}
    }
  </style>
</head>
<body>
  <video class="bg" id="bg" autoplay muted playsinline>
    <source src="galaxy.mp4" type="video/mp4">
  </video>

  <div class="top">
    <a class="logo-link" href="./"><img src="favicon.ico" alt="Logo"></a>
  </div>

  <div class="wrap">
    <h1>Explore the Universe</h1>
    <div class="slogan">Safe, mind-expanding, and carefully curated topics.</div>

    <div class="categories-grid">
        <?php foreach($categories as $cat): ?>
            <a class="cat-card" href="list?q=<?= urlencode($cat['query']) ?>">
                <?= htmlspecialchars($cat['name']) ?>
            </a>
        <?php endforeach; ?>
    </div>
  </div>

  <script nonce="<?= $nonce ?>">
    document.getElementById('bg').addEventListener('ended',e=>{
      e.target.currentTime=0;e.target.play();
    });
  </script>
</body>
</html>
<?php ob_end_flush(); ?>
