<?php
session_start();
if (!isset($_SESSION['login'])) {
  header("Location: login.php");
  exit;
}
require_once __DIR__ . '/../db.php';

$musicDir = realpath(__DIR__.'/../music');
if (!$musicDir) $musicDir = __DIR__.'/../music';

$file = basename($_GET['file'] ?? '');
if (!$file) { header("Location: music.php?s=error&m=".urlencode("No file.")); exit; }

$path = rtrim($musicDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $file;

// Cek apakah ini file yang sedang aktif
$current = '';
if ($st=$conn->prepare("SELECT v FROM settings WHERE k='bg_music' LIMIT 1")){
  $st->execute(); $r=$st->get_result(); if($r && $row=$r->fetch_assoc()) $current=$row['v'] ?? ''; $st->close();
}

if ($current === $file) {
  // Clear setting jika file aktif dihapus
  $conn->query("DELETE FROM settings WHERE k='bg_music'");
  $conn->query("DELETE FROM settings WHERE k='bg_music_title'");
}

if (is_file($path)){
  @unlink($path);
  header("Location: music.php?s=success&m=".urlencode("Deleted $file"));
  exit;
}

header("Location: music.php?s=error&m=".urlencode("File not found."));
