<?php
session_start();
if (!isset($_SESSION['login'])) {
    header("Location: login.php");
    exit;
}
include '../db.php'; // mysqli $conn

$message = "";
$status = "";

// Konfigurasi
$uploadDir = __DIR__ . '/../uploads';
$maxFiles  = 20;
$maxSize   = 5 * 1024 * 1024; // 5MB
$allowedMime = ['image/jpeg','image/png','image/webp','image/gif'];

if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0755, true); }
$uploadDir = realpath($uploadDir);
if ($uploadDir === false) {
  $status = "error";
  $message = "Folder uploads tidak bisa dibuat/diakses.";
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && empty($message)) {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $userId = $_SESSION['user_id'] ?? null; // kalau ada

    if (!isset($_FILES['fotos'])) {
        $message = "Tidak ada file yang dipilih.";
        $status  = "error";
    } else {
        // reArray dulu
        $allFiles = reArrayFiles($_FILES['fotos']);

        // ambil urutan dari front-end (misal ["2","0","1"])
        $orderedIndexes = $_POST['order'] ?? [];
        $files = [];

        if ($orderedIndexes) {
            // susun ulang berdasarkan order[]
            foreach ($orderedIndexes as $idxStr) {
                $idx = (int)$idxStr;
                if (isset($allFiles[$idx])) {
                    $files[] = $allFiles[$idx];
                }
            }
        } else {
            // fallback: pakai urutan asli
            $files = $allFiles;
        }

        // buang yang kosong
        $files = array_values(array_filter($files, fn($f) => isset($f['error']) && $f['error'] !== UPLOAD_ERR_NO_FILE));

        if (!$files) {
            $message = "Tidak ada file yang dipilih.";
            $status  = "error";
        } elseif (count($files) > $maxFiles) {
            $message = "Maksimal $maxFiles file per unggah.";
            $status  = "error";
        } else {
            // 1) buat album
            $albumId = null;
            // sesuaikan field albums kamu: kalau ga ada created_by, ubah query
            if ($stmtAlbum = $conn->prepare("INSERT INTO albums (title, description, created_by) VALUES (?, ?, ?)")) {
                $stmtAlbum->bind_param("ssi", $title, $description, $userId);
                $stmtAlbum->execute();
                $albumId = $stmtAlbum->insert_id;
                $stmtAlbum->close();
            } else {
                $status = "error";
                $message = "Gagal membuat album.";
            }

            if ($albumId) {
                $ok = 0; $errs = [];
                $finfo = new finfo(FILEINFO_MIME_TYPE);

                // kalau tabel photos kamu punya kolom position, kita isi berurutan
                $position = 1;

                // sesuaikan field photos kamu
                $stmt = $conn->prepare("
                    INSERT INTO photos (album_id, description, filename, created_at, position)
                    VALUES (?, ?, ?, NOW(), ?)
                ");

                foreach ($files as $i => $f) {
                    if ($f['error'] !== UPLOAD_ERR_OK) {
                        $errs[] = "File #".($i+1)." gagal: ".fileErrorText($f['error']);
                        continue;
                    }
                    if ($f['size'] > $maxSize) { $errs[] = "{$f['name']} > 5MB."; continue; }

                    $mime = $finfo->file($f['tmp_name']);
                    if (!in_array($mime, $allowedMime, true)) {
                        $errs[] = "{$f['name']} bukan gambar didukung.";
                        continue;
                    }

                    $ext  = mimeToExt($mime) ?? strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
                    $base = sanitize(pathinfo($f['name'], PATHINFO_FILENAME));
                    $final= $base . '-' . uniqid('', true) . '.' . $ext;
                    $dest = $uploadDir . DIRECTORY_SEPARATOR . $final;

                    if (!move_uploaded_file($f['tmp_name'], $dest)) {
                        $errs[] = "Gagal menyimpan {$f['name']}.";
                        continue;
                    }

                    if ($stmt) {
                        $emptyDesc = ""; // desc foto per item, kamu bisa isi lain
                        $stmt->bind_param("issi", $albumId, $emptyDesc, $final, $position);
                        if ($stmt->execute()) {
                            $ok++;
                            $position++;
                        } else {
                            $errs[] = "Gagal insert DB untuk {$f['name']}.";
                        }
                    } else {
                        $errs[] = "DB statement gagal diinisialisasi.";
                    }
                }
                if ($stmt) $stmt->close();

                if ($ok > 0) {
                    $status = "success";
                    $message = "Album dibuat (#$albumId). $ok foto berhasil diunggah." . (count($errs) ? "\n".implode("\n",$errs) : "");
                } else {
                    $status = "error";
                    $message = "Album kosong: tidak ada foto yang berhasil diunggah.\n" . (count($errs) ? implode("\n",$errs) : "");
                }
            }
        }
    }
}

// ---------- helpers ----------
function reArrayFiles($filePost) {
    $out=[]; $n=is_array($filePost['name']) ? count($filePost['name']) : 0;
    for($i=0;$i<$n;$i++){
        $out[$i]=[
          'name'=>$filePost['name'][$i]??null,
          'type'=>$filePost['type'][$i]??null,
          'tmp_name'=>$filePost['tmp_name'][$i]??null,
          'error'=>$filePost['error'][$i]??UPLOAD_ERR_NO_FILE,
          'size'=>$filePost['size'][$i]??0
        ];
    }
    return $out;
}
function mimeToExt($m){ return match($m){
  'image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif', default=>null
};}
function sanitize($s){ $s=preg_replace('/[^A-Za-z0-9-_]+/','-',$s); return trim($s,'-'); }
function fileErrorText($code) {
  return match ($code) {
    UPLOAD_ERR_INI_SIZE   => 'Ukuran file melebihi upload_max_filesize.',
    UPLOAD_ERR_FORM_SIZE  => 'Ukuran file melebihi MAX_FILE_SIZE di form.',
    UPLOAD_ERR_PARTIAL    => 'File hanya terunggah sebagian.',
    UPLOAD_ERR_NO_FILE    => 'Tidak ada file yang diunggah.',
    UPLOAD_ERR_NO_TMP_DIR => 'Folder tmp hilang (server).',
    UPLOAD_ERR_CANT_WRITE => 'Gagal menulis ke disk.',
    UPLOAD_ERR_EXTENSION  => 'Diblok ekstensi PHP.',
    default => 'Kode error tidak dikenal.'
  };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Pixel Upload</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <!-- SortableJS untuk drag di mobile -->
  <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&family=Short+Stack&display=swap" rel="stylesheet">
  <style>
    :root { --pink:#ff9bb3; --purple:#b5a1ff; --blue:#9bd4ff; --yellow:#ffe08a; }
    body { font-family:'Short Stack', cursive; background-color:#fff5f7; image-rendering:pixelated; }
    .pixel-border { border:4px solid #000; box-shadow:8px 8px 0 rgba(0,0,0,.2); position:relative; }
    .pixel-border:before { content:''; position:absolute; inset:2px; border:2px solid #fff; pointer-events:none; }
    .cute-btn { background:var(--pink); color:#fff; border:3px solid #000; padding:10px 20px; font-size:.9rem; box-shadow:5px 5px 0 rgba(0,0,0,.2); transition:.1s; font-family:'Press Start 2P', cursive; text-shadow:2px 2px 0 rgba(0,0,0,.2); }
    .cute-btn:hover { transform:translate(2px,2px); box-shadow:3px 3px 0 rgba(0,0,0,.2); }
    .title-font { font-family:'Press Start 2P', cursive; text-shadow:3px 3px 0 var(--purple); }
    .pixel-input,.pixel-textarea,.pixel-file-input { border:3px solid #000; padding:8px 12px; font-family:'Short Stack', cursive; box-shadow:4px 4px 0 rgba(0,0,0,.1); }
    .pixel-textarea{ resize:none; }
    .pixel-file-input::file-selector-button{ background:var(--blue); border:3px solid #000; padding:4px 8px; font-family:'Press Start 2P', cursive; font-size:.7rem; margin-right:10px; }

    .grid-preview{ display:grid; grid-template-columns:repeat(auto-fill,minmax(90px,1fr)); gap:10px; }
    .preview-item{
      position:relative; border:3px solid #000; background:#fff; box-shadow:4px 4px 0 rgba(0,0,0,.1);
      display:flex; flex-direction:column; gap:4px; align-items:stretch;
    }
    .preview-item img{ width:100%; height:80px; object-fit:cover; }
    .drag-handle{
      position:absolute; top:4px; left:4px;
      background:rgba(255,255,255,.9); border:2px solid #000;
      width:26px; height:26px; display:flex; align-items:center; justify-content:center;
      font-size:14px; cursor:grab;
      touch-action:none;
    }
    .preview-index{
      text-align:center; font-size:.65rem; padding:2px 0 4px; background:#fff;
    }
  </style>
</head>
<body class="min-h-screen p-6" style="background: linear-gradient(to bottom right, var(--pink), var(--purple));">
<div class="max-w-md mx-auto relative z-10">
  <header class="flex justify-center mb-8">
    <h1 class="text-3xl sm:text-4xl title-font text-white">UPLOAD PHOTOS (Carousel/Album)</h1>
  </header>

  <?php if (!empty($message)) : ?>
    <script>
      Swal.fire({
        icon: '<?= $status ?>',
        title: '<?= $status === "success" ? "SUCCESS!" : "ERROR!" ?>',
        text: `<?= $message ?>`,
        confirmButtonText: 'OK',
        background: 'white',
        customClass: { title: 'title-font', confirmButton: 'pixel-border' }
      }).then(() => {
        <?php if ($status === "success") : ?> window.location.href='upload.php'; <?php endif; ?>
      });
    </script>
  <?php endif; ?>

  <div class="pixel-border bg-white p-6">
    <form action="upload.php" method="POST" enctype="multipart/form-data" id="uploadForm">
      <div class="mb-4">
        <label class="block text-sm font-medium mb-1">ALBUM TITLE</label>
        <input type="text" name="title" class="pixel-input w-full" placeholder="Judul album (opsional)">
      </div>

      <div class="mb-4">
        <label class="block text-sm font-medium mb-1">ALBUM DESCRIPTION</label>
        <textarea name="description" required rows="4" class="pixel-textarea w-full" placeholder="Deskripsi album"></textarea>
      </div>

      <div class="mb-4">
        <label class="block text-sm font-medium mb-1">CHOOSE PHOTOS</label>
        <input type="file" id="fotos" name="fotos[]" accept="image/*" multiple required class="pixel-file-input w-full">
        <p class="text-xs mt-1 opacity-80">Pilih banyak file sekaligus, lalu urutkan di bawah (bisa drag di HP).</p>
      </div>

      <div class="mb-4">
        <label class="block text-sm font-medium mb-1">PREVIEW (drag icon ≡ untuk urutkan)</label>
        <div id="preview-grid" class="grid-preview"></div>
      </div>

      <!-- hidden order diisi dari JS -->
      <div id="order-holder"></div>

      <div class="grid grid-cols-1 gap-3 mt-6">
        <button type="submit" class="cute-btn"><i class="fas fa-upload mr-2"></i> CREATE ALBUM & UPLOAD</button>
        <a href="dashboard.php" class="cute-btn text-center" style="background: var(--purple);">
          <i class="fas fa-arrow-left mr-2"></i> BACK TO DASHBOARD
        </a>
      </div>
    </form>
  </div>
</div>

<script>
const input = document.getElementById('fotos');
const grid  = document.getElementById('preview-grid');
const orderHolder = document.getElementById('order-holder');

function rebuildOrderInputs() {
  // hapus dulu
  orderHolder.innerHTML = '';
  const items = grid.querySelectorAll('.preview-item');
  items.forEach((item, newIndex) => {
    const originalIndex = item.getAttribute('data-index');
    const hidden = document.createElement('input');
    hidden.type = 'hidden';
    hidden.name = 'order[]';
    hidden.value = originalIndex;
    orderHolder.appendChild(hidden);

    // update nomor urut yg kelihatan
    const idxEl = item.querySelector('.preview-index');
    if (idxEl) idxEl.textContent = (newIndex+1);
  });
}

input.addEventListener('change', () => {
  grid.innerHTML = '';
  const files = input.files;
  [...files].forEach((file, idx) => {
    const wrap = document.createElement('div');
    wrap.className = 'preview-item';
    wrap.setAttribute('data-index', idx);

    const handle = document.createElement('div');
    handle.className = 'drag-handle';
    handle.innerHTML = '&#9776;';

    const img = document.createElement('img');
    const reader = new FileReader();
    reader.onload = e => img.src = e.target.result;
    reader.readAsDataURL(file);

    const info = document.createElement('div');
    info.className = 'preview-index';
    info.textContent = (idx+1);

    wrap.appendChild(handle);
    wrap.appendChild(img);
    wrap.appendChild(info);
    grid.appendChild(wrap);
  });

  // setelah preview dibuat, rebuild order
  rebuildOrderInputs();
});

// aktifkan SortableJS biar drag enak di mobile
new Sortable(grid, {
  animation: 150,
  handle: '.drag-handle',
  onSort: function() {
    rebuildOrderInputs();
  }
});

// sebelum submit pastikan order terbaru dikirim
document.getElementById('uploadForm').addEventListener('submit', ()=>{
  rebuildOrderInputs();
});
</script>
</body>
</html>
