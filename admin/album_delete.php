<?php
session_start();
if (!isset($_SESSION['login'])) {
  header("Location: login.php");
  exit;
}

require_once '../db.php';

$album_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($album_id <= 0) {
  header("Location: dashboard.php");
  exit;
}

// Cek apakah album ada
$stmt = $conn->prepare("SELECT id, title FROM albums WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $album_id);
$stmt->execute();
$res = $stmt->get_result();
$album = $res->fetch_assoc();
$stmt->close();

if (!$album) {
  header("Location: dashboard.php");
  exit;
}

// Ambil semua foto di album
$photos = [];
$stmt = $conn->prepare("SELECT id, filename FROM photos WHERE album_id = ?");
$stmt->bind_param("i", $album_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
  $photos[] = $row;
}
$stmt->close();

// --- Hapus album ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Hapus file foto di folder uploads
  $uploadDir = realpath(__DIR__ . '/../uploads');
  foreach ($photos as $p) {
    $file = $uploadDir . DIRECTORY_SEPARATOR . $p['filename'];
    if (is_file($file)) @unlink($file);
  }

  // Hapus record foto
  $conn->query("DELETE FROM photos WHERE album_id = $album_id");
  // Hapus record album
  $conn->query("DELETE FROM albums WHERE id = $album_id");

  header("Location: dashboard.php");
  exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Delete Album - Pixel Admin</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&family=Short+Stack&display=swap" rel="stylesheet">

  <style>
    :root { --pink:#ff9bb3; --purple:#b5a1ff; --yellow:#ffe08a; }
    body { font-family:'Short Stack', cursive; background-color:#fff5f7; image-rendering:pixelated; }
    .pixel-border{ border:4px solid #000; box-shadow:8px 8px 0 rgba(0,0,0,.2); position:relative; }
    .pixel-border:before{ content:''; position:absolute; inset:2px; border:2px solid #fff; pointer-events:none; }
    .title-font{ font-family:'Press Start 2P', cursive; text-shadow:3px 3px 0 var(--purple); }
    .cute-btn{ background:var(--pink); color:#fff; border:3px solid #000; padding:10px 20px; font-size:.8rem; box-shadow:5px 5px 0 rgba(0,0,0,.2); transition:.1s; font-family:'Press Start 2P', cursive; text-shadow:2px 2px 0 rgba(0,0,0,.2); }
    .cute-btn:hover{ transform:translate(2px,2px); box-shadow:3px 3px 0 rgba(0,0,0,.2); }
    .pixel-cloud{ position:absolute; background:#fff; border:3px solid #000; border-radius:50%; }
    .pixel-star{ position:absolute; color:var(--yellow); text-shadow:2px 2px 0 rgba(0,0,0,.2); animation:twinkle 2s infinite alternate; }
    @keyframes twinkle{ from{opacity:.6; transform:scale(1);} to{opacity:1; transform:scale(1.2);} }
  </style>
</head>
<body class="min-h-screen flex items-center justify-center p-6" style="background: linear-gradient(to bottom right, var(--pink), var(--purple));">

<div class="pixel-cloud" style="width:80px; height:40px; top:5%; left:5%;"></div>
<div class="pixel-cloud" style="width:60px; height:30px; top:10%; right:10%;"></div>
<div class="pixel-star" style="top:15%; left:15%;">✦</div>
<div class="pixel-star" style="top:20%; right:20%;">✦</div>

<div class="pixel-border bg-white p-8 max-w-md mx-auto text-center relative z-10">
  <h1 class="title-font text-xl mb-6">DELETE ALBUM</h1>
  <p class="mb-4 text-gray-700">
    Are you sure you want to delete<br>
    <strong><?= htmlspecialchars($album['title'] ?: 'Untitled Album', ENT_QUOTES, 'UTF-8'); ?></strong>?
  </p>

  <?php if ($photos): ?>
    <p class="text-sm text-gray-500 mb-4">This album contains <b><?= count($photos) ?></b> photo(s) which will also be deleted.</p>
  <?php else: ?>
    <p class="text-sm text-gray-500 mb-4">This album has no photos.</p>
  <?php endif; ?>

  <form method="POST" id="deleteForm">
    <div class="flex justify-center gap-4">
      <button type="button" onclick="confirmDelete()" class="cute-btn"><i class="fas fa-trash mr-2"></i> DELETE</button>
      <a href="dashboard.php" class="cute-btn" style="background: var(--purple);"><i class="fas fa-times mr-2"></i> CANCEL</a>
    </div>
  </form>
</div>

<script>
function confirmDelete() {
  Swal.fire({
    title: 'DELETE THIS ALBUM?',
    text: 'All photos in this album will be permanently removed!',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#ff9bb3',
    cancelButtonColor: '#b5a1ff',
    confirmButtonText: 'YES, DELETE',
    cancelButtonText: 'CANCEL',
    background: '#fff',
    customClass: { title: 'title-font' }
  }).then((result) => {
    if (result.isConfirmed) {
      document.getElementById('deleteForm').submit();
    }
  });
}
</script>

</body>
</html>
