<?php
// like.php
header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/db.php'; // $conn (mysqli)

function clientIP(): string {
  if (!empty($_SERVER['HTTP_CLIENT_IP'])) return $_SERVER['HTTP_CLIENT_IP'];
  if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
    return trim($parts[0]);
  }
  return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function anonFingerprint(): string {
  $ip = clientIP();
  if (!filter_var($ip, FILTER_VALIDATE_IP)) $ip = '0.0.0.0';
  $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
  // “seumur hidup” 1 like per device per foto:
  $raw = $ip . '|' . $ua;
  return hash('sha256', $raw);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo '0';
  exit;
}

$photoId = isset($_POST['photo_id']) ? (int)$_POST['photo_id'] : 0;
if ($photoId <= 0) {
  http_response_code(400);
  echo '0';
  exit;
}

// Pastikan foto ada
$exists = 0;
if ($stmt = $conn->prepare('SELECT 1 FROM photos WHERE id = ? LIMIT 1')) {
  $stmt->bind_param('i', $photoId);
  $stmt->execute();
  $stmt->store_result();
  $exists = $stmt->num_rows > 0 ? 1 : 0;
  $stmt->close();
}
if (!$exists) { echo '0'; exit; }

// Simpan like publik (anonymous)
$fingerprint = anonFingerprint();
if ($stmt = $conn->prepare('INSERT IGNORE INTO photo_likes_public (photo_id, fingerprint) VALUES (?, ?)')) {
  $stmt->bind_param('is', $photoId, $fingerprint);
  $stmt->execute();
  $stmt->close();
}

// Hitung total likes = admin (login) + publik (anon)
$total = 0;

// 1) publik
if ($stmt = $conn->prepare('SELECT COUNT(*) FROM photo_likes_public WHERE photo_id = ?')) {
  $stmt->bind_param('i', $photoId);
  $stmt->execute();
  $stmt->bind_result($cnt);
  if ($stmt->fetch()) $total += (int)$cnt;
  $stmt->close();
}

// 2) admin (jika kamu pakai tabel photo_likes untuk like oleh admin/login)
if ($stmt = $conn->prepare('SELECT COUNT(*) FROM photo_likes WHERE photo_id = ?')) {
  $stmt->bind_param('i', $photoId);
  $stmt->execute();
  $stmt->bind_result($cnt2);
  if ($stmt->fetch()) $total += (int)$cnt2;
  $stmt->close();
}

// Sinkronkan counter di photos
if ($stmt = $conn->prepare('UPDATE photos SET likes = ? WHERE id = ?')) {
  $stmt->bind_param('ii', $total, $photoId);
  $stmt->execute();
  $stmt->close();
}

// Return angka saja (frontend kamu membaca plain text)
echo (string)$total;
