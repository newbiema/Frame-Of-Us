<?php
session_start();
if (!isset($_SESSION['login'])) {
  header("Location: login.php");
  exit;
}
require_once __DIR__ . '/../db.php'; // $conn (mysqli)

/* ---------- helpers ---------- */
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function mimeToExt($m){
  return match($m){
    'image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif', default=>null
  };
}
function sanitizeBase($s){
  $s = preg_replace('/[^A-Za-z0-9-_]+/','-', $s);
  return trim($s,'-');
}
function reArrayFiles($filePost){
  $out=[]; $n=is_array($filePost['name'])?count($filePost['name']):0;
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

/* ---------- config upload ---------- */
$uploadDir = realpath(__DIR__ . '/../uploads');
if (!$uploadDir) { $uploadDir = __DIR__ . '/../uploads'; }
if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0755, true); }

$maxFiles    = 20;
$maxSize     = 5 * 1024 * 1024; // 5MB
$allowedMime = ['image/jpeg','image/png','image/webp','image/gif'];

/* ---------- get album ---------- */
$aid = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($aid <= 0) { header('Location: dashboard.php'); exit; }

$album=null;
if ($st=$conn->prepare("SELECT id,title,description,created_at FROM albums WHERE id=? LIMIT 1")){
  $st->bind_param('i',$aid); $st->execute();
  $res=$st->get_result(); $album=$res?$res->fetch_assoc():null; $st->close();
}
if (!$album){ header('Location: dashboard.php'); exit; }

