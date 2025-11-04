<?php
require_once __DIR__ . '/db.php'; // expects $conn (mysqli)

// ---------- Helpers ----------
function getUserIP(): string {
  if (!empty($_SERVER['HTTP_CLIENT_IP'])) return $_SERVER['HTTP_CLIENT_IP'];
  if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
    return trim($parts[0]);
  }
  return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

// ---------- Visitors (visited_at) ----------
$ip  = getUserIP();
$ua  = $_SERVER['HTTP_USER_AGENT'] ?? '';
$now = date('Y-m-d H:i:s');

if ($stmt = $conn->prepare('SELECT id FROM visitors WHERE ip_address = ? LIMIT 1')) {
  $stmt->bind_param('s', $ip);
  $stmt->execute();
  $stmt->store_result();
  if ($stmt->num_rows > 0) {
    if ($up = $conn->prepare('UPDATE visitors SET visited_at = ?, user_agent = ? WHERE ip_address = ?')) {
      $up->bind_param('sss', $now, $ua, $ip);
      $up->execute();
      $up->close();
    }
  } else {
    if ($ins = $conn->prepare('INSERT INTO visitors (ip_address, user_agent, visited_at) VALUES (?, ?, ?)')) {
      $ins->bind_param('sss', $ip, $ua, $now);
      $ins->execute();
      $ins->close();
    }
  }
  $stmt->close();
}

// ---------- ambil lagu aktif dari settings ----------
$bgMusicFile  = '';
$bgMusicTitle = '';
if ($st = $conn->prepare("SELECT v FROM settings WHERE k='bg_music' LIMIT 1")) {
  $st->execute();
  $r = $st->get_result();
  if ($r && $row = $r->fetch_assoc()) $bgMusicFile = $row['v'] ?? '';
  $st->close();
}
if ($st = $conn->prepare("SELECT v FROM settings WHERE k='bg_music_title' LIMIT 1")) {
  $st->execute();
  $r = $st->get_result();
  if ($r && $row = $r->fetch_assoc()) $bgMusicTitle = $row['v'] ?? '';
  $st->close();
}
// fallback kalau kosong
$musicSrc = $bgMusicFile ? ('music/' . rawurlencode($bgMusicFile)) : 'music/Space Song.mp3';

// ---------- visitor count ----------
$totalVisitors = 0; 
$onlineVisitors = 0;
if ($res = $conn->query('SELECT COUNT(*) AS total FROM visitors')) {
  $row = $res->fetch_assoc();
  $totalVisitors = (int)($row['total'] ?? 0);
  $res->free();
}
if ($stmt = $conn->prepare('SELECT COUNT(*) AS online FROM visitors WHERE visited_at >= ?')) {
  $limit = date('Y-m-d H:i:s', strtotime('-5 minutes'));
  $stmt->bind_param('s', $limit);
  $stmt->execute();
  $r = $stmt->get_result();
  if ($r) {
    $row = $r->fetch_assoc();
    $onlineVisitors = (int)($row['online'] ?? 0);
  }
  $stmt->close();
}

// ---------- Albums ----------
$albums = [];
if ($res = $conn->query("SELECT id, title, description, created_at, likes FROM albums ORDER BY created_at DESC")) {
  while ($a = $res->fetch_assoc()) {
    $a['photos'] = [];
    $albums[(int)$a['id']] = $a;
  }
  $res->free();
}

