<?php
require_once __DIR__ . '/db.php'; // $conn (mysqli)

// --- Validate & get album id ---
$aid = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($aid <= 0) { http_response_code(400); die('Bad request'); }

// --- Fetch album ---
$album = null;
if ($stmt = $conn->prepare("SELECT id, title, description, created_at FROM albums WHERE id = ? LIMIT 1")) {
  $stmt->bind_param('i', $aid);
  $stmt->execute();
  $res = $stmt->get_result();
  $album = $res ? $res->fetch_assoc() : null;
  $stmt->close();
}
if (!$album) { http_response_code(404); die('Album not found'); }

// --- Fetch photos in album ---
$photos = [];
if ($stmt = $conn->prepare("SELECT id, filename, description FROM photos WHERE album_id = ? ORDER BY id ASC")) {
  $stmt->bind_param('i', $aid);
  $stmt->execute();
  $res = $stmt->get_result();
  while ($row = $res->fetch_assoc()) { $photos[] = $row; }
  $stmt->close();
}
if (!$photos) { http_response_code(404); die('No photos for this album'); }

// --- SEO / Open Graph (pakai foto pertama) ---
$first   = $photos[0];
$ogTitle = $album['title'] ?: 'Untitled';
$ogDesc  = $album['description'] ?: 'Photo album';
$ogImg   = 'uploads/' . rawurlencode($first['filename']);
$created = date('M j, Y', strtotime($album['created_at']));

