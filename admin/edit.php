<?php
session_start();
if (!isset($_SESSION['login'])) {
  header("Location: login.php");
  exit;
}
require_once '../db.php'; // mysqli $conn

// ---------- helpers ----------
function humanErrorUpload(int $code): string {
  return match ($code) {
    UPLOAD_ERR_INI_SIZE   => 'Ukuran file melewati upload_max_filesize.',
    UPLOAD_ERR_FORM_SIZE  => 'Ukuran file melewati MAX_FILE_SIZE di form.',
    UPLOAD_ERR_PARTIAL    => 'File terunggah sebagian.',
    UPLOAD_ERR_NO_FILE    => 'Tidak ada file yang diunggah.',
    UPLOAD_ERR_NO_TMP_DIR => 'Folder tmp tidak ada di server.',
    UPLOAD_ERR_CANT_WRITE => 'Gagal menulis ke disk.',
    UPLOAD_ERR_EXTENSION  => 'Diblokir oleh ekstensi.',
    default               => 'Terjadi kesalahan upload.',
  };
}
function mimeToExt($m){ return match($m){ 'image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif', default=>null }; }
function makeUniqueName(string $baseNoExt, string $ext): string {
  $safe = preg_replace('/[^A-Za-z0-9-_]+/', '-', $baseNoExt);
  $safe = trim($safe, '-');
  return $safe . '-' . bin2hex(random_bytes(6)) . '.' . $ext;
}

// ---------- fetch current photo ----------
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { header('Location: dashboard.php'); exit; }

$photo = null;
if ($stmt = $conn->prepare("SELECT id, filename, description, album_id, created_at FROM photos WHERE id = ? LIMIT 1")) {
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $res = $stmt->get_result();
  $photo = $res->fetch_assoc();
  $stmt->close();
}
if (!$photo) { header('Location: dashboard.php'); exit; }

// ---------- albums for dropdown ----------
$albums = [];
$res = $conn->query("SELECT id, COALESCE(title,'Untitled') AS title FROM albums ORDER BY created_at DESC");
while ($row = $res->fetch_assoc()) { $albums[] = $row; }
$res->free();
$current_album_id = (int)$photo['album_id'];

