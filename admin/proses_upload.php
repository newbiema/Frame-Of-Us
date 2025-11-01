<?php
session_start();
include '../db.php';

$uploadDir = realpath(__DIR__ . '/../uploads');
$maxFiles  = 20;
$maxSize   = 5 * 1024 * 1024;
$allowedMime = ['image/jpeg','image/png','image/webp','image/gif'];
if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0755, true); }

$status = "error"; $message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    if (!isset($_FILES['fotos'])) {
        $message = "Tidak ada file dikirim.";
    } else {
        $files = reArrayFiles($_FILES['fotos']);
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $ok=0; $errs=[];
        $stmt = $conn->prepare("INSERT INTO photos (title, description, filename, created_at) VALUES (?, ?, ?, NOW())");

        foreach ($files as $f) {
            if ($f['error'] === UPLOAD_ERR_NO_FILE) continue;
            if ($f['error'] !== UPLOAD_ERR_OK) { $errs[]="Error upload."; continue; }
            if ($f['size'] > $maxSize)         { $errs[]="File terlalu besar."; continue; }
            $mime = $finfo->file($f['tmp_name']);
            if (!in_array($mime,$allowedMime,true)){ $errs[]="Tipe tidak didukung."; continue; }

            $ext = match($mime){'image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif',default=>strtolower(pathinfo($f['name'],PATHINFO_EXTENSION))};
            $base = preg_replace('/[^A-Za-z0-9-_]+/','-', pathinfo($f['name'], PATHINFO_FILENAME));
            $final = trim($base,'-') . '-' . uniqid('', true) . '.' . $ext;

            if (move_uploaded_file($f['tmp_name'], $uploadDir . DIRECTORY_SEPARATOR . $final)) {
                if ($stmt) { $stmt->bind_param("sss",$title,$description,$final); $stmt->execute(); $ok++; }
            } else { $errs[]="Gagal simpan file."; }
        }
        if (isset($stmt) && $stmt) $stmt->close();

        if ($ok>0){ $status="success"; $message="$ok file berhasil diunggah."; }
        if ($errs){ $message .= ($message? "\n" : "") . implode("\n",$errs); }
    }
}

header("Location: upload.php?status={$status}&msg=" . rawurlencode($message));
exit;

// helpers
function reArrayFiles($p){ $r=[];$n=is_array($p['name'])?count($p['name']):0; for($i=0;$i<$n;$i++){ $r[$i]=['name'=>$p['name'][$i]??null,'type'=>$p['type'][$i]??null,'tmp_name'=>$p['tmp_name'][$i]??null,'error'=>$p['error'][$i]??UPLOAD_ERR_NO_FILE,'size'=>$p['size'][$i]??0]; } return $r; }
