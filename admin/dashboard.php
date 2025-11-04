<?php
session_start();
if (!isset($_SESSION['login'])) {
  header("Location: login.php");
  exit;
}
require_once __DIR__ . '/../db.php'; // $conn (mysqli)

/* ----------------------- Helpers ----------------------- */
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function qs_set(array $overrides): string {
  $q = array_merge($_GET, $overrides);
  return '?'.http_build_query($q);
}

/* ----------------------- Stats ------------------------- */
$totalAlbums = 0;
$totalPhotos = 0;

if ($res = $conn->query("SELECT COUNT(*) AS c FROM albums")) {
  $totalAlbums = (int)($res->fetch_assoc()['c'] ?? 0); 
  $res->free();
}
if ($res = $conn->query("SELECT COUNT(*) AS c FROM photos")) {
  $totalPhotos = (int)($res->fetch_assoc()['c'] ?? 0); 
  $res->free();
}

/* ----------------------- Albums ------------------------ */
/*
   cover sekarang diambil dari foto dengan position paling kecil
   lalu fallback ke id ASC
*/
$albums = [];
$sqlAlbums = "
  SELECT 
    a.id, a.title, a.description, a.created_at,
    (
      SELECT filename 
      FROM photos 
      WHERE album_id = a.id 
      ORDER BY position ASC, id ASC 
      LIMIT 1
    ) AS cover,
    (SELECT COUNT(*) FROM photos WHERE album_id = a.id) AS photo_count
  FROM albums a
  ORDER BY a.created_at DESC
";
if ($res = $conn->query($sqlAlbums)) {
  while ($row = $res->fetch_assoc()) $albums[] = $row;
  $res->free();
}

/* --- album list for filter (buat tabel photos) --- */
$albumOptions = [];
$ra = $conn->query("SELECT id, COALESCE(title,'Untitled') AS t FROM albums ORDER BY created_at DESC");
while ($r = $ra->fetch_assoc()) $albumOptions[] = $r;
$ra->free();

/* ---------------- Photos: search + pagination ---------- */
$q       = trim($_GET['q'] ?? '');
$albumId = isset($_GET['album_id']) ? (int)$_GET['album_id'] : 0;
$perPage = max(5, min(100, (int)($_GET['per_page'] ?? 15)));
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

$where = " WHERE 1=1 ";
$params = [];
$types  = "";

if ($q !== "") {
  $where .= " AND (p.description LIKE CONCAT('%', ?, '%') OR a.title LIKE CONCAT('%', ?, '%')) ";
  $params[] = $q; 
  $params[] = $q; 
  $types   .= "ss";
}
if ($albumId > 0) {
  $where .= " AND p.album_id = ? ";
  $params[] = $albumId; 
  $types   .= "i";
}

/* count total rows */
$sqlCount = "SELECT COUNT(*) AS c
             FROM photos p LEFT JOIN albums a ON a.id = p.album_id
             $where";
$st = $conn->prepare($sqlCount);
if ($types) $st->bind_param($types, ...$params);
$st->execute();
$totalRows = (int)($st->get_result()->fetch_assoc()['c'] ?? 0);
$st->close();

/* fetch current page rows */
$sqlPhotos = "SELECT p.id, p.filename, p.description, p.created_at, p.album_id,
                     COALESCE(a.title,'Untitled') AS album_title
              FROM photos p
              LEFT JOIN albums a ON a.id = p.album_id
              $where
              ORDER BY p.created_at DESC
              LIMIT ? OFFSET ?";
$st = $conn->prepare($sqlPhotos);
if ($types) {
  $types2 = $types . "ii";
  $st->bind_param($types2, ...array_merge($params, [$perPage, $offset]));
} else {
  $st->bind_param("ii", $perPage, $offset);
}
$st->execute();
$res = $st->get_result();
$photos = [];
while ($row = $res->fetch_assoc()) $photos[] = $row;
$st->close();