// ---------- Photos per album (PAKAI position ASC sekarang) ----------
if ($albums) {
  $ids = implode(',', array_map('intval', array_keys($albums)));
  // penting: order by position ASC, lalu id ASC untuk jaga urutan konsisten
  $sqlPhotos = "SELECT id, filename, description, album_id 
                FROM photos 
                WHERE album_id IN ($ids)
                ORDER BY position ASC, id ASC";
  if ($res = $conn->query($sqlPhotos)) {
    while ($p = $res->fetch_assoc()) {
      $aid = (int)$p['album_id'];
      if (isset($albums[$aid])) {
        $albums[$aid]['photos'][] = $p;
      }
    }
    $res->free();
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Kept.</title>
  <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&family=Short+Stack&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link rel="shortcut icon" href="img/avatar.png" type="image/x-icon">
  <script src="https://cdn.jsdelivr.net/npm/typed.js@2.0.12"></script>
  <script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
  <style>
    :root {
      --pink:#ff9bb3; --purple:#b5a1ff; --blue:#9bd4ff; --yellow:#ffe08a;
      --text:#000;
    }
    body {
      font-family:'Short Stack',cursive;
      background: linear-gradient(to bottom right, var(--pink), var(--purple));
      color: var(--text);
      image-rendering: pixelated;
      min-height: 100vh;
    }
    .pixel-border { border:4px solid #000; box-shadow: 8px 8px 0 rgba(0,0,0,.2); position:relative; background:#fff; }
    .pixel-border:before { content:''; position:absolute; inset:2px; border:2px solid #fff; pointer-events:none; }
    .pixel-border-thick { border:4px solid #000; background:#fff; box-shadow: 6px 6px 0 rgba(0,0,0,.2); }
    .pixel-card { background:#fff; border:4px solid #000; box-shadow:6px 6px 0 rgba(0,0,0,.15); overflow:hidden; }
    .cute-btn {
      background:var(--pink); color:#fff; border:3px solid #000; padding:10px 20px; font-size:.9rem;
      box-shadow:5px 5px 0 rgba(0,0,0,.2); transition:.1s; font-family:'Press Start 2P',cursive; text-shadow:2px 2px 0 rgba(0,0,0,.2);
    }
    .cute-btn:hover { transform:translate(2px,2px); box-shadow:3px 3px 0 rgba(0,0,0,.2); }
    .title-font { font-family:'Press Start 2P',cursive; text-shadow:3px 3px 0 #fff; color:#000; }

    .swiper { --swiper-theme-color:#000; }
    .swiper-button-next, .swiper-button-prev {
      width:34px; height:34px; background:#fff; border:3px solid #000; border-radius:6px; box-shadow:4px 4px 0 rgba(0,0,0,.15);
    }
    .swiper-button-next:after, .swiper-button-prev:after { font-size:14px; color:#000; }
    .swiper-pagination-bullet { background:#000; opacity:.25; }
    .swiper-pagination-bullet-active { background:#000; opacity:1; }

    .card-img { transition: transform .5s ease; }
    .memory-card:hover .card-img { transform: scale(1.04); }

    .card-topbar{
      position:absolute; inset:10px 10px auto 10px;
      display:flex; align-items:center; gap:8px; flex-wrap:wrap; z-index:5;
      pointer-events: auto;
    }
    .card-chip{
      background:#fff; border:2px solid #000; border-radius:8px;
      padding:4px 8px; font-size:11px; line-height:1; box-shadow:3px 3px 0 rgba(0,0,0,.15);
    }
    .card-title{
      max-width: 70%;
      white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
      font-weight: 700;
    }

    .line-clamp-2{ display:-webkit-box; -webkit-box-orient:vertical; -webkit-line-clamp:2; overflow:hidden; }

    .pixel-cloud { position:absolute; background:#fff; border:3px solid #000; border-radius:50%; }
    .pixel-star { position:absolute; color:var(--yellow); text-shadow:2px 2px 0 rgba(0,0,0,.2); animation: twinkle 2s infinite alternate; }
    @keyframes twinkle { from{opacity:.6; transform:scale(1);} to{opacity:1; transform:scale(1.2);} }

    .chip {
      display:inline-flex; align-items:center; gap:10px; padding:10px 16px; border:3px solid #000; background:#fff;
      box-shadow:5px 5px 0 rgba(0,0,0,.15);
    }
    a.link-plain { text-decoration:none; color:#000; }
    a.link-plain:hover { text-decoration: underline; text-decoration-style: dotted; }

    /* New Enhanced Styles */
    .hero-section {
      position: relative;
      min-height: 85vh;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
    }

    
    @keyframes float {
      0%, 100% { transform: translateY(0px); }
      50% { transform: translateY(-15px); }
    }
    
    .gradient-text {
      background: linear-gradient(45deg, var(--pink), var(--purple), var(--blue));
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }
    
    .memory-card {
      transition: all 0.3s ease;
      position: relative;
    }
    
    .memory-card:hover {
      transform: translateY(-8px);
      box-shadow: 8px 16px 0 rgba(0,0,0,.2);
    }
    
    .memory-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: linear-gradient(90deg, var(--pink), var(--purple), var(--blue));
      z-index: 10;
    }
    
    .section-divider {
      height: 3px;
      background: repeating-linear-gradient(90deg, #000, #000 12px, transparent 12px, transparent 24px);
      margin: 3rem 0;
      position: relative;
    }
    
    .section-divider::before {
      content: '✦';
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      background: linear-gradient(to bottom right, var(--pink), var(--purple));
      padding: 0 15px;
      font-size: 1.2rem;
    }
    
    .visitor-counter {
      background: rgba(255,255,255,0.9);
      backdrop-filter: blur(10px);
      border: 3px solid #000;
      padding: 1.5rem;
      border-radius: 12px;
      box-shadow: 6px 6px 0 rgba(0,0,0,.15);
    }
    
    .music-player {
      position: fixed;
      bottom: 20px;
      right: 20px;
      z-index: 100;
      transition: all 0.3s ease;
    }
    
    .music-player:hover {
      transform: scale(1.1);
    }
    
    .album-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
      gap: 2rem;
      padding: 2rem 0;
    }
    
    .footer-section {
      background: rgba(255,255,255,0.1);
      backdrop-filter: blur(5px);
      border-top: 3px solid #000;
      margin-top: 4rem;
      padding: 2rem 0;
    }
    
    .social-icon {
      transition: all 0.3s ease;
    }
    
    .social-icon:hover {
      transform: translateY(-3px) rotate(5deg);
      background: var(--yellow) !important;
    }
    
    .typing-container {
      min-height: 2.5rem;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    
    .pulse-animation {
      animation: pulse 2s infinite;
    }
    
    @keyframes pulse {
      0%, 100% { transform: scale(1); }
      50% { transform: scale(1.05); }
    }
    
    /* Responsive improvements */
    @media (max-width: 768px) {
      .hero-section {
        min-height: 70vh;
        padding: 2rem 0;
      }
      
      .album-grid {
        grid-template-columns: 1fr;
        gap: 1.5rem;
      }
      
      .card-topbar {
        flex-direction: column;
        align-items: flex-start;
        gap: 5px;
      }
      
      .card-title {
        max-width: 100%;
      }
    }
  </style>
</head>
<body class="overflow-x-hidden">

<!-- Enhanced Decorative elements -->
<div class="pixel-cloud floating-element" style="width:80px; height:40px; top:5%; left:5%;"></div>
<div class="pixel-cloud" style="width:60px; height:30px; top:10%; right:10%;"></div>
<div class="pixel-star floating-element" style="top:15%; left:15%;">✦</div>
<div class="pixel-star" style="top:20%; right:20%;">✦</div>

<div class="pixel-star" style="top:85%; right:15%;">✦</div>

<!-- Hero Section -->
<section class="hero-section">
  <div class="max-w-4xl mx-auto px-6 text-center">
    <div class="floating-element" data-aos="fade-down">
      <h1 class="text-4xl md:text-6xl title-font mb-6 gradient-text">Kept.</h1>
    </div>
    
    <div class="w-32 h-2 mx-auto mb-8 section-divider" style="background: repeating-linear-gradient(90deg,#000,#000 12px,transparent 12px,transparent 24px);"></div>
    
    <div class="typing-container mb-10" data-aos="fade-up" data-aos-delay="200">
      <p class="text-lg md:text-xl italic">
        <span id="typed-text"></span>
      </p>
    </div>
    
    <div class="flex flex-col sm:flex-row gap-4 justify-center items-center mb-12" data-aos="fade-up" data-aos-delay="400">
      <a href="#gallery" class="cute-btn link-plain pulse-animation">
        <i class="fas fa-images mr-2"></i> EXPLORE MEMORIES
      </a>
      <a href="admin/login.php" class="cute-btn link-plain" style="background:var(--purple);">
        <i class="fas fa-lock mr-2"></i> ADMIN PANEL
      </a>
    </div>

    <!-- Enhanced Visitors Counter -->
    <div class="flex justify-center" data-aos="fade-up" data-aos-delay="600">
      <div class="visitor-counter">
        <div class="flex flex-wrap gap-8 justify-center items-center">
          <div class="flex items-center gap-3">
            <div class="w-12 h-12 pixel-border flex items-center justify-center pulse-animation">
              <i class="fas fa-users"></i>
            </div>
            <div>
              <div class="text-xs uppercase tracking-wider">VISITORS</div>
              <span id="totalVisitors" class="font-bold text-xl"><?= (int)$totalVisitors; ?></span>
            </div>
          </div>
          <div class="flex items-center gap-3">
            <div class="w-12 h-12 pixel-border flex items-center justify-center pulse-animation">
              <i class="fas fa-signal"></i>
            </div>
            <div>
              <div class="text-xs uppercase tracking-wider">ONLINE</div>
              <span id="onlineVisitors" class="font-bold text-xl"><?= (int)$onlineVisitors; ?></span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Gallery Section -->
<main id="gallery" class="max-w-7xl mx-auto px-4 sm:px-6 pb-16">
  <div class="text-center mb-12" data-aos="fade-up">
    <h2 class="text-2xl md:text-3xl title-font gradient-text">Kept Moments</h2>
    <p class="mt-3 text-base italic max-w-2xl mx-auto">Little memories I didn't want to forget, preserved in digital frames.</p>
  </div>

  <div class="section-divider"></div>

  <div class="album-grid">
    <?php if ($albums): foreach ($albums as $album): 
      $aid    = (int)$album['id']; 
      $photos = $album['photos']; 
      if (!$photos) continue;
    ?>
      <a href="post.php?id=<?= $aid ?>" class="album-link block memory-card pixel-card group relative overflow-hidden" data-aos="fade-up" aria-label="Open album">
        <div class="relative overflow-hidden">
          <div class="card-topbar">
            <span class="card-chip">
              <?= date('M j, Y', strtotime($album['created_at'])) ?>
            </span>
            <span class="card-chip" style="display:flex;align-items:center;gap:6px;">
              <button type="button"
                      class="link-plain"
                      style="display:inline-flex;align-items:center;gap:6px;background:#fff;border:none;cursor:pointer;font-weight:700;"
                      onclick="likeAlbum(<?= $aid ?>, event)">
                <i class="fa-regular fa-heart"></i>
                <span id="likes-<?= $aid ?>"><?= (int)($album['likes'] ?? 0) ?></span>
              </button>
            </span>
          </div>

          <div class="swiper main-swiper-<?= $aid ?>" style="width:100%; aspect-ratio:1/1; border-radius:10px;">
            <div class="swiper-wrapper">
              <?php foreach ($photos as $p):
                $src = 'uploads/' . rawurlencode($p['filename']);
                $alt = htmlspecialchars($p['description'] ?: ($album['description'] ?? ''), ENT_QUOTES, 'UTF-8');
              ?>
                <div class="swiper-slide flex items-center justify-center bg-white">
                  <img src="<?= $src ?>" alt="<?= $alt ?>" class="w-full h-full object-cover card-img">
                </div>
              <?php endforeach; ?>
            </div>
            <div class="swiper-button-prev"></div>
            <div class="swiper-button-next"></div>
            <div class="swiper-pagination"></div>
          </div>
        </div>

        <div class="p-4">
          <?php if (!empty($album['description'])): ?>
            <p class="text-sm italic line-clamp-2">
              <?= htmlspecialchars($album['description'], ENT_QUOTES, 'UTF-8') ?>
            </p>
          <?php else: ?>
            <p class="text-sm italic opacity-60">no description…</p>
          <?php endif; ?>
        </div>
      </a>
    <?php endforeach; else: ?>
      <div class="col-span-full text-center py-20 pixel-card" data-aos="fade-up">
        <div class="mx-auto w-20 h-20 pixel-border rounded-full flex items-center justify-center mb-6 pulse-animation">
          <i class="fas fa-camera text-2xl"></i>
        </div>
        <h3 class="text-xl font-medium mb-3">Empty Archive</h3>
        <p class="opacity-70 max-w-md mx-auto italic">No memories captured yet. Check back later for new moments!</p>
      </div>
    <?php endif; ?>
  </div>
</main>

<!-- Enhanced Music Player -->
<div class="music-player">
  <button id="toggleMusic" class="cute-btn pulse-animation" style="width:70px;height:70px;border-radius:12px;display:flex;align-items:center;justify-content:center;flex-direction:column;padding:10px;">
    <i id="musicIcon" class="fas fa-play mb-1"></i>
  </button>
</div>

<audio id="backgroundMusic" loop preload="metadata">
  <source src="<?= htmlspecialchars($musicSrc, ENT_QUOTES, 'UTF-8') ?>" type="audio/mpeg">
</audio>

<!-- Enhanced Footer -->
<footer class="footer-section">
  <div class="max-w-4xl mx-auto text-center px-6">
    <div class="py-6">
      <div class="chip inline-block">
        <i class="fas fa-clock"></i>
        <span id="clock" class="font-mono">00:00:00</span>
        <span>WIB</span>
      </div>
    </div>
    
    <div class="py-6 my-4 border-t border-b border-black border-dashed">
      <p class="italic text-lg">"What was once a moment, now a memory."</p>
      <p class="text-base mt-3 opacity-70">— Kept.</p>
    </div>
    
    <div class="mb-6">
      <p class="text-sm opacity-70">© 2025 Kept. All memories preserved.</p>
    </div>

    <div class="flex justify-center gap-6">
      <a href="https://www.instagram.com/n4ve.666/" class="social-icon pixel-border w-12 h-12 flex items-center justify-center link-plain" style="background:#fff;">
        <i class="fab fa-instagram"></i>
      </a>
      <a href="https://github.com/newbiema" class="social-icon pixel-border w-12 h-12 flex items-center justify-center link-plain" style="background:#fff;">
        <i class="fab fa-github"></i>
      </a>
      <a href="mailto:evanjamaq123@gmail.com" class="social-icon pixel-border w-12 h-12 flex items-center justify-center link-plain" style="background:#fff;">
        <i class="fas fa-envelope"></i>
      </a>
    </div>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
<script>
  AOS.init({ 
    duration: 800, 
    easing: 'ease-out-back', 
    once: true, 
    offset: 50,
    disable: window.innerWidth < 768
  });
  
  new Typed('#typed-text', {
    strings: ["just memories...", "kept within frames...", "pieces of me...", "digital nostalgia..."],
    typeSpeed: 80, 
    backSpeed: 50, 
    loop: true, 
    cursorChar: '_', 
    showCursor: true
  });

  document.addEventListener('DOMContentLoaded', () => {
    animateValue('totalVisitors', 0, <?= (int)$totalVisitors; ?>, 1500);
    animateValue('onlineVisitors', 0, <?= (int)$onlineVisitors; ?>, 1000);

    // init semua swiper album
    document.querySelectorAll('[class*="main-swiper-"]').forEach((mainEl) => {
      const slides = mainEl.querySelectorAll('.swiper-slide').length;
      const paginationEl = mainEl.querySelector('.swiper-pagination');
      const nextEl = mainEl.querySelector('.swiper-button-next');
      const prevEl = mainEl.querySelector('.swiper-button-prev');

      new Swiper(mainEl, {
        loop: slides > 1,
        spaceBetween: 10,
        keyboard: { enabled: true },
        pagination: { el: paginationEl, clickable: true },
        navigation: { nextEl, prevEl }
      });
    });
  });

  function likeAlbum(aid, ev){
    if (ev) { ev.preventDefault(); ev.stopPropagation(); }

    const key = 'liked-album-' + aid;
    if (localStorage.getItem(key)) {
      Swal.fire({
        title: 'Already liked',
        text: 'Kamu sudah mengapresiasi album ini.',
        icon: 'info',
        confirmButtonText: 'OK',
        background: '#fff',
        customClass: { popup: 'pixel-border' }
      });
      return;
    }

    fetch('like_album.php', {
      method: 'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded'},
      body: 'album_id=' + encodeURIComponent(aid)
    })
    .then(r => r.text())
    .then(txt => {
      const el = document.getElementById('likes-' + aid);
      if (el) el.textContent = txt || '0';
      localStorage.setItem(key, '1');
      el?.animate([{transform:'scale(1)'},{transform:'scale(1.25)'},{transform:'scale(1)'}], {duration:300});
    })
    .catch(() => {
      Swal.fire({
        title:'Oops',
        text:'Gagal menyimpan like.',
        icon:'error',
        background:'#fff',
        customClass: { popup: 'pixel-border' }
      });
    });
  }

  const music = document.getElementById('backgroundMusic');
  const musicBtn = document.getElementById('toggleMusic');
  const musicIcon = document.getElementById('musicIcon');
  
  musicBtn.addEventListener('click',()=>{ 
    if(music.paused){ 
      music.play().then(()=>{ 
        musicIcon.className='fas fa-pause mb-1';
        musicBtn.classList.add('pulse-animation');
      });
    } else { 
      music.pause(); 
      musicIcon.className='fas fa-play mb-1';
      musicBtn.classList.remove('pulse-animation');
    } 
  });

  // simpan state musik saat klik album
  (function attachAlbumLinkHandler(){
    const musicEl = document.getElementById('backgroundMusic');
    document.querySelectorAll('a.album-link').forEach(a => {
      a.addEventListener('click', () => {
        try {
          const state = {
            t: musicEl?.currentTime || 0,
            playing: !!(musicEl && !musicEl.paused)
          };
          sessionStorage.setItem('kept_music_state', JSON.stringify(state));
        } catch(e){}
      }, {capture:true});
    });
  })();

  // restore state musik
  (function restoreMusicOnLoad(){
    const musicEl = document.getElementById('backgroundMusic');
    const icon  = document.getElementById('musicIcon');
    const btn = document.getElementById('toggleMusic');
    if (!musicEl) return;

    try {
      const raw = sessionStorage.getItem('kept_music_state');
      if (!raw) return;
      const state = JSON.parse(raw || '{}');
      if (typeof state.t === 'number') {
        musicEl.currentTime = Math.max(0, state.t - 0.15);
      }
      if (state.playing) {
        musicEl.play().then(() => {
          if (icon) icon.className = 'fas fa-pause mb-1';
          if (btn) btn.classList.add('pulse-animation');
        }).catch(() => {
          const resumeOnce = () => {
            musicEl.play().then(() => {
              if (icon) icon.className = 'fas fa-pause mb-1';
              if (btn) btn.classList.add('pulse-animation');
            }).finally(() => {
              document.removeEventListener('click', resumeOnce);
              document.removeEventListener('keydown', resumeOnce);
            });
          };
          document.addEventListener('click', resumeOnce, {once:true});
          document.addEventListener('keydown', resumeOnce, {once:true});
        });
      }
    } catch(e){}
  })();

  function animateValue(id,start,end,duration){
    const obj = document.getElementById(id); 
    let startTimestamp = null;
    const step = (ts) => {
      if (!startTimestamp) startTimestamp = ts;
      const progress = Math.min((ts - startTimestamp) / duration, 1);
      obj.innerHTML = Math.floor(progress * (end - start) + start);
      if (progress < 1) window.requestAnimationFrame(step);
    };
    window.requestAnimationFrame(step);
  }

  function updateClock(){
    const now = new Date();
    document.getElementById('clock').textContent = now.toLocaleTimeString('en-US',{
      hour12:false,hour:'2-digit',minute:'2-digit',second:'2-digit'
    });
  }
  setInterval(updateClock,1000); updateClock();
</script>

</body>
</html>