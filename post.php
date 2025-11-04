<?php
require_once __DIR__ . '/db.php'; // $conn (mysqli)

// --- get active music from settings ---
$bgMusicFile = '';
if ($st=$conn->prepare("SELECT v FROM settings WHERE k='bg_music' LIMIT 1")){
  $st->execute(); $r=$st->get_result();
  if($r && $row=$r->fetch_assoc()) $bgMusicFile=$row['v'] ?? '';
  $st->close();
}
$musicSrc = $bgMusicFile ? ('music/'.rawurlencode($bgMusicFile)) : 'music/Space Song.mp3';

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

// --- Fetch photos (pakai urutan position ASC biar sama kayak index) ---
$photos = [];
if ($stmt = $conn->prepare("SELECT id, filename, description FROM photos WHERE album_id = ? ORDER BY position ASC, id ASC")) {
  $stmt->bind_param('i', $aid);
  $stmt->execute();
  $res = $stmt->get_result();
  while ($row = $res->fetch_assoc()) { $photos[] = $row; }
  $stmt->close();
}
if (!$photos) { http_response_code(404); die('No photos for this album'); }

// --- SEO / OG ---
$first   = $photos[0];
$ogTitle = $album['title'] ?: 'Untitled';
$ogDesc  = $album['description'] ?: 'Photo album';
$ogImg   = 'uploads/' . rawurlencode($first['filename']);
$created = date('M j, Y', strtotime($album['created_at']));