$totalPages = max(1, (int)ceil($totalRows / $perPage));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Pixel Admin Dashboard</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&family=Short+Stack&display=swap" rel="stylesheet">

  <style>
    :root { --pink:#ff9bb3; --purple:#b5a1ff; --blue:#9bd4ff; --yellow:#ffe08a; }
    body { font-family:'Short Stack', cursive; background-color:#fff5f7; image-rendering:pixelated; }
    .title-font{ font-family:'Press Start 2P', cursive; text-shadow:3px 3px 0 var(--purple); }
    .cute-btn{ background:var(--pink); color:#fff; border:3px solid #000; padding:10px 20px; font-size:.9rem; box-shadow:5px 5px 0 rgba(0,0,0,.2); transition:.1s; font-family:'Press Start 2P', cursive; text-shadow:2px 2px 0 rgba(0,0,0,.2); }
    .cute-btn:hover{ transform:translate(2px,2px); box-shadow:3px 3px 0 rgba(0,0,0,.2); }

    .pixel-card{ background:#fff; border:4px solid #000; box-shadow:6px 6px 0 rgba(0,0,0,.1); }
    .pixel-stat{ background:#fff; border:3px solid #000; box-shadow:5px 5px 0 rgba(0,0,0,.1); }
    .pixel-icon{ width:40px; height:40px; border:3px solid #000; display:flex; align-items:center; justify-content:center; }

    .grid-albums .card-img{ transition: transform .5s ease; }
    .grid-albums .album-card:hover .card-img{ transform: scale(1.05); }
    .badge{ background:rgba(0,0,0,.8); color:#fff; padding:2px 8px; border-radius:8px; font-size:.7rem; }

    .pixel-table{ border-collapse:separate; border-spacing:0; }
    .pixel-table th{ background:var(--pink); color:#fff; border:3px solid #000; border-bottom:none; }
    .pixel-table td{ border:3px solid #000; border-top:none; background:#fff; }
    .pixel-table tr:hover td{ background:#ffebf0; }

    .pixel-lightbox{ border:5px solid #000; box-shadow:10px 10px 0 rgba(0,0,0,.3); }

    .inp { border:2px solid #000; background:#fff; padding:.55rem .75rem; }
    .btn-mini { border:2px solid #000; background:#fff; padding:.35rem .6rem; font-size:.8rem; }
    .btn-mini:hover { background:#000; color:#fff; }

    /* Sidebar Styles */
    .sidebar {
      background: linear-gradient(to bottom, var(--pink), var(--purple));
      width: 250px;
      min-height: 100vh;
      position: fixed;
      top: 0;
      left: 0;
      z-index: 1000;
      transition: transform 0.3s ease;
      border-right: 4px solid #000;
    }
    
    .sidebar-content {
      padding: 20px 15px;
      height: 100%;
      display: flex;
      flex-direction: column;
    }
    
    .sidebar-logo {
      font-family: 'Press Start 2P', cursive;
      color: white;
      text-align: center;
      margin-bottom: 30px;
      text-shadow: 3px 3px 0 rgba(0,0,0,.2);
      font-size: 1.2rem;
    }
    
    .sidebar-nav {
      display: flex;
      flex-direction: column;
      gap: 10px;
      flex-grow: 1;
    }
    
    .sidebar-link {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 12px 15px;
      color: white;
      text-decoration: none;
      border-radius: 8px;
      transition: all 0.2s;
      border: 2px solid transparent;
    }
    
    .sidebar-link:hover, .sidebar-link.active {
      background: rgba(255, 255, 255, 0.2);
      border-color: white;
      transform: translate(2px, 2px);
    }
    
    .sidebar-link i {
      width: 20px;
      text-align: center;
    }
    
    .main-content {
      margin-left: 250px;
      padding: 20px;
      transition: margin-left 0.3s ease;
    }
    
    .mobile-header {
      display: none;
      justify-content: space-between;
      align-items: center;
      padding: 15px;
      background: linear-gradient(to right, var(--pink), var(--purple));
      border-bottom: 4px solid #000;
      position: sticky;
      top: 0;
      z-index: 999;
    }
    
    .mobile-menu-btn {
      background: none;
      border: 2px solid #000;
      color: white;
      padding: 8px 12px;
      font-size: 1.2rem;
      cursor: pointer;
    }
    
    .mobile-logo {
      font-family: 'Press Start 2P', cursive;
      color: white;
      text-shadow: 2px 2px 0 rgba(0,0,0,.2);
      font-size: 1rem;
    }
    
    .overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0,0,0,0.5);
      z-index: 999;
      display: none;
    }
    
    /* Responsive */
    @media (max-width: 1024px) {
      .sidebar {
        transform: translateX(-100%);
      }
      
      .sidebar.open {
        transform: translateX(0);
      }
      
      .main-content {
        margin-left: 0;
      }
      
      .mobile-header {
        display: flex;
      }
    }
    
    @media (max-width: 768px) {
      .main-content {
        padding: 15px;
      }
      
      .pixel-card, .pixel-stat {
        padding: 15px;
      }
      
      .grid-albums {
        grid-template-columns: 1fr;
      }
      
      .pixel-table {
        font-size: 0.85rem;
      }
      
      .pixel-table th, .pixel-table td {
        padding: 8px 10px;
      }
    }
    
    @media (max-width: 480px) {
      .stats-grid {
        grid-template-columns: 1fr;
      }
      
      .toolbar-grid {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>
<body class="min-h-screen" style="background: linear-gradient(to bottom right, var(--pink), var(--purple));">

<!-- Mobile Header -->
<div class="mobile-header">
  <button class="mobile-menu-btn" id="menuToggle">
    <i class="fas fa-bars"></i>
  </button>
  <div class="mobile-logo">ADMIN</div>
  <div></div> <!-- Empty div for flex spacing -->
</div>

<!-- Overlay for mobile menu -->
<div class="overlay" id="overlay"></div>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
  <div class="sidebar-content">
    <div class="sidebar-logo">PIXEL ADMIN</div>
    
    <div class="sidebar-nav">
      <a href="#" class="sidebar-link active">
        <i class="fas fa-tachometer-alt"></i>
        <span>Dashboard</span>
      </a>
      <a href="upload.php" class="sidebar-link">
        <i class="fas fa-plus"></i>
        <span>Upload</span>
      </a>
      <a href="music.php" class="sidebar-link">
        <i class="fas fa-music"></i>
        <span>Music</span>
      </a>
      <a href="../index.php" class="sidebar-link">
        <i class="fas fa-globe"></i>
        <span>View Site</span>
      </a>
      
      <div class="mt-auto">
        <a href="logout.php" class="sidebar-link">
          <i class="fas fa-sign-out-alt"></i>
          <span>Logout</span>
        </a>
      </div>
    </div>
  </div>
</div>

<!-- Main Content -->
<div class="main-content" id="mainContent">
  <div class="max-w-7xl mx-auto">
    <!-- Header -->
    <header class="mb-8">
      <h1 class="text-3xl sm:text-4xl title-font text-white">ADMIN DASHBOARD</h1>
    </header>

    <!-- Stats -->
    <section class="stats-grid grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6 mb-10">
      <div class="pixel-stat p-6 flex items-center justify-between">
        <div>
          <p class="text-gray-600 text-sm">TOTAL ALBUMS</p>
          <h2 class="text-2xl font-bold" style="color: var(--purple);"><?= $totalAlbums ?></h2>
        </div>
        <div class="pixel-icon" style="background: var(--purple);"><i class="fas fa-folder-open text-white"></i></div>
      </div>
      <div class="pixel-stat p-6 flex items-center justify-between">
        <div>
          <p class="text-gray-600 text-sm">TOTAL PHOTOS</p>
          <h2 class="text-2xl font-bold" style="color: var(--pink);"><?= $totalPhotos ?></h2>
        </div>
        <div class="pixel-icon" style="background: var(--pink);"><i class="fas fa-camera text-white"></i></div>
      </div>
      <div class="pixel-stat p-6 flex items-center justify-between">
        <div>
          <p class="text-gray-600 text-sm">AVG/ALBUM</p>
          <h2 class="text-2xl font-bold" style="color: var(--blue);">
            <?= $totalAlbums ? ceil($totalPhotos / max(1,$totalAlbums)) : 0; ?>
          </h2>
        </div>
        <div class="pixel-icon" style="background: var(--blue);"><i class="fas fa-chart-simple text-white"></i></div>
      </div>
    </section>

    <!-- Albums Grid -->
    <section class="pixel-card p-6 mb-10">
      <div class="flex items-center justify-between mb-6">
        <h2 class="text-2xl title-font" style="color: var(--purple);">ALBUMS</h2>
        <a href="upload.php" class="cute-btn"><i class="fas fa-folder-plus mr-2"></i> NEW ALBUM</a>
      </div>

      <?php if (!$albums): ?>
        <div class="text-center py-16 text-gray-600 italic">No albums yet. Create one from the Upload page.</div>
      <?php else: ?>
        <div class="grid-albums grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
          <?php foreach ($albums as $a): 
            $aid   = (int)$a['id'];
            $cover = $a['cover'] ? '../uploads/'.rawurlencode($a['cover']) : 'https://via.placeholder.com/600x600?text=No+Cover';
            $title = h($a['title'] ?: 'Untitled');
            $desc  = h($a['description'] ?? '');
            $date  = date('M j, Y', strtotime($a['created_at']));
            $count = (int)$a['photo_count'];
          ?>
          <div class="album-card border-4 border-black bg-white shadow p-3">
            <div class="relative overflow-hidden rounded" style="aspect-ratio:1/1;">
              <img src="<?= $cover; ?>" alt="<?= $title; ?>" loading="lazy" decoding="async" class="w-full h-full object-cover card-img">
              <div class="absolute top-2 left-2 badge">
                <i class="fa-regular fa-image mr-1"></i><?= $count; ?> photos
              </div>
              <!-- tetap kirim from=admin -->
              <a href="../post.php?id=<?= $aid; ?>&from=admin" class="absolute inset-0" aria-label="Open album"></a>
            </div>
            <div class="pt-3">
              <div class="flex items-start justify-between gap-2">
                <h3 class="font-semibold leading-tight">
                  <a href="../post.php?id=<?= $aid; ?>&from=admin" class="hover:underline">
                    <?= $title; ?>
                  </a>
                </h3>
                <span class="badge"><?= $date; ?></span>
              </div>
              <p class="text-gray-600 text-sm line-clamp-2 mt-1">
                <?= $desc ?: '<span class="italic text-gray-400">no description…</span>'; ?>
              </p>
              <div class="mt-3 flex items-center gap-2 text-xs">
                <a href="../post.php?id=<?= $aid; ?>&from=admin" class="px-3 py-2 border-2 border-black bg-white hover:bg-black hover:text-white transition">View</a>
                <a href="album_edit.php?id=<?= $aid; ?>" class="px-3 py-2 border-2 border-black bg-white hover:bg-black hover:text-white transition">Edit</a>
                <button onclick="confirmDeleteAlbum(<?= $aid; ?>)" class="px-3 py-2 border-2 border-black bg-white hover:bg-red-600 hover:text-white transition">Delete</button>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    <!-- Photos Table -->
    <section class="pixel-card p-6">
      <h2 class="text-2xl title-font mb-6" style="color: var(--purple);">PHOTOS</h2>

      <!-- Toolbar: search, filter, per-page -->
      <form class="mb-4 toolbar-grid grid grid-cols-1 sm:grid-cols-4 gap-3" method="get">
        <input type="hidden" name="page" value="1">
        <div class="sm:col-span-2">
          <input type="text" name="q" value="<?= h($q) ?>" placeholder="Search description or album…" class="inp w-full">
        </div>
        <div>
          <select name="album_id" class="inp w-full">
            <option value="0">All Albums</option>
            <?php foreach($albumOptions as $op): ?>
              <option value="<?= (int)$op['id'] ?>" <?= $albumId===(int)$op['id']?'selected':'' ?>>
                <?= h($op['t']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="flex gap-2">
          <select name="per_page" class="inp">
            <?php foreach([10,15,25,50,100] as $n): ?>
              <option value="<?= $n ?>" <?= $perPage===$n?'selected':'' ?>><?= $n ?>/page</option>
            <?php endforeach; ?>
          </select>
          <button class="btn-mini">Apply</button>
        </div>
      </form>

      <div class="overflow-x-auto">
        <table class="pixel-table w-full rounded-lg overflow-hidden">
          <thead>
            <tr>
              <th class="px-4 py-3 text-left w-12">#</th>
              <th class="px-4 py-3 text-left w-20">PHOTO</th>
              <th class="px-4 py-3 text-left min-w-[220px]">DESCRIPTION</th>
              <th class="px-4 py-3 text-left min-w-[160px]">ALBUM</th>
              <th class="px-4 py-3 text-left w-32">DATE</th>
              <th class="px-4 py-3 text-left w-28">ACTIONS</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$photos): ?>
              <tr><td colspan="6" class="px-4 py-6 text-center italic text-gray-600">No photos found.</td></tr>
            <?php else: $no = $offset + 1; foreach($photos as $row): ?>
              <tr class="hover:bg-pink-50 transition-colors">
                <td class="px-4 py-3 font-mono"><?= $no++; ?></td>
                <td class="px-4 py-3">
                  <img src="../uploads/<?= h($row['filename']); ?>"
                       loading="lazy" decoding="async"
                       onclick="openLightbox(this.src)"
                       class="h-14 w-14 object-cover border-2 border-black cursor-pointer hover:scale-110 transition mx-auto">
                </td>
                <td class="px-4 py-3 max-w-[360px] truncate" title="<?= h($row['description']); ?>">
                  <?= h($row['description']); ?>
                </td>
                <td class="px-4 py-3">
                  <a href="../post.php?id=<?= (int)$row['album_id']; ?>&from=admin" class="underline hover:no-underline">
                    <?= h($row['album_title']); ?>
                  </a>
                </td>
                <td class="px-4 py-3 font-mono text-sm">
                  <?= date("d M Y", strtotime($row['created_at'])); ?>
                </td>
                <td class="px-4 py-3">
                  <div class="flex items-center justify-center gap-2">
                    <a href="edit.php?id=<?= (int)$row['id']; ?>" class="btn-mini" title="Edit">Edit</a>
                    <button onclick="confirmDeletePhoto(<?= (int)$row['id']; ?>)" class="btn-mini" title="Delete">Del</button>
                  </div>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <?php if ($totalPages > 1): ?>
        <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
          <div class="text-sm">Showing <?= count($photos) ?> of <?= $totalRows ?> photos</div>
          <div class="flex items-center gap-2">
            <a class="btn-mini <?= $page<=1?'opacity-50 pointer-events-none':'' ?>" href="<?= qs_set(['page'=>max(1,$page-1)]) ?>">Prev</a>
            <span class="btn-mini">Page <?= $page ?> / <?= $totalPages ?></span>
            <a class="btn-mini <?= $page>=$totalPages?'opacity-50 pointer-events-none':'' ?>" href="<?= qs_set(['page'=>min($totalPages,$page+1)]) ?>">Next</a>
          </div>
        </div>
      <?php endif; ?>
    </section>
  </div>
</div>

<!-- Lightbox -->
<div id="lightboxModal" class="fixed inset-0 bg-black bg-opacity-90 flex items-center justify-center z-50 hidden">
  <span onclick="closeLightbox()" class="absolute top-5 right-5 text-white text-3xl cursor-pointer hover:text-pink-400">&times;</span>
  <img id="lightboxImg" src="" class="pixel-lightbox max-w-3xl max-h-[80vh]" alt="preview">
</div>

<script>
// Mobile menu toggle
const menuToggle = document.getElementById('menuToggle');
const sidebar = document.getElementById('sidebar');
const overlay = document.getElementById('overlay');
const mainContent = document.getElementById('mainContent');

menuToggle.addEventListener('click', () => {
  sidebar.classList.toggle('open');
  overlay.style.display = sidebar.classList.contains('open') ? 'block' : 'none';
});

overlay.addEventListener('click', () => {
  sidebar.classList.remove('open');
  overlay.style.display = 'none';
});

// Close sidebar when clicking on a link (for mobile)
document.querySelectorAll('.sidebar-link').forEach(link => {
  link.addEventListener('click', () => {
    if (window.innerWidth <= 1024) {
      sidebar.classList.remove('open');
      overlay.style.display = 'none';
    }
  });
});

function confirmDeletePhoto(id) {
  Swal.fire({
    title: 'DELETE PHOTO?',
    text: "This action cannot be undone!",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#ff9bb3',
    cancelButtonColor: '#b5a1ff',
    confirmButtonText: 'YES, DELETE',
    cancelButtonText: 'CANCEL',
    background: '#fff',
    customClass: { title: 'title-font', confirmButton: 'pixel-border', cancelButton: 'pixel-border' }
  }).then((r)=>{ if(r.isConfirmed){ window.location.href='delete.php?id='+id; } });
}

function confirmDeleteAlbum(id) {
  Swal.fire({
    title: 'DELETE ALBUM?',
    text: "All photos in this album may be affected depending on your delete logic.",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#ff9bb3',
    cancelButtonColor: '#b5a1ff',
    confirmButtonText: 'YES, DELETE',
    cancelButtonText: 'CANCEL',
    background: '#fff',
    customClass: { title: 'title-font', confirmButton: 'pixel-border', cancelButton: 'pixel-border' }
  }).then((r)=>{ if(r.isConfirmed){ window.location.href='album_delete.php?id='+id; } });
}

function openLightbox(src) {
  document.getElementById('lightboxImg').src = src;
  document.getElementById('lightboxModal').classList.remove('hidden');
}
function closeLightbox() {
  document.getElementById('lightboxModal').classList.add('hidden');
}
</script>
</body>
</html>