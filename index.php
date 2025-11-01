<?php
require_once __DIR__ . '/db.php'; // expects $conn (mysqli)

// ---------- Helpers ----------
function getUserIP(): string {
  if (!empty($_SERVER['HTTP_CLIENT_IP'])) return $_SERVER['HTTP_CLIENT_IP'];
  if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) { $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']); return trim($parts[0]); }
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

// ambil lagu aktif
$bgMusicFile  = '';
$bgMusicTitle = '';
if ($st=$conn->prepare("SELECT v FROM settings WHERE k='bg_music' LIMIT 1")){
  $st->execute(); $r=$st->get_result(); if($r && $row=$r->fetch_assoc()) $bgMusicFile=$row['v'] ?? ''; $st->close();
}
if ($st=$conn->prepare("SELECT v FROM settings WHERE k='bg_music_title' LIMIT 1")){
  $st->execute(); $r=$st->get_result(); if($r && $row=$r->fetch_assoc()) $bgMusicTitle=$row['v'] ?? ''; $st->close();
}
// path final (fallback ke Space Song.mp3 jika kosong)
$musicSrc = $bgMusicFile ? ('music/'.rawurlencode($bgMusicFile)) : 'music/Space Song.mp3';

$totalVisitors = 0; $onlineVisitors = 0;
if ($res = $conn->query('SELECT COUNT(*) AS total FROM visitors')) {
  $row = $res->fetch_assoc(); $totalVisitors = (int)($row['total'] ?? 0); $res->free();
}
if ($stmt = $conn->prepare('SELECT COUNT(*) AS online FROM visitors WHERE visited_at >= ?')) {
  $limit = date('Y-m-d H:i:s', strtotime('-5 minutes'));
  $stmt->bind_param('s', $limit);
  $stmt->execute(); $r = $stmt->get_result();
  if ($r) { $row = $r->fetch_assoc(); $onlineVisitors = (int)($row['online'] ?? 0); }
  $stmt->close();
}

// ---------- Albums + Photos ----------
$albums = [];
if ($res = $conn->query("SELECT id, title, description, created_at, likes FROM albums ORDER BY created_at DESC")) {
  while ($a = $res->fetch_assoc()) { $a['photos'] = []; $albums[(int)$a['id']] = $a; }
  $res->free();
}
if ($albums) {
  $ids = implode(',', array_map('intval', array_keys($albums)));
  if ($res = $conn->query("SELECT id, filename, description, album_id FROM photos WHERE album_id IN ($ids) ORDER BY id ASC")) {
    while ($p = $res->fetch_assoc()) { $aid = (int)$p['album_id']; if (isset($albums[$aid])) $albums[$aid]['photos'][] = $p; }
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

    /* Swiper controls in pixel style */
    .swiper { --swiper-theme-color:#000; }
    .swiper-button-next, .swiper-button-prev {
      width:34px; height:34px; background:#fff; border:3px solid #000; border-radius:6px; box-shadow:4px 4px 0 rgba(0,0,0,.15);
    }
    .swiper-button-next:after, .swiper-button-prev:after { font-size:14px; color:#000; }
    .swiper-pagination-bullet { background:#000; opacity:.25; }
    .swiper-pagination-bullet-active { background:#000; opacity:1; }

    /* Image hover */
    .card-img { transition: transform .5s ease; }
    .memory-card:hover .card-img { transform: scale(1.04); }

    /* Top meta bar on cards */
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

    /* Two-line clamp for description */
    .line-clamp-2{ display:-webkit-box; -webkit-box-orient:vertical; -webkit-line-clamp:2; overflow:hidden; }

    /* Decorations */
    .pixel-cloud { position:absolute; background:#fff; border:3px solid #000; border-radius:50%; }
    .pixel-star { position:absolute; color:var(--yellow); text-shadow:2px 2px 0 rgba(0,0,0,.2); animation: twinkle 2s infinite alternate; }
    @keyframes twinkle { from{opacity:.6; transform:scale(1);} to{opacity:1; transform:scale(1.2);} }

    /* Footer chip clock */
    .chip {
      display:inline-flex; align-items:center; gap:10px; padding:10px 16px; border:3px solid #000; background:#fff;
      box-shadow:5px 5px 0 rgba(0,0,0,.15);
    }
    a.link-plain { text-decoration:none; color:#000; }
    a.link-plain:hover { text-decoration: underline; text-decoration-style: dotted; }
  </style>
</head>
<body class="overflow-x-hidden">

<!-- Decorative elements -->
<div class="pixel-cloud" style="width:80px; height:40px; top:5%; left:5%;"></div>
<div class="pixel-cloud" style="width:60px; height:30px; top:10%; right:10%;"></div>
<div class="pixel-star" style="top:15%; left:15%;">✦</div>
<div class="pixel-star" style="top:20%; right:20%;">✦</div>

<header class="text-center py-16">
  <div class="max-w-4xl mx-auto px-6">
    <h1 class="text-3xl md:text-4xl title-font mb-6">Kept.</h1>
    <div class="w-24 h-2 mx-auto mb-6" style="background: repeating-linear-gradient(90deg,#000,#000 12px,transparent 12px,transparent 24px);"></div>
    <p class="text-base md:text-lg mb-8 italic">
      <span id="typed-text"></span>
    </p>
    <div class="flex flex-col sm:flex-row gap-4 justify-center items-center mb-10">
      <a href="#gallery" class="cute-btn link-plain"><i class="fas fa-images mr-2"></i> EXPLORE</a>
      <a href="admin/login.php" class="cute-btn link-plain" style="background:var(--purple);"><i class="fas fa-lock mr-2"></i> ADMIN PANEL</a>
    </div>

    <!-- Visitors -->
    <div class="flex justify-center">
      <div class="pixel-border-thick px-6 py-4">
        <div class="flex flex-wrap gap-8 justify-center items-center">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 pixel-border flex items-center justify-center"><i class="fas fa-users"></i></div>
            <div>
              <div class="text-xs">VISITOR</div>
              <span id="totalVisitors" class="font-semibold text-lg"><?php echo (int)$totalVisitors; ?></span>
            </div>
          </div>
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 pixel-border flex items-center justify-center"><i class="fas fa-signal"></i></div>
            <div>
              <div class="text-xs">ONLINE</div>
              <span id="onlineVisitors" class="font-semibold text-lg"><?php echo (int)$onlineVisitors; ?></span>
            </div>
          </div>
        </div>
      </div>
    </div>

  </div>
</header>

<main id="gallery" class="max-w-6xl mx-auto px-4 sm:px-6 pb-16">
  <div class="text-center mb-12">
    <h2 class="text-xl title-font" style="text-shadow:3px 3px 0 #fff;">Kept Moments</h2>
    <p class="mt-2 text-sm italic">Little memories I didn't want to forget.</p>
  </div>

  <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6">
    <?php if ($albums): foreach ($albums as $album): $aid=(int)$album['id']; $photos=$album['photos']; if(!$photos) continue; ?>
      <!-- Tambahkan class album-link untuk penyimpanan state musik -->
      <a href="post.php?id=<?= $aid ?>" class="album-link block memory-card pixel-card group relative overflow-hidden" data-aos="fade-up" aria-label="Open album">
        <div class="relative overflow-hidden">
          <!-- TOP META BAR (title, date, photo count, like) -->
          <div class="card-topbar">
            <span class="card-chip card-title">
              <?= htmlspecialchars($album['title'] ?: 'Untitled', ENT_QUOTES, 'UTF-8') ?>
            </span>
            <span class="card-chip">
              <?= date('M j, Y', strtotime($album['created_at'])) ?>
            </span>
            <span class="card-chip">
              <i class="fa-regular fa-image mr-1"></i><?= count($photos) ?> photos
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

          <!-- MAIN SLIDER -->
          <div class="swiper main-swiper-<?= $aid ?>" style="width:100%; aspect-ratio:1/1; border-radius:10px;">
            <div class="swiper-wrapper">
              <?php foreach ($photos as $p):
                $src = 'uploads/'.rawurlencode($p['filename']);
                $alt = htmlspecialchars($p['description'] ?: ($album['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
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

        <!-- Description (singkat) -->
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
        <div class="mx-auto w-16 h-16 pixel-border rounded-full flex items-center justify-center mb-4">
          <i class="fas fa-camera text-xl"></i>
        </div>
        <h3 class="text-lg font-medium mb-2">Empty Archive</h3>
        <p class="opacity-70 max-w-md mx-auto italic">No memories captured yet...</p>
      </div>
    <?php endif; ?>
  </div>
</main>

<!-- Music Button (tetap) -->
<div class="fixed bottom-6 right-6 z-50">
  <button id="toggleMusic" class="cute-btn" style="width:60px;height:60px;border-radius:10px;display:flex;align-items:center;justify-content:center;">
    <i id="musicIcon" class="fas fa-play"></i>
  </button>
</div>

<audio id="backgroundMusic" loop preload="metadata">
  <source src="<?= htmlspecialchars($musicSrc, ENT_QUOTES, 'UTF-8') ?>" type="audio/mpeg">
</audio>

<footer class="px-6 pb-14">
  <div class="max-w-4xl mx-auto text-center">
    <div class="py-4">
      <div class="chip">
        <i class="fas fa-clock"></i>
        <span id="clock" class="font-mono">00:00:00</span>
        <span>WIB</span>
      </div>
    </div>
    <div class="py-6 my-4">
      <p class="italic text-base">"What was once a moment, now a memory."</p>
      <p class="text-sm mt-2 opacity-70">— Kept.</p>
    </div>
    <div><p class="text-sm opacity-70">© 2025 Kept. All memories preserved.</p></div>

    <div class="mt-6 flex justify-center gap-6">
      <a href="https://www.instagram.com/n4ve.666/" class="pixel-border w-12 h-12 flex items-center justify-center link-plain" style="background:#fff;">
        <i class="fab fa-instagram"></i>
      </a>
      <a href="https://github.com/newbiema" class="pixel-border w-12 h-12 flex items-center justify-center link-plain" style="background:#fff;">
        <i class="fab fa-github"></i>
      </a>
      <a href="mailto:evanjamaq123@gmail.com" class="pixel-border w-12 h-12 flex items-center justify-center link-plain" style="background:#fff;">
        <i class="fas fa-envelope"></i>
      </a>
    </div>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
<script>
  AOS.init({ duration: 800, easing: 'ease-out-back', once: true, offset: 50 });
  new Typed('#typed-text', {
    strings: ["just memories...", "kept within frames...", "pieces of me..."],
    typeSpeed: 80, backSpeed: 50, loop: true, cursorChar: '_', showCursor: true
  });

  document.addEventListener('DOMContentLoaded', () => {
    animateValue('totalVisitors', 0, <?php echo (int)$totalVisitors; ?>, 1500);
    animateValue('onlineVisitors', 0, <?php echo (int)$onlineVisitors; ?>, 1000);

    // Init setiap Swiper pakai element langsung (akurasi selector)
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

  // ---------- LIKE: cegah pindah halaman ----------
  function likeAlbum(aid, ev){
    if (ev) { ev.preventDefault(); ev.stopPropagation(); }

    const key = 'liked-album-' + aid;
    if (localStorage.getItem(key)) {
      Swal.fire({
        title: 'Already liked',
        text: 'Kamu sudah mengapresiasi album ini.',
        icon: 'info',
        confirmButtonText: 'OK',
        background: '#fff'
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
      Swal.fire({title:'Oops',text:'Gagal menyimpan like.',icon:'error',background:'#fff'});
    });
  }

  // ---------- Music toggle ----------
  const music=document.getElementById('backgroundMusic');
  const musicBtn=document.getElementById('toggleMusic');
  const musicIcon=document.getElementById('musicIcon');
  musicBtn.addEventListener('click',()=>{ 
    if(music.paused){ 
      music.play().then(()=>{ musicIcon.className='fas fa-pause'; });
    } else { 
      music.pause(); musicIcon.className='fas fa-play'; 
    } 
  });

  // ---------- Simpan state music sebelum navigasi ----------
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

  // ---------- Restore music state saat halaman dimuat ----------
  (function restoreMusicOnLoad(){
    const musicEl = document.getElementById('backgroundMusic');
    const icon  = document.getElementById('musicIcon');
    if (!musicEl) return;

    try {
      const raw = sessionStorage.getItem('kept_music_state');
      if (!raw) return;
      const state = JSON.parse(raw || '{}');
      if (typeof state.t === 'number') {
        musicEl.currentTime = Math.max(0, state.t - 0.15); // sedikit mundur biar halus
      }
      if (state.playing) {
        musicEl.play().then(() => {
          if (icon) icon.className = 'fas fa-pause';
        }).catch(() => {
          // Autoplay mungkin diblokir — lanjut setelah interaksi pertama
          const resumeOnce = () => {
            musicEl.play().then(() => {
              if (icon) icon.className = 'fas fa-pause';
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

  // ---------- Clock & counters ----------
  function animateValue(id,start,end,duration){
    const obj=document.getElementById(id); let startTimestamp=null;
    const step=(ts)=>{ if(!startTimestamp) startTimestamp=ts; const progress=Math.min((ts-startTimestamp)/duration,1);
      obj.innerHTML=Math.floor(progress*(end-start)+start); if(progress<1){ window.requestAnimationFrame(step);} };
    window.requestAnimationFrame(step);
  }
  function updateClock(){
    const now=new Date();
    document.getElementById('clock').textContent= now.toLocaleTimeString('en-US',{hour12:false,hour:'2-digit',minute:'2-digit',second:'2-digit'});
  }
  setInterval(updateClock,1000); updateClock();
</script>

</body>
</html>
