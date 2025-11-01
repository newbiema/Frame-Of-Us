<?php
// admin/music.php
session_start();
if (!isset($_SESSION['login'])) {
  header("Location: login.php");
  exit;
}
require_once __DIR__ . '/../db.php'; // $conn (mysqli)

/* ---------------- Helpers ---------------- */
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

function ensureDir($dir){
  if (!is_dir($dir)) @mkdir($dir, 0755, true);
}

function getSetting(mysqli $conn, string $k, string $default=''): string {
  if ($st = $conn->prepare("SELECT v FROM settings WHERE k=? LIMIT 1")){
    $st->bind_param('s',$k); $st->execute();
    $r = $st->get_result(); $row = $r? $r->fetch_assoc():null; $st->close();
    return $row ? (string)$row['v'] : $default;
  }
  return $default;
}
function setSetting(mysqli $conn, string $k, string $v): bool {
  $k = $conn->real_escape_string($k);
  $v = $conn->real_escape_string($v);
  return (bool)$conn->query("INSERT INTO settings (k, v) VALUES ('$k', '$v')
                             ON DUPLICATE KEY UPDATE v=VALUES(v)");
}

// MIME detector paling robust
function detect_mime($tmpPath){
  $mime = '';
  if (class_exists('finfo')) {
    $fi = new finfo(FILEINFO_MIME_TYPE);
    if ($fi) $mime = $fi->file($tmpPath) ?: '';
  }
  if (!$mime && function_exists('mime_content_type')) {
    $mime = @mime_content_type($tmpPath) ?: '';
  }
  return strtolower($mime);
}

function sanitizeBase($s){
  $s = preg_replace('/[^A-Za-z0-9-_]+/','-', $s);
  return trim($s,'-') ?: 'audio';
}

/* ---------------- Config ------------------ */
$musicDir = realpath(__DIR__.'/../music');
if (!$musicDir) $musicDir = __DIR__.'/../music';
ensureDir($musicDir);

$maxSize = 20 * 1024 * 1024; // 20MB

// Terima variasi MIME yang sering muncul
$allowedMime = [
  'audio/mpeg', 'audio/mp3', 'audio/x-mpeg', 'audio/mpeg3', 'audio/x-mpeg-3', 'audio/mpa',
  'audio/ogg',  'application/ogg',
  'audio/wav',  'audio/x-wav',
  'audio/webm',
  'audio/aac',
  'audio/mp4', 'audio/m4a', 'audio/x-m4a',
  'application/octet-stream', // beberapa server return ini
];
// Ekstensi yang diizinkan
$allowedExt = ['mp3','ogg','wav','webm','aac','m4a'];

/* -------------- State (current) ----------- */
$currentFile  = getSetting($conn, 'bg_music', '');
$currentTitle = getSetting($conn, 'bg_music_title', '');

/* -------------- Actions ------------------- */
$status = ''; $message='';

if ($_SERVER['REQUEST_METHOD']==='POST') {
  $action = $_POST['action'] ?? '';

  // Upload audio baru
  if ($action === 'upload') {
    if (!empty($_FILES['music']['name'])) {
      $f = $_FILES['music'];

      if ($f['error']===UPLOAD_ERR_OK && $f['size'] > 0 && $f['size'] <= $maxSize) {
        $mime = detect_mime($f['tmp_name']);
        $ext  = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        // Valid kalau mime ada di whitelist / audio/* / octet-stream tapi ext valid
        $okByMime = in_array($mime, $allowedMime, true)
                    || (strpos($mime, 'audio/') === 0)
                    || ($mime === 'application/octet-stream' && in_array($ext, $allowedExt, true));

        if ($okByMime && in_array($ext, $allowedExt, true)) {
          // Pastikan ada ekstensi yang benar
          if (!$ext) {
            $ext = match(true) {
              str_starts_with($mime,'audio/mpeg'),
              $mime==='audio/mp3', $mime==='audio/x-mpeg',
              $mime==='audio/mpeg3', $mime==='audio/x-mpeg-3', $mime==='audio/mpa' => 'mp3',
              $mime==='audio/ogg' || $mime==='application/ogg' => 'ogg',
              $mime==='audio/wav' || $mime==='audio/x-wav' => 'wav',
              $mime==='audio/webm' => 'webm',
              $mime==='audio/aac' => 'aac',
              $mime==='audio/mp4' || $mime==='audio/m4a' || $mime==='audio/x-m4a' => 'm4a',
              default => 'mp3',
            };
          }

          $base  = sanitizeBase(pathinfo($f['name'], PATHINFO_FILENAME));
          $final = $base . '-' . uniqid('', true) . '.' . $ext;
          $dest  = rtrim($musicDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $final;

          if (move_uploaded_file($f['tmp_name'], $dest)) {
            $title = trim($_POST['title'] ?? '');
            $makeActive = isset($_POST['make_active']) ? 1 : 0;
            if ($makeActive) {
              setSetting($conn,'bg_music', $final);
              setSetting($conn,'bg_music_title', ($title!==''? $title : $final));
              $currentFile  = $final;
              $currentTitle = ($title!==''? $title : $final);
            }
            header("Location: music.php?s=success&m=".urlencode("Uploaded $final".($makeActive?' (set active)':'')));
            exit;
          } else {
            $status='error'; $message='Failed saving file.';
          }
        } else {
          $status='error'; $message='Unsupported audio format.';
        }
      } else {
        $status='error'; $message='Empty file / too large.';
      }
    } else {
      $status='error'; $message='No file selected.';
    }
  }

  // Set aktif dari file yang ada
  if ($action === 'set_active') {
    $fname = basename($_POST['fname'] ?? '');
    if ($fname && is_file($musicDir.DIRECTORY_SEPARATOR.$fname)) {
      setSetting($conn,'bg_music',$fname);
      $ttl = trim($_POST['fname_title'] ?? '');
      setSetting($conn,'bg_music_title', $ttl!==''? $ttl : $fname);
      header("Location: music.php?s=success&m=".urlencode("Active music set to $fname"));
      exit;
    } else {
      $status='error'; $message='File not found.';
    }
  }

  // Hapus file musik
  if ($action === 'delete') {
    $fname = basename($_POST['fname'] ?? '');
    $path  = $musicDir.DIRECTORY_SEPARATOR.$fname;
    if ($fname && is_file($path)) {
      // Jika yang dihapus adalah file aktif, kosongkan setting
      if ($fname === $currentFile) {
        setSetting($conn,'bg_music','');
        // judul biarin atau kosongkan, bebas. Kita kosongkan biar bersih:
        setSetting($conn,'bg_music_title','');
        $currentFile = ''; $currentTitle = '';
      }
      @unlink($path);
      header("Location: music.php?s=success&m=".urlencode("Deleted $fname"));
      exit;
    } else {
      $status='error'; $message='File not found.';
    }
  }
}

// Ambil daftar file di folder music
$files = [];
if (is_dir($musicDir)) {
  $scan = scandir($musicDir);
  foreach ($scan as $fn) {
    if ($fn === '.' || $fn === '..') continue;
    $p = $musicDir . DIRECTORY_SEPARATOR . $fn;
    if (is_file($p)) {
      $ext = strtolower(pathinfo($fn, PATHINFO_EXTENSION));
      if (in_array($ext, $allowedExt, true)) {
        $files[] = [
          'name' => $fn,
          'size' => filesize($p),
          'mtime'=> filemtime($p),
          'ext'  => $ext,
        ];
      }
    }
  }
  // urut terbaru di atas
  usort($files, fn($a,$b)=> $b['mtime'] <=> $a['mtime']);
}

/* Notif via GET */
if (isset($_GET['s'])) { $status=$_GET['s']; $message=$_GET['m']??''; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Pixel Admin — Music</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&family=Short+Stack&display=swap" rel="stylesheet">
  <style>
    :root { --pink:#ff9bb3; --purple:#b5a1ff; --blue:#9bd4ff; --yellow:#ffe08a; }
    body { font-family:'Short Stack', cursive; background:#fff5f7; image-rendering:pixelated; }
    .title-font{ font-family:'Press Start 2P', cursive; text-shadow:3px 3px 0 var(--purple); }
    .cute-btn{ background:var(--pink); color:#fff; border:3px solid #000; padding:10px 20px; font-size:.9rem; box-shadow:5px 5px 0 rgba(0,0,0,.2); transition:.1s; font-family:'Press Start 2P', cursive; text-shadow:2px 2px 0 rgba(0,0,0,.2); }
    .cute-btn:hover{ transform:translate(2px,2px); box-shadow:3px 3px 0 rgba(0,0,0,.2); }
    .pixel-card{ background:#fff; border:4px solid #000; box-shadow:6px 6px 0 rgba(0,0,0,.1); }
    .inp { border:3px solid #000; background:#fff; padding:.6rem .8rem; width:100%; }
    .btn-mini { border:3px solid #000; background:#fff; padding:.35rem .6rem; font-size:.8rem; }
    .btn-mini:hover { background:#000; color:#fff; }
    .badge { background:#000; color:#fff; padding:.2rem .5rem; border-radius:.5rem; font-size:.7rem; }
    .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono","Courier New", monospace; }
    .audio-box { border:3px solid #000; background:#fff; padding:.5rem; }
  </style>
</head>
<body class="min-h-screen p-6">

<div class="max-w-5xl mx-auto">
  <header class="flex flex-wrap items-center justify-between mb-8 gap-4">
    <h1 class="text-3xl sm:text-4xl title-font">MUSIC MANAGER</h1>
    <div class="flex gap-3">
      <a href="dashboard.php" class="cute-btn"><i class="fa-solid fa-arrow-left mr-2"></i> BACK TO DASHBOARD</a>
      <a href="../index.php" class="cute-btn" style="background:var(--purple);"><i class="fa-solid fa-globe mr-2"></i> VIEW SITE</a>
    </div>
  </header>

  <?php if ($status): ?>
    <script>
      Swal.fire({
        icon: '<?= h($status) ?>',
        title: '<?= $status==="success"?"SUCCESS":($status==="error"?"ERROR":"INFO") ?>',
        text: `<?= h($message) ?>`,
        confirmButtonText: 'OK',
        background: '#fff',
        customClass: { title: 'title-font' }
      });
    </script>
  <?php endif; ?>

  <!-- Current active music -->
  <section class="pixel-card p-6 mb-8">
    <h2 class="text-xl title-font mb-4" style="color:var(--purple)">CURRENT BACKSOUND</h2>
    <?php if ($currentFile): ?>
      <div class="grid sm:grid-cols-3 gap-4 items-center">
        <div>
          <div class="badge mb-2">Active File</div>
          <div class="mono break-all"><?php echo h($currentFile); ?></div>
          <div class="mt-2 text-sm">Title: <strong><?php echo h($currentTitle ?: $currentFile); ?></strong></div>
        </div>
        <div class="sm:col-span-2">
          <div class="audio-box">
            <audio controls preload="metadata" style="width:100%;">
              <source src="<?php echo h('../music/'.$currentFile); ?>" type="audio/mpeg">
              <source src="<?php echo h('../music/'.$currentFile); ?>">
              Browser kamu tidak mendukung tag audio.
            </audio>
          </div>
        </div>
      </div>
    <?php else: ?>
      <p class="italic text-gray-600">Belum ada backsound aktif.</p>
    <?php endif; ?>
  </section>

  <!-- Upload new music -->
  <section class="pixel-card p-6 mb-8">
    <h2 class="text-xl title-font mb-4" style="color:var(--purple)">UPLOAD NEW</h2>
    <form method="post" enctype="multipart/form-data" class="grid md:grid-cols-2 gap-4">
      <input type="hidden" name="action" value="upload">
      <div class="md:col-span-2">
        <label class="block text-sm font-semibold mb-1">Select file (MP3/OGG/WAV/WEBM/AAC/M4A, max 20MB)</label>
        <input class="inp" type="file" name="music" accept="audio/*" required>
      </div>
      <div>
        <label class="block text-sm font-semibold mb-1">Display Title (optional)</label>
        <input class="inp" type="text" name="title" placeholder="e.g. Bedroom Pop — 2021 Edit">
      </div>
      <div class="flex items-center gap-2">
        <input id="make_active" type="checkbox" name="make_active" class="w-4 h-4 border-2 border-black">
        <label for="make_active" class="text-sm">Set as active backsound</label>
      </div>
      <div class="md:col-span-2">
        <button class="cute-btn"><i class="fa-solid fa-upload mr-2"></i>UPLOAD</button>
      </div>
    </form>
  </section>

  <!-- Library -->
  <section class="pixel-card p-6">
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-xl title-font" style="color:var(--purple)">LIBRARY</h2>
      <div class="text-sm text-gray-600">Folder: <span class="mono">/music</span></div>
    </div>

    <?php if (!$files): ?>
      <div class="text-center py-10 italic text-gray-600">Belum ada file audio di folder /music.</div>
    <?php else: ?>
      <div class="overflow-x-auto">
        <table class="w-full">
          <thead>
            <tr class="text-left text-sm">
              <th class="py-2 pr-3">File</th>
              <th class="py-2 pr-3">Size</th>
              <th class="py-2 pr-3">Modified</th>
              <th class="py-2 pr-3">Preview</th>
              <th class="py-2">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($files as $f):
              $isActive = ($f['name'] === $currentFile);
              $sizeKB = max(1, (int)ceil($f['size']/1024));
              $mtime  = date('Y-m-d H:i', $f['mtime']);
            ?>
            <tr class="border-t border-black/20 align-top">
              <td class="py-3 pr-3">
                <div class="mono break-all <?php echo $isActive?'font-bold':''; ?>">
                  <?php echo h($f['name']); ?>
                </div>
                <?php if ($isActive): ?>
                  <div class="badge mt-1">ACTIVE</div>
                <?php endif; ?>
              </td>
              <td class="py-3 pr-3"><?php echo $sizeKB; ?> KB</td>
              <td class="py-3 pr-3"><?php echo h($mtime); ?></td>
              <td class="py-3 pr-3" style="min-width:220px;">
                <audio controls preload="metadata" style="width:220px;">
                  <source src="<?php echo h('../music/'.$f['name']); ?>">
                </audio>
              </td>
              <td class="py-3">
                <div class="flex flex-col sm:flex-row gap-2">
                  <form method="post" class="flex items-center gap-2">
                    <input type="hidden" name="action" value="set_active">
                    <input type="hidden" name="fname" value="<?php echo h($f['name']); ?>">
                    <input class="inp" type="text" name="fname_title" placeholder="Display title" value="<?php echo h($isActive ? $currentTitle : ''); ?>" style="max-width:220px;">
                    <button class="btn-mini" <?php echo $isActive?'disabled':''; ?>>Set Active</button>
                  </form>
                  <form method="post" onsubmit="return confirm('Hapus file ini?');">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="fname" value="<?php echo h($f['name']); ?>">
                    <button class="btn-mini">Delete</button>
                  </form>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
</div>
</body>
</html>