/* ---------- handle POST actions ---------- */
$status=""; $message="";
if ($_SERVER['REQUEST_METHOD']==='POST'){
  $action = $_POST['action'] ?? 'save_album';

  // 0) reorder photos (drag & drop)
  if ($action === 'reorder_photos') {
    $order = $_POST['order'] ?? [];
    if (is_array($order) && $order) {
      $pos = 1;
      if ($st = $conn->prepare("UPDATE photos SET position=? WHERE id=? AND album_id=?")) {
        foreach ($order as $pid) {
          $pid = (int)$pid;
          if ($pid <= 0) continue;
          $st->bind_param('iii', $pos, $pid, $aid);
          $st->execute();
          $pos++;
        }
        $st->close();
      }
      $status='success'; $message='Urutan foto disimpan.';
    }
    header("Location: album_edit.php?id=".$aid."&s=$status&m=".urlencode($message));
    exit;
  }

  // 1) Simpan judul/desc + tambah foto baru
  if ($action === 'save_album'){
    $title = trim($_POST['title'] ?? '');
    $desc  = trim($_POST['description'] ?? '');

    if ($st=$conn->prepare("UPDATE albums SET title=?, description=? WHERE id=?")){
      $st->bind_param('ssi',$title,$desc,$aid);
      if(!$st->execute()){ $status='error'; $message='Gagal update album.'; }
      $st->close();
    }

    // tambah foto baru (opsional)
    if (!empty($_FILES['new_photos']) && is_array($_FILES['new_photos']['name'])){
      $files = reArrayFiles($_FILES['new_photos']);
      if (count($files) > $maxFiles){
        $status='error'; $message="Maksimal $maxFiles file per unggah.";
      } else {
        // cari posisi terakhir
        $lastPos = 0;
        if ($r = $conn->query("SELECT COALESCE(MAX(position),0) AS p FROM photos WHERE album_id=".$aid)) {
          $row = $r->fetch_assoc();
          $lastPos = (int)($row['p'] ?? 0);
          $r->free();
        }

        $ok=0; $errs=[]; $finfo=new finfo(FILEINFO_MIME_TYPE);
        $stIns=$conn->prepare("INSERT INTO photos (album_id, description, filename, position, created_at) VALUES (?, ?, ?, ?, NOW())");
        foreach($files as $i=>$f){
          if($f['error']===UPLOAD_ERR_NO_FILE) continue;
          if($f['error']!==UPLOAD_ERR_OK){ $errs[]="File #".($i+1)." gagal (err {$f['error']})."; continue; }
          if($f['size']>$maxSize){ $errs[]="{$f['name']} > 5MB."; continue; }

          $mime=$finfo->file($f['tmp_name']);
          if(!in_array($mime,$allowedMime,true)){ $errs[]="{$f['name']} bukan gambar didukung."; continue; }

          $ext = mimeToExt($mime) ?? strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
          $base= sanitizeBase(pathinfo($f['name'], PATHINFO_FILENAME));
          $final = $base.'-'.uniqid('',true).'.'.$ext;
          $dest  = rtrim($uploadDir,DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$final;

          if(!move_uploaded_file($f['tmp_name'],$dest)){ $errs[]="Gagal simpan {$f['name']}."; continue; }

          $empty=''; $lastPos++;
          if($stIns){
            $stIns->bind_param('issis',$aid,$empty,$final,$lastPos);
            if($stIns->execute()){ $ok++; } else { $errs[]="Gagal insert DB untuk {$f['name']}."; }
          } else { $errs[]="DB statement gagal init."; }
        }
        if($stIns) $stIns->close();

        if($ok>0){ $status='success'; $message="$ok foto baru ditambahkan.". (count($errs)?"\n".implode("\n",$errs):""); }
        elseif(!$status){ $status='info'; $message= count($errs)? implode("\n",$errs):"Tidak ada foto baru diunggah."; }
      }
    }

    header("Location: album_edit.php?id=".$aid.($status?"&s=$status&m=".urlencode($message):""));
    exit;
  }

  // 2) Hapus satu foto
  if ($action === 'delete_photo'){
    $pid = (int)($_POST['photo_id'] ?? 0);
    if ($pid>0){
      if($st=$conn->prepare("SELECT filename FROM photos WHERE id=? AND album_id=? LIMIT 1")){
        $st->bind_param('ii',$pid,$aid); $st->execute();
        $r=$st->get_result(); $row=$r?$r->fetch_assoc():null; $st->close();
        if($row){
          $file = $row['filename'];
          if($st=$conn->prepare("DELETE FROM photos WHERE id=? AND album_id=?")){
            $st->bind_param('ii',$pid,$aid);
            if($st->execute()){
              $path = rtrim($uploadDir,DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$file;
              if(is_file($path)) @unlink($path);
              $status='success'; $message='Foto dihapus.';
            } else { $status='error'; $message='Gagal menghapus foto.'; }
            $st->close();
          }
        } else { $status='error'; $message='Foto tidak ditemukan.'; }
      }
    }
    header("Location: album_edit.php?id=".$aid.($status?"&s=$status&m=".urlencode($message):""));
    exit;
  }

  // 3) Replace satu foto
  if ($action === 'replace_photo'){
    $pid = (int)($_POST['photo_id'] ?? 0);
    if ($pid>0 && isset($_FILES['replace_file'])){
      if($st=$conn->prepare("SELECT filename FROM photos WHERE id=? AND album_id=? LIMIT 1")){
        $st->bind_param('ii',$pid,$aid); $st->execute();
        $r=$st->get_result(); $row=$r?$r->fetch_assoc():null; $st->close();
        if($row){
          $f = $_FILES['replace_file'];
          if($f['error']===UPLOAD_ERR_OK && $f['size'] <= $maxSize){
            $finfo=new finfo(FILEINFO_MIME_TYPE);
            $mime=$finfo->file($f['tmp_name']);
            if(in_array($mime,$allowedMime,true)){
              $ext = mimeToExt($mime) ?? strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
              $base= sanitizeBase(pathinfo($f['name'], PATHINFO_FILENAME));
              $final = $base.'-'.uniqid('',true).'.'.$ext;
              $dest  = rtrim($uploadDir,DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$final;

              if(move_uploaded_file($f['tmp_name'],$dest)){
                if($st=$conn->prepare("UPDATE photos SET filename=? WHERE id=? AND album_id=?")){
                  $st->bind_param('sii',$final,$pid,$aid);
                  if($st->execute()){
                    $old = rtrim($uploadDir,DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$row['filename'];
                    if(is_file($old)) @unlink($old);
                    $status='success'; $message='Foto diganti.';
                  } else { $status='error'; $message='Gagal update foto.'; }
                  $st->close();
                }
              } else { $status='error'; $message='Gagal menyimpan file baru.'; }
            } else { $status='error'; $message='Format tidak didukung.'; }
          } else { $status='error'; $message='File kosong/terlalu besar.'; }
        } else { $status='error'; $message='Foto tidak ditemukan.'; }
      }
    }
    header("Location: album_edit.php?id=".$aid.($status?"&s=$status&m=".urlencode($message):""));
    exit;
  }
}

/* ---------- reload photos for view ---------- */
$photos=[];
if ($st=$conn->prepare("SELECT id,filename,description,COALESCE(position,0) AS position FROM photos WHERE album_id=? ORDER BY position ASC, id ASC")){
  $st->bind_param('i',$aid); $st->execute();
  $r=$st->get_result(); while($row=$r->fetch_assoc()){ $photos[]=$row; } $st->close();
}

/* ---------- read optional notif ---------- */
if (isset($_GET['s'])){ $status=$_GET['s']; $message=$_GET['m']??""; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Pixel Edit Album</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <!-- SortableJS biar drag enak di mobile -->
  <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&family=Short+Stack&display=swap" rel="stylesheet">
  <style>
    :root{ --pink:#ff9bb3; --purple:#b5a1ff; --blue:#9bd4ff; --yellow:#ffe08a; }
    body{ font-family:'Short Stack',cursive; background:linear-gradient(to bottom right,var(--pink),var(--purple)); image-rendering:pixelated; }
    .pixel-border{ border:4px solid #000; box-shadow:8px 8px 0 rgba(0,0,0,.2); position:relative; background:#fff; }
    .pixel-border:before{ content:''; position:absolute; inset:2px; border:2px solid #fff; pointer-events:none; }
    .title-font{ font-family:'Press Start 2P',cursive; text-shadow:3px 3px 0 var(--purple); }
    .cute-btn{ background:var(--pink); color:#fff; border:3px solid #000; padding:10px 20px; font-size:.9rem; box-shadow:5px 5px 0 rgba(0,0,0,.2); transition:.1s; font-family:'Press Start 2P',cursive; }
    .cute-btn:hover{ transform:translate(2px,2px); box-shadow:3px 3px 0 rgba(0,0,0,.2); }
    .pixel-input,.pixel-textarea,.pixel-file-input{ border:3px solid #000; padding:8px 12px; box-shadow:4px 4px 0 rgba(0,0,0,.1); }
    .pixel-textarea{ resize:none; }
    .pixel-file-input::file-selector-button{ background:var(--blue); border:3px solid #000; padding:4px 8px; font-family:'Press Start 2P',cursive; font-size:.7rem; margin-right:10px; }

    .grid-preview{ display:grid; grid-template-columns:repeat(auto-fill,minmax(160px,1fr)); gap:16px; }
    .thumb-card{ display:flex; flex-direction:column; gap:10px; border:3px solid #000; background:#fff; padding:8px; position:relative; }
    .thumb-wrap{ aspect-ratio:1/1; overflow:hidden; }
    .thumb{ width:100%; height:100%; object-fit:cover; display:block; }
    .order-badge{ position:absolute; top:6px; left:6px; background:#000; color:#fff; font-size:.6rem; padding:2px 6px; z-index:5; }
    .drag-handle{ position:absolute; top:6px; right:6px; background:#fff; border:2px solid #000; width:26px; height:26px; display:flex; align-items:center; justify-content:center; cursor:grab; touch-action:none; }

    .mini-btn{ border:2px solid #000; padding:6px 10px; background:#fff; font-size:.72rem; box-shadow:3px 3px 0 rgba(0,0,0,.15); line-height:1; }
    .mini-btn.del{ background:#ffdddd; }
    .mini-btn.rep{ background:#ddf2ff; }
    .btn-row{ display:flex; align-items:center; justify-content:space-between; gap:8px; margin-top:4px; }
    .replace-input{ display:none; }
    .mini-note{ font-size:.7rem; opacity:.7; }
  </style>
</head>
<body class="min-h-screen p-6">
  <div class="max-w-3xl mx-auto">
    <header class="text-center mb-8">
      <h1 class="text-3xl sm:text-4xl title-font text-white">EDIT ALBUM</h1>
    </header>

    <?php if ($status): ?>
      <script>
        Swal.fire({
          icon: '<?= h($status) ?>',
          title: '<?= $status==="success"?"SUCCESS":($status==="error"?"ERROR":"INFO") ?>',
          text: `<?= h($message) ?>`,
          confirmButtonText: 'OK',
          background: '#fff',
          customClass: { title: 'title-font', confirmButton: 'pixel-border' }
        });
      </script>
    <?php endif; ?>

    <!-- Form utama -->
    <div class="pixel-border p-6 mb-8">
      <form method="POST" enctype="multipart/form-data" class="space-y-4">
        <input type="hidden" name="action" value="save_album">
        <div>
          <label class="block text-sm font-medium mb-1">TITLE</label>
          <input type="text" name="title" class="pixel-input w-full" value="<?= h($album['title']) ?>" placeholder="Album title">
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">DESCRIPTION</label>
          <textarea name="description" rows="4" class="pixel-textarea w-full" placeholder="About this album..."><?= h($album['description']) ?></textarea>
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">ADD NEW PHOTOS (optional)</label>
          <input type="file" name="new_photos[]" accept="image/*" multiple class="pixel-file-input w-full">
          <p class="mini-note mt-1">JPG/PNG/WEBP/GIF • maks 5MB • boleh banyak.</p>
        </div>

        <div class="flex gap-3">
          <button type="submit" class="cute-btn"><i class="fas fa-save mr-2"></i> SAVE</button>
          <a href="dashboard.php" class="cute-btn" style="background:var(--purple);"><i class="fas fa-arrow-left mr-2"></i> BACK</a>
        </div>
      </form>
    </div>

    <!-- Grid foto -->
    <div class="pixel-border p-6">
      <div class="flex items-center justify-between mb-4 gap-3">
        <h2 class="title-font text-xl">CURRENT PHOTOS</h2>
        <?php if ($photos): ?>
        <form method="POST" id="orderForm">
          <input type="hidden" name="action" value="reorder_photos">
          <div id="orderInputs"></div>
          <button type="submit" class="mini-btn"><i class="fa-solid fa-floppy-disk mr-1"></i>Save Order</button>
        </form>
        <?php endif; ?>
      </div>

      <?php if ($photos): ?>
        <div class="grid-preview" id="photoGrid">
          <?php foreach($photos as $p): $pid=(int)$p['id']; ?>
            <div class="thumb-card" data-id="<?= $pid ?>">
              <div class="relative thumb-wrap">
                <span class="order-badge"></span>
                <span class="drag-handle">&#9776;</span>
                <img src="../uploads/<?= h($p['filename']) ?>" alt="" class="thumb">
              </div>

              <div class="btn-row">
                <form method="POST" onsubmit="return confirm('Hapus foto ini?');">
                  <input type="hidden" name="action" value="delete_photo">
                  <input type="hidden" name="photo_id" value="<?= $pid ?>">
                  <button class="mini-btn del"><i class="fa-solid fa-trash-can mr-1"></i>Del</button>
                </form>

                <form method="POST" enctype="multipart/form-data" class="inline-flex items-center gap-2">
                  <input type="hidden" name="action" value="replace_photo">
                  <input type="hidden" name="photo_id" value="<?= $pid ?>">
                  <input id="file-<?= $pid ?>" class="replace-input" type="file" name="replace_file" accept="image/*" required>
                  <label for="file-<?= $pid ?>" class="mini-btn rep cursor-pointer">
                    <i class="fa-solid fa-rotate mr-1"></i>Rep
                  </label>
                </form>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p class="italic opacity-70">No photos yet.</p>
      <?php endif; ?>
    </div>
  </div>

  <script>
    // auto-submit replace
    document.querySelectorAll('.replace-input').forEach(inp=>{
      inp.addEventListener('change', e=>{
        if(e.target.files && e.target.files.length){
          e.target.closest('form').submit();
        }
      });
    });

    const grid = document.getElementById('photoGrid');
    const orderInputs = document.getElementById('orderInputs');

    function rebuildOrderInputs() {
      if (!grid || !orderInputs) return;
      orderInputs.innerHTML = '';
      const cards = grid.querySelectorAll('.thumb-card');
      cards.forEach((card, i) => {
        const id = card.getAttribute('data-id');
        // badge nomor
        const badge = card.querySelector('.order-badge');
        if (badge) badge.textContent = i + 1;

        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'order[]';
        hidden.value = id;
        orderInputs.appendChild(hidden);
      });
    }

    if (grid) {
      // aktifkan Sortable di sini
      new Sortable(grid, {
        animation: 150,
        handle: '.drag-handle',
        onSort: function () {
          rebuildOrderInputs();
        }
      });
      // initial
      rebuildOrderInputs();
    }
  </script>
</body>
</html>