// --- Back target: kembali ke admin dashboard kalau dari admin ---
// Tentukan tujuan tombol Back
$backHref = 'index.php#gallery';
if (isset($_GET['from']) && $_GET['from'] === 'admin') {
  $backHref = 'admin/dashboard.php';
} elseif (!empty($_SERVER['HTTP_REFERER'])) {
  // fallback: kalau referer dari /admin/, tetap balik ke dashboard
  $ref = $_SERVER['HTTP_REFERER'];
  if (strpos($ref, '/admin/') !== false) {
    $backHref = 'admin/dashboard.php';
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
  <title><?php echo htmlspecialchars($ogTitle, ENT_QUOTES, 'UTF-8'); ?> — Kept.</title>

  <!-- Open Graph -->
  <meta property="og:type" content="website">
  <meta property="og:title" content="<?php echo htmlspecialchars($ogTitle, ENT_QUOTES, 'UTF-8'); ?>">
  <meta property="og:description" content="<?php echo htmlspecialchars($ogDesc, ENT_QUOTES, 'UTF-8'); ?>">
  <meta property="og:image" content="<?php echo htmlspecialchars($ogImg, ENT_QUOTES, 'UTF-8'); ?>">

  <!-- UI libs -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <script src="https://cdn.tailwindcss.com"></script>

  <style>
    :root { --bg:#0a0a0a; --fg:#eaeaea; --muted:#9aa0a6; }
    html,body { height:100%; }
    body { margin:0; background:var(--bg); color:var(--fg); }
    .safe { padding: env(safe-area-inset-top) env(safe-area-inset-right) env(safe-area-inset-bottom) env(safe-area-inset-left); }
    .topbar {
      position: fixed; inset: 0 0 auto 0; height: 56px; display:flex; align-items:center; gap:10px;
      padding: 0 14px; z-index: 30; background: linear-gradient(to bottom, rgba(0,0,0,.55), rgba(0,0,0,0));
      -webkit-backdrop-filter: blur(2px); backdrop-filter: blur(2px);
    }
    .btn {
      display:inline-flex; align-items:center; gap:8px; padding:8px 12px; border-radius:10px;
      background: rgba(255,255,255,.08); border:1px solid rgba(255,255,255,.18); color:var(--fg);
    }
    .btn:hover { background: rgba(255,255,255,.16); }
    .caption {
      position: fixed; left:0; right:0; bottom:0; z-index: 25;
      padding: 16px; background: linear-gradient(to top, rgba(0,0,0,.55), rgba(0,0,0,0));
    }
    .swiper { position: fixed; inset:0; width:100%; height:100%; }
    .swiper-slide { display:flex; align-items:center; justify-content:center; background:#000; }
    .swiper-slide img { width:100%; height:100%; object-fit:contain; background:#000; }
    .swiper-button-prev, .swiper-button-next { width:44px; height:44px; border-radius:10px; background:rgba(0,0,0,.35); }
    .swiper-pagination-bullet { background: rgba(255,255,255,.6); }
    .swiper-pagination-bullet-active { background: #fff; }
    .meta { font-size:12px; color:var(--muted); }
    @media (max-width: 640px){ .btn span { display:none; } }
  </style>
</head>
<body class="safe">

  <!-- Top Bar -->
  <div class="topbar">
    <a href="<?php echo htmlspecialchars($backHref, ENT_QUOTES, 'UTF-8'); ?>" class="btn" title="Back">
      <i class="fa-solid fa-arrow-left"></i><span>Back</span>
    </a>
    <div class="truncate">
      <strong><?php echo htmlspecialchars($album['title'] ?: 'Untitled', ENT_QUOTES, 'UTF-8'); ?></strong>
      <span class="meta"> · <?php echo $created; ?></span>
    </div>
    <div class="ml-auto flex gap-2">
      <button class="btn" id="btnShare" title="Share"><i class="fa-solid fa-share-nodes"></i><span>Share</span></button>
      <a class="btn" href="<?php echo htmlspecialchars($ogImg, ENT_QUOTES, 'UTF-8'); ?>" download title="Download first">
        <i class="fa-solid fa-download"></i><span>Download</span>
      </a>
    </div>
  </div>

  <!-- Fullscreen Swiper -->
  <div class="swiper" id="album-swiper">
    <div class="swiper-wrapper">
      <?php foreach ($photos as $p):
        $src = 'uploads/' . rawurlencode($p['filename']);
        $alt = htmlspecialchars($p['description'] ?: $album['description'] ?: $album['title'] ?: 'Photo', ENT_QUOTES, 'UTF-8');
      ?>
        <div class="swiper-slide">
          <img src="<?php echo $src; ?>" alt="<?php echo $alt; ?>" loading="lazy" decoding="async">
        </div>
      <?php endforeach; ?>
    </div>
    <div class="swiper-button-prev"></div>
    <div class="swiper-button-next"></div>
    <div class="swiper-pagination"></div>
  </div>

  <!-- Caption -->
  <div class="caption">
    <div class="max-w-3xl mx-auto">
      <div class="text-sm sm:text-base leading-relaxed opacity-90">
        <?php echo nl2br(htmlspecialchars($album['description'] ?? '', ENT_QUOTES, 'UTF-8')); ?>
      </div>
      <div class="meta mt-2">
        Album #<?php echo (int)$album['id']; ?> · <?php echo $created; ?> · <?php echo count($photos); ?> photos
      </div>
    </div>
  </div>

  <!-- Hidden audio so musik bisa lanjut dari index -->
  <audio id="backgroundMusic" style="display:none" preload="metadata">
    <source src="music/Space Song.mp3" type="audio/mpeg">
  </audio>

  <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
  <script>
    // Swiper init (pakai ID yang ada)
    const detailSwiper = new Swiper('#album-swiper', {
      loop: true,
      spaceBetween: 10,
      keyboard: { enabled: true },
      navigation: {
        nextEl: '#album-swiper .swiper-button-next',
        prevEl: '#album-swiper .swiper-button-prev'
      },
      pagination: { el: '#album-swiper .swiper-pagination', clickable: true }
    });

    // Share (opsional)
    document.getElementById('btnShare')?.addEventListener('click', async () => {
      try {
        await navigator.share?.({
          title: <?php echo json_encode($ogTitle); ?>,
          text: <?php echo json_encode($ogDesc); ?>,
          url: window.location.href
        });
      } catch (_) {}
    });

    // Music: restore state dari sessionStorage (tanpa tombol di halaman ini)
    const music = document.getElementById('backgroundMusic');

    (function restoreMusicOnLoad(){
      if(!music) return;
      try{
        const raw = sessionStorage.getItem('kept_music_state');
        if(!raw) return;
        const s = JSON.parse(raw || '{}');
        if(typeof s.t === 'number') music.currentTime = Math.max(0, s.t - 0.15);
        if(s.playing){
          music.play().catch(() => {
            // user gesture fallback
            const once = () => {
              music.play().catch(()=>{}); 
              document.removeEventListener('click', once);
              document.removeEventListener('keydown', once);
            };
            document.addEventListener('click', once, { once: true });
            document.addEventListener('keydown', once, { once: true });
          });
        }
      }catch(e){}
    })();

    // Simpan progress secara berkala
    setInterval(() => {
      if(!music) return;
      try {
        sessionStorage.setItem('kept_music_state', JSON.stringify({
          t: music.currentTime,
          playing: !music.paused
        }));
      } catch(e){}
    }, 700);
  </script>

</body>
</html>
