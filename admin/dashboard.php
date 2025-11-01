<?php
session_start();
if (!isset($_SESSION['login'])) {
    header("Location: login.php");
    exit;
}
include '../db.php';

// ------- Stats -------
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

// ------- Albums + cover + count -------
$albums = [];
$sqlAlbums = "
  SELECT 
    a.id, a.title, a.description, a.created_at,
    (SELECT filename FROM photos WHERE album_id = a.id ORDER BY id ASC LIMIT 1) AS cover,
    (SELECT COUNT(*) FROM photos WHERE album_id = a.id) AS photo_count
  FROM albums a
  ORDER BY a.created_at DESC
";
if ($res = $conn->query($sqlAlbums)) {
  while ($row = $res->fetch_assoc()) { $albums[] = $row; }
  $res->free();
}

// ------- Photos list with album -------
$photos = [];
$sqlPhotos = "
  SELECT p.id, p.filename, p.description, p.created_at, p.album_id, 
         COALESCE(a.title,'Untitled') AS album_title
  FROM photos p
  LEFT JOIN albums a ON a.id = p.album_id
  ORDER BY p.created_at DESC
";
if ($res = $conn->query($sqlPhotos)) {
  while ($row = $res->fetch_assoc()) { $photos[] = $row; }
  $res->free();
}
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
    .pixel-border{ border:4px solid #000; box-shadow:8px 8px 0 rgba(0,0,0,.2); position:relative; }
    .pixel-border:before{ content:''; position:absolute; top:2px; left:2px; right:2px; bottom:2px; border:2px solid #fff; pointer-events:none; }
    .cute-btn{ background:var(--pink); color:#fff; border:3px solid #000; padding:10px 20px; font-size:.9rem; box-shadow:5px 5px 0 rgba(0,0,0,.2); transition:.1s; font-family:'Press Start 2P', cursive; text-shadow:2px 2px 0 rgba(0,0,0,.2); }
    .cute-btn:hover{ transform:translate(2px,2px); box-shadow:3px 3px 0 rgba(0,0,0,.2); }
    .title-font{ font-family:'Press Start 2P', cursive; text-shadow:3px 3px 0 var(--purple); }
    .pixel-card{ background:#fff; border:4px solid #000; box-shadow:6px 6px 0 rgba(0,0,0,.1); }
    .pixel-stat{ background:#fff; border:3px solid #000; box-shadow:5px 5px 0 rgba(0,0,0,.1); }
    .pixel-icon{ width:40px; height:40px; border:3px solid #000; display:flex; align-items:center; justify-content:center; }
    .pixel-table{ border-collapse:separate; border-spacing:0; }
    .pixel-table th{ background:var(--pink); color:#fff; border:3px solid #000; border-bottom:none; }
    .pixel-table td{ border:3px solid #000; border-top:none; background:#fff; }
    .pixel-table tr:hover td{ background:#ffebf0; }
    .grid-albums .card-img{ transition: transform .5s ease; }
    .grid-albums .album-card:hover .card-img{ transform: scale(1.05); }
    .badge{ background:rgba(0,0,0,.55); color:#fff; border:1px solid #000; padding:2px 8px; border-radius:8px; font-size:.7rem; }
    .line-clamp-2{ display:-webkit-box; -webkit-box-orient:vertical; -webkit-line-clamp:2; overflow:hidden; }
    .pixel-lightbox{ border:5px solid #000; box-shadow:10px 10px 0 rgba(0,0,0,.3); }
  </style>
</head>
<body class="min-h-screen p-6" style="background: linear-gradient(to bottom right, var(--pink), var(--purple));">

<!-- Decor -->
<div class="pixel-border" style="display:none"></div>

<div class="max-w-7xl mx-auto relative z-10">
  <header class="flex flex-wrap items-center justify-between mb-8 gap-4">
    <h1 class="text-3xl sm:text-4xl title-font text-white">ADMIN DASHBOARD</h1>
    <div class="flex flex-wrap gap-3">
      <a href="upload.php" class="cute-btn"><i class="fas fa-plus mr-2"></i> UPLOAD</a>
      <a href="../index.php" class="cute-btn" style="background: var(--purple);"><i class="fas fa-globe mr-2"></i> VIEW SITE</a>
    </div>
  </header>

  <!-- Stats -->
  <section class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6 mb-10">
    <div class="pixel-stat p-6 flex items-center justify-between">
      <div>
        <p class="text-gray-600 text-sm">TOTAL ALBUMS</p>
        <h2 class="text-2xl font-bold" style="color: var(--purple);"><?php echo $totalAlbums; ?></h2>
      </div>
      <div class="pixel-icon" style="background: var(--purple);"><i class="fas fa-folder-open text-white"></i></div>
    </div>
    <div class="pixel-stat p-6 flex items-center justify-between">
      <div>
        <p class="text-gray-600 text-sm">TOTAL PHOTOS</p>
        <h2 class="text-2xl font-bold" style="color: var(--pink);"><?php echo $totalPhotos; ?></h2>
      </div>
      <div class="pixel-icon" style="background: var(--pink);"><i class="fas fa-camera text-white"></i></div>
    </div>
    <div class="pixel-stat p-6 flex items-center justify-between">
      <div>
        <p class="text-gray-600 text-sm">AVG/ALBUM</p>
        <h2 class="text-2xl font-bold" style="color: var(--blue);">
          <?php echo $totalAlbums ? ceil($totalPhotos / max(1,$totalAlbums)) : 0; ?>
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
          $aid = (int)$a['id'];
          $cover = $a['cover'] ? '../uploads/'.rawurlencode($a['cover']) : 'https://via.placeholder.com/600x600?text=No+Cover';
          $title = htmlspecialchars($a['title'] ?: 'Untitled', ENT_QUOTES, 'UTF-8');
          $desc  = htmlspecialchars($a['description'] ?? '', ENT_QUOTES, 'UTF-8');
          $date  = date('M j, Y', strtotime($a['created_at']));
          $count = (int)$a['photo_count'];
        ?>
        <div class="album-card border-4 border-black bg-white shadow p-3">
          <div class="relative overflow-hidden rounded" style="aspect-ratio:1/1;">
            <img src="<?php echo $cover; ?>" alt="<?php echo $title; ?>" class="w-full h-full object-cover card-img">
            <div class="absolute top-2 left-2 badge"><i class="fa-regular fa-image mr-1"></i><?php echo $count; ?> photos</div>
            <a href="../post.php?id=<?php echo $aid; ?>" class="absolute inset-0" aria-label="Open album"></a>
          </div>
          <div class="pt-3">
            <div class="flex items-start justify-between gap-2">
              <h3 class="font-semibold leading-tight">
                <a href="../post.php?id=<?php echo $aid; ?>" class="hover:underline">
                  <?php echo $title; ?>
                </a>
              </h3>
              <span class="badge"><?php echo $date; ?></span>
            </div>
            <p class="text-gray-600 text-sm line-clamp-2 mt-1"><?php echo $desc ?: '<span class="italic text-gray-400">no description…</span>'; ?></p>
            <div class="mt-3 flex items-center gap-2">
              <a href="../post.php?id=<?php echo $aid; ?>" class="text-xs px-3 py-2 border-2 border-black bg-white hover:bg-black hover:text-white transition">View</a>
              <a href="album_edit.php?id=<?php echo $aid; ?>" class="text-xs px-3 py-2 border-2 border-black bg-white hover:bg-black hover:text-white transition">Edit</a>
              <button onclick="confirmDeleteAlbum(<?php echo $aid; ?>)" class="text-xs px-3 py-2 border-2 border-black bg-white hover:bg-red-600 hover:text-white transition">Delete</button>
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
    <div class="overflow-x-auto">
      <table class="pixel-table w-full rounded-lg overflow-hidden">
        <thead>
          <tr>
            <th class="px-4 py-3 text-left w-12">#</th>
            <th class="px-4 py-3 text-left w-24">PHOTO</th>
            <th class="px-4 py-3 text-left min-w-[220px]">DESCRIPTION</th>
            <th class="px-4 py-3 text-left min-w-[160px]">ALBUM</th>
            <th class="px-4 py-3 text-left w-32">DATE</th>
            <th class="px-4 py-3 text-left w-28">ACTIONS</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$photos): ?>
            <tr><td colspan="6" class="px-4 py-6 text-center italic text-gray-600">No photos yet.</td></tr>
          <?php else: $no=1; foreach($photos as $row): ?>
            <tr class="hover:bg-pink-50 transition-colors">
              <td class="px-4 py-3 font-mono"><?php echo $no++; ?></td>
              <td class="px-4 py-3">
                <img src="../uploads/<?php echo htmlspecialchars($row['filename']); ?>"
                     onclick="openLightbox(this.src)"
                     class="h-16 w-16 object-cover border-2 border-black cursor-pointer hover:scale-110 transition mx-auto">
              </td>
              <td class="px-4 py-3 max-w-[320px] truncate" title="<?php echo htmlspecialchars($row['description']); ?>">
                <?php echo htmlspecialchars($row['description']); ?>
              </td>
              <td class="px-4 py-3">
                <a href="../post.php?id=<?php echo (int)$row['album_id']; ?>" class="underline">
                  <?php echo htmlspecialchars($row['album_title']); ?>
                </a>
              </td>
              <td class="px-4 py-3 font-mono text-sm">
                <?php echo date("d M Y", strtotime($row['created_at'])); ?>
              </td>
              <td class="px-4 py-3">
                <div class="flex items-center justify-center gap-3">
                  <a href="edit.php?id=<?php echo (int)$row['id']; ?>" title="Edit" class="text-blue-600 hover:text-blue-800 text-lg transition-colors">
                    <i class="fas fa-pen-to-square"></i>
                  </a>
                  <button onclick="confirmDeletePhoto(<?php echo (int)$row['id']; ?>)" title="Delete" class="text-red-600 hover:text-red-800 text-lg transition-colors">
                    <i class="fas fa-trash-alt"></i>
                  </button>
                </div>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</div>

<!-- Lightbox -->
<div id="lightboxModal" class="fixed inset-0 bg-black bg-opacity-90 flex items-center justify-center z-50 hidden">
  <span onclick="closeLightbox()" class="absolute top-5 right-5 text-white text-3xl cursor-pointer hover:text-pink-400">&times;</span>
  <img id="lightboxImg" src="" class="pixel-lightbox max-w-3xl max-h-[80vh]">
</div>

<script>
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