// ---------- handle POST ----------
$error = "";
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $desc = trim($_POST['description'] ?? '');
  $album_id = isset($_POST['album_id']) ? (int)$_POST['album_id'] : $current_album_id;

  // validasi album_id eksis
  $albumExists = false;
  if ($st = $conn->prepare("SELECT 1 FROM albums WHERE id=?")) {
    $st->bind_param("i", $album_id);
    $st->execute();
    $st->store_result();
    $albumExists = $st->num_rows > 0;
    $st->close();
  }
  if (!$albumExists) {
    $error = "Album tidak ditemukan.";
  } else {
    $uploadDir = realpath(__DIR__ . '/../uploads');
    if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);

    $hasNew = isset($_FILES['new_photo']) && $_FILES['new_photo']['error'] !== UPLOAD_ERR_NO_FILE;

    if ($hasNew) {
      $file = $_FILES['new_photo'];

      if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = humanErrorUpload($file['error']);
      } elseif ($file['size'] > 5*1024*1024) { // 5MB
        $error = "Maksimal ukuran 5MB.";
      } else {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']);
        $allowed = ['image/jpeg','image/png','image/webp','image/gif'];
        if (!in_array($mime, $allowed, true)) {
          $error = "Format file tidak didukung (hanya JPG/PNG/WEBP/GIF).";
        } else {
          $ext = mimeToExt($mime) ?? strtolower(pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg');
          $base = pathinfo($file['name'], PATHINFO_FILENAME) ?: 'image';
          try {
            $newName = makeUniqueName($base, $ext);
          } catch (Throwable $e) {
            $newName = time() . '-' . mt_rand(1000,9999) . '.' . $ext;
          }
          $dest = $uploadDir . DIRECTORY_SEPARATOR . $newName;

          if (!move_uploaded_file($file['tmp_name'], $dest)) {
            $error = "Gagal menyimpan file.";
          } else {
            // hapus file lama jika ada
            $old = $uploadDir . DIRECTORY_SEPARATOR . $photo['filename'];
            if (is_file($old)) @unlink($old);

            // update DB (filename + desc + album)
            if ($stmt = $conn->prepare("UPDATE photos SET filename=?, description=?, album_id=? WHERE id=?")) {
              $stmt->bind_param("ssii", $newName, $desc, $album_id, $id);
              $success = $stmt->execute();
              $stmt->close();
            }
          }
        }
      }
    } else {
      // hanya update desc + album
      if ($stmt = $conn->prepare("UPDATE photos SET description=?, album_id=? WHERE id=?")) {
        $stmt->bind_param("sii", $desc, $album_id, $id);
        $success = $stmt->execute();
        $stmt->close();
      }
    }
  }

  if ($success) {
    header("Location: dashboard.php");
    exit;
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Pixel Edit Photo</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&family=Short+Stack&display=swap" rel="stylesheet">
  <style>
    :root { --pink:#ff9bb3; --purple:#b5a1ff; --blue:#9bd4ff; --yellow:#ffe08a; }
    body { font-family:'Short Stack', cursive; background-color:#fff5f7; image-rendering:pixelated; }
    .pixel-border{ border:4px solid #000; box-shadow:8px 8px 0 rgba(0,0,0,.2); position:relative; }
    .pixel-border:before{ content:''; position:absolute; top:2px; left:2px; right:2px; bottom:2px; border:2px solid #fff; pointer-events:none; }
    .cute-btn{ background:var(--pink); color:#fff; border:3px solid #000; padding:8px 16px; font-size:.8rem; box-shadow:5px 5px 0 rgba(0,0,0,.2); transition:.1s; font-family:'Press Start 2P', cursive; text-shadow:2px 2px 0 rgba(0,0,0,.2); }
    .cute-btn:hover{ transform:translate(2px,2px); box-shadow:3px 3px 0 rgba(0,0,0,.2); }
    .title-font{ font-family:'Press Start 2P', cursive; text-shadow:3px 3px 0 var(--purple); }
    .pixel-input{ border:3px solid #000; padding:8px 12px; font-family:'Short Stack', cursive; box-shadow:4px 4px 0 rgba(0,0,0,.1); }
    .pixel-input:focus{ outline:none; box-shadow:4px 4px 0 var(--purple); }
    .pixel-file-input{ border:3px solid #000; padding:8px; font-family:'Short Stack', cursive; box-shadow:4px 4px 0 rgba(0,0,0,.1); background:#fff; }
    .pixel-file-input::file-selector-button{ background:var(--blue); border:3px solid #000; padding:4px 8px; font-family:'Press Start 2P', cursive; font-size:.7rem; margin-right:10px; }
    .pixel-textarea{ border:3px solid #000; padding:8px 12px; font-family:'Short Stack', cursive; box-shadow:4px 4px 0 rgba(0,0,0,.1); resize:none; }
    .pixel-textarea:focus{ outline:none; box-shadow:4px 4px 0 var(--purple); }
    .pixel-preview{ border:3px solid #000; box-shadow:4px 4px 0 rgba(0,0,0,.1); object-fit:contain; background:#fff; }
    .pixel-cloud{ position:absolute; background:#fff; border:3px solid #000; border-radius:50%; }
    .pixel-star{ position:absolute; color:var(--yellow); text-shadow:2px 2px 0 rgba(0,0,0,.2); animation: twinkle 2s infinite alternate; }
    .error-message{ color:#ff3333; font-family:'Press Start 2P', cursive; font-size:.7rem; text-shadow:1px 1px 0 rgba(0,0,0,.1); }
    @keyframes twinkle { from{opacity:.6; transform:scale(1);} to{opacity:1; transform:scale(1.2);} }
  </style>
</head>
<body class="min-h-screen p-6" style="background: linear-gradient(to bottom right, var(--pink), var(--purple));">

<!-- Decorative elements -->
<div class="pixel-cloud" style="width:80px; height:40px; top:5%; left:5%;"></div>
<div class="pixel-cloud" style="width:60px; height:30px; top:10%; right:10%;"></div>
<div class="pixel-star" style="top:15%; left:15%;">✦</div>
<div class="pixel-star" style="top:20%; right:20%;">✦</div>

<div class="max-w-md mx-auto relative z-10">
  <header class="flex justify-between items-center mb-6">
    <h1 class="text-2xl sm:text-3xl title-font text-white">EDIT PHOTO</h1>
    <a href="dashboard.php" class="cute-btn" style="background: var(--purple);">
      <i class="fas fa-arrow-left mr-2"></i> BACK
    </a>
  </header>

  <?php if (!empty($error)): ?>
    <script>
      document.addEventListener('DOMContentLoaded', function(){
        Swal.fire({
          icon: 'error',
          title: 'FAILED!',
          text: <?= json_encode($error); ?>,
          confirmButtonColor: '#ff9bb3',
          background: '#fff',
          confirmButtonText: 'OK',
          customClass: { title: 'title-font' }
        });
      });
    </script>
  <?php endif; ?>

  <div class="pixel-border bg-white p-5">
    <form method="POST" enctype="multipart/form-data">
      <!-- Album -->
      <div class="mb-4">
        <label class="block text-sm font-medium mb-1">ALBUM</label>
        <select name="album_id" class="pixel-input w-full">
          <?php foreach ($albums as $a): ?>
            <option value="<?= (int)$a['id']; ?>" <?= $current_album_id===(int)$a['id']?'selected':''; ?>>
              <?= htmlspecialchars($a['title'], ENT_QUOTES, 'UTF-8'); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Description -->
      <div class="mb-4">
        <label class="block text-sm font-medium mb-1">DESCRIPTION</label>
        <textarea name="description" required class="pixel-textarea w-full" rows="4"
          placeholder="Tulis deskripsi foto..."><?= htmlspecialchars($photo['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
      </div>

      <!-- Current Photo -->
      <div class="mb-4">
        <label class="block text-sm font-medium mb-1">CURRENT PHOTO</label>
        <img src="../uploads/<?= htmlspecialchars($photo['filename'], ENT_QUOTES, 'UTF-8'); ?>" class="w-full h-48 pixel-preview" alt="Current Photo">
      </div>

      <!-- New Photo -->
      <div class="mb-5">
        <label class="block text-sm font-medium mb-1">NEW PHOTO (OPTIONAL)</label>
        <input type="file" name="new_photo" accept="image/*" class="pixel-file-input w-full">
        <p class="text-xs text-gray-500 mt-1">JPG/PNG/WEBP/GIF, maksimal 5MB.</p>
      </div>

      <!-- Buttons -->
      <div class="flex justify-between">
        <button type="submit" class="cute-btn">
          <i class="fas fa-save mr-2"></i> SAVE
        </button>
        <a href="dashboard.php" class="cute-btn" style="background: var(--purple);">
          <i class="fas fa-times mr-2"></i> CANCEL
        </a>
      </div>
    </form>
  </div>
</div>
</body>
</html>