// --- Back target (admin / public) ---
$backHref = 'index.php#gallery';
if (isset($_GET['from']) && $_GET['from'] === 'admin') {
  $backHref = 'admin/dashboard.php';
} elseif (!empty($_SERVER['HTTP_REFERER'])) {
  $ref = $_SERVER['HTTP_REFERER'];
  if (strpos($ref, '/admin/') !== false) $backHref = 'admin/dashboard.php';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
<title><?= htmlspecialchars($ogTitle) ?> — Kept.</title>

<meta property="og:type" content="website">
<meta property="og:title" content="<?= htmlspecialchars($ogTitle) ?>">
<meta property="og:description" content="<?= htmlspecialchars($ogDesc) ?>">
<meta property="og:image" content="<?= htmlspecialchars($ogImg) ?>">

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdn.tailwindcss.com"></script>

<style>
:root {
  --bg: #050505;
  --fg: #eaeaea;
  --muted: #9aa0a6;
  --surface: rgba(255,255,255,0.04);
  --surface-2: rgba(255,255,255,0.12);
  --accent: #ff9bb3;
}
html,body { height:100%; }
body {
  margin:0;
  background: radial-gradient(circle at top, #141414 0%, #050505 55%, #000 100%);
  color:var(--fg);
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
}
.safe { padding: env(safe-area-inset-top) env(safe-area-inset-right) env(safe-area-inset-bottom) env(safe-area-inset-left); }

.topbar {
  position: fixed;
  inset: 0 0 auto 0;
  height: 62px;
  display:flex;
  align-items:center;
  gap:12px;
  padding: 0 16px;
  z-index: 30;
  background: linear-gradient(to bottom, rgba(0,0,0,0.85) 0%, rgba(0,0,0,0) 100%);
  backdrop-filter: blur(16px);
  border-bottom: 1px solid rgba(255,255,255,0.04);
}
.btn {
  display:inline-flex;
  align-items:center;
  gap:8px;
  padding:8px 14px;
  border-radius:14px;
  background: rgba(255,255,255,0.04);
  border:1px solid rgba(255,255,255,0.12);
  color:var(--fg);
  transition: .15s ease-out;
  cursor: pointer;
}
.btn:hover { background: rgba(255,255,255,0.12); }
.album-title { font-weight: 600; letter-spacing: -0.01em; max-width: 100%; }
.meta { font-size:12px; color:var(--muted); }

.swiper {
  position: fixed;
  inset:0;
  width:100%;
  height:100%;
}
.swiper-slide {
  display:flex;
  align-items:center;
  justify-content:center;
  background:#000;
}
.swiper-slide img {
  width:100%;
  height:100%;
  object-fit:contain;
  background:#000;
}
.swiper-button-prev,
.swiper-button-next {
  width:44px;
  height:44px;
  border-radius:14px;
  background:rgba(0,0,0,.35);
  backdrop-filter: blur(12px);
  border:1px solid rgba(255,255,255,0.2);
}
.swiper-button-prev:after,
.swiper-button-next:after {
  font-size:15px;
  font-weight:600;
}
.swiper-pagination-bullet { background: rgba(255,255,255,.4); }
.swiper-pagination-bullet-active { background: var(--accent); }

.caption {
  position: fixed;
  left:0;
  right:0;
  bottom:0;
  z-index: 25;
  padding: 20px 16px 18px;
  background: linear-gradient(to top, rgba(0,0,0,.85), rgba(0,0,0,0));
  backdrop-filter: blur(12px);
}
.caption > .inner {
  max-width: 720px;
  margin: 0 auto;
}
.caption p {
  font-size: 0.9rem;
  line-height: 1.5;
  color: rgba(234,234,234,.9);
}

@media (max-width: 640px){
  .btn span { display:none; }
  .topbar { height: 58px; }
  .caption { padding-bottom: 20px; }
}
</style>
</head>
<body class="safe">

<!-- TOP BAR -->
<div class="topbar">
  <a href="<?= htmlspecialchars($backHref) ?>" class="btn">
    <i class="fa-solid fa-arrow-left"></i>
    <span>Back</span>
  </a>
  <div class="truncate">
    <div class="album-title truncate">
      <?= htmlspecialchars($album['title'] ?: 'Untitled') ?>
    </div>
    <div class="meta"> <?= $created ?> </div>
  </div>
  <div class="ml-auto flex gap-2">
    <button class="btn" id="btnShare">
      <i class="fa-solid fa-share-nodes"></i>
      <span>Share</span>
    </button>
    <!-- ganti jadi button, bukan <a ... download> -->
    <button class="btn" id="btnDownload">
      <i class="fa-solid fa-download"></i>
      <span>Download</span>
    </button>
  </div>
</div>

<!-- SWIPER -->
<div class="swiper" id="album-swiper">
  <div class="swiper-wrapper">
    <?php foreach ($photos as $p):
      $src = 'uploads/' . rawurlencode($p['filename']);
      $alt = htmlspecialchars($p['description'] ?: $album['description'] ?: $album['title'] ?: 'Photo', ENT_QUOTES);
    ?>
      <div class="swiper-slide">
        <img src="<?= $src ?>" alt="<?= $alt ?>" loading="lazy" decoding="async">
      </div>
    <?php endforeach; ?>
  </div>
  <div class="swiper-button-prev"></div>
  <div class="swiper-button-next"></div>
  <div class="swiper-pagination"></div>
</div>

<!-- CAPTION -->
<div class="caption">
  <div class="inner">
    <p><?= nl2br(htmlspecialchars($album['description'] ?? '')) ?></p>
    <div class="meta mt-2">
      Album #<?= (int)$album['id'] ?> · <?= $created ?> · <?= count($photos) ?> photos
    </div>
  </div>
</div>

<!-- musik -->
<audio id="backgroundMusic" preload="metadata">
  <source src="<?= htmlspecialchars($musicSrc) ?>" type="audio/mpeg">
</audio>

<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
<script>
const swiper = new Swiper('#album-swiper', {
  loop: true,
  spaceBetween: 10,
  keyboard: { enabled: true },
  navigation: {
    nextEl: '#album-swiper .swiper-button-next',
    prevEl: '#album-swiper .swiper-button-prev'
  },
  pagination: { el: '#album-swiper .swiper-pagination', clickable: true }
});

// SHARE (tetap)
document.getElementById('btnShare')?.addEventListener('click', async () => {
  try {
    await navigator.share?.({
      title: <?= json_encode($ogTitle) ?>,
      text: <?= json_encode($ogDesc) ?>,
      url: window.location.href
    });
  } catch (_) {}
});

// DOWNLOAD FOTO YANG LAGI AKTIF
document.getElementById('btnDownload')?.addEventListener('click', () => {
  // karena loop: true, ambil slide aktif lalu cari img di dalamnya
  const activeSlide = swiper.slides[swiper.activeIndex];
  if (!activeSlide) return;
  const img = activeSlide.querySelector('img');
  if (!img) return;

  // bikin link sementara
  const a = document.createElement('a');
  a.href = img.src;
  // coba ambil nama file dari src
  const parts = img.src.split('/');
  a.download = parts[parts.length - 1] || ('photo-' + Date.now() + '.jpg');
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
});

// MUSIC STATE SYNC (punya kamu)
const music = document.getElementById('backgroundMusic');

(function restoreMusicOnLoad(){
  if(!music) return;
  try{
    const raw = sessionStorage.getItem('kept_music_state');
    if(!raw) return;
    const s = JSON.parse(raw || '{}');
    if(typeof s.t === 'number') music.currentTime = Math.max(0, s.t - 0.15);
    if(s.playing){
      music.play().catch(()=>{
        const once=()=>{
          music.play().catch(()=>{});
          document.removeEventListener('click',once);
          document.removeEventListener('keydown',once);
        };
        document.addEventListener('click',once,{once:true});
        document.addEventListener('keydown',once,{once:true});
      });
    }
  }catch(e){}
})();

setInterval(()=>{
  if(!music) return;
  try{
    sessionStorage.setItem('kept_music_state',JSON.stringify({
      t:music.currentTime,
      playing:!music.paused
    }));
  }catch(e){}
},700);
</script>

</body>
</html>
