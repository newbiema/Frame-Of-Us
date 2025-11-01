<?php
require_once __DIR__ . '/db.php'; // mysqli $conn

header('Content-Type: text/plain');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Method Not Allowed'); }

$album_id = isset($_POST['album_id']) ? (int)$_POST['album_id'] : 0;
if ($album_id <= 0) { http_response_code(400); exit('Bad Request'); }

$stmt = $conn->prepare('UPDATE albums SET likes = likes + 1 WHERE id = ?');
$stmt->bind_param('i', $album_id);
$stmt->execute();

$stmt = $conn->prepare('SELECT likes FROM albums WHERE id = ?');
$stmt->bind_param('i', $album_id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();

echo (string)($row['likes'] ?? 0);
