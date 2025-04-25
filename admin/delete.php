<?php
include '../db.php';
session_start();
if (!isset($_SESSION['login'])) header("Location: dashboard.php");

$id = $_GET['id'];

// Hapus file foto di folder
$get = $conn->query("SELECT filename FROM photos WHERE id=$id")->fetch_assoc();
unlink("../uploads/" . $get['filename']);

// Hapus dari database
$conn->query("DELETE FROM photos WHERE id=$id");

header("Location: dashboard.php");
exit;
?>
