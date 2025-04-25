<?php
include '../db.php';
session_start();
if (!isset($_SESSION['login'])) header("Location: login.php");

$id = $_GET['id'];
$photo = $conn->query("SELECT * FROM photos WHERE id=$id")->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $desc = $_POST['description'];

  if ($_FILES['new_photo']['name']) {
    $filename = $_FILES['new_photo']['name'];
    $tmp = $_FILES['new_photo']['tmp_name'];
    move_uploaded_file($tmp, "../uploads/" . $filename);
    $conn->query("UPDATE photos SET filename='$filename', description='$desc' WHERE id=$id");
  } else {
    $conn->query("UPDATE photos SET description='$desc' WHERE id=$id");
  }

  header("Location: dashboard.php");
  exit;
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>Edit Foto</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-6">
  <div class="max-w-lg mx-auto bg-white p-6 rounded shadow">
    <h1 class="text-2xl font-bold mb-4">Edit Foto</h1>
    <form method="POST" enctype="multipart/form-data">
      <div class="mb-4">
        <label class="block text-sm">Deskripsi</label>
        <textarea name="description" class="w-full border p-2"><?php echo $photo['description']; ?></textarea>
      </div>
      <div class="mb-4">
        <label class="block text-sm">Ganti Foto (Opsional)</label>
        <input type="file" name="new_photo" class="w-full">
      </div>
      <div class="flex justify-between">
        <button class="bg-blue-500 text-white px-4 py-2 rounded" type="submit">Simpan</button>
        <a href="dashboard.php" class="text-gray-500">Batal</a>
      </div>
    </form>
  </div>
</body>
</html>
