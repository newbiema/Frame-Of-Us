<?php
session_start();
if (!isset($_SESSION['login'])) {
    header("Location: login.php");
    exit;
}
include '../db.php';

// Statistik jumlah total foto
$total = $conn->query("SELECT COUNT(*) AS total FROM photos")->fetch_assoc()['total'];

// Ambil semua data foto
$data = $conn->query("SELECT * FROM photos ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Dashboard Admin</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 font-sans text-gray-800">

<div class="p-6 max-w-6xl mx-auto">
  <h1 class="text-3xl font-bold text-pink-600 mb-6">Dashboard Admin</h1>

  <!-- Tombol Upload -->
  <div class="mb-4">
    <a href="upload.php" class="bg-pink-500 hover:bg-pink-600 text-white px-4 py-2 rounded shadow">+ Tambah Foto</a>
    <a href="../index.php" class="bg-pink-500 hover:bg-pink-600 text-white px-4 py-2 rounded shadow">Lihat Web</a>
  </div>

  <!-- Tabel Daftar Foto -->
  <div class="bg-white rounded shadow p-6">
    <h2 class="text-xl font-bold mb-4">Daftar Foto</h2>
    <div class="overflow-auto">
      <table class="w-full text-left table-auto border">
        <thead class="bg-pink-100 text-sm">
          <tr>
            <th class="px-4 py-2 border">#</th>
            <th class="px-4 py-2 border">Deskripsi</th>
            <th class="px-4 py-2 border">Foto</th>
            <th class="px-4 py-2 border">Tanggal</th>
            <th class="px-4 py-2 border">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $no = 1;
          while($row = $data->fetch_assoc()):
          ?>
          <tr class="border-t">
            <td class="px-4 py-2 border"><?php echo $no++; ?></td>
            <td class="px-4 py-2 border"><?php echo $row['description']; ?></td>
            <td class="px-4 py-2 border">
              <img src="../uploads/<?php echo $row['filename']; ?>" class="h-12 w-12 object-cover rounded">
            </td>
            <td class="px-4 py-2 border"><?php echo date("d M Y", strtotime($row['created_at'])); ?></td>
            <td class="px-4 py-2 border space-x-2">
              <a href="edit.php?id=<?php echo $row['id']; ?>" class="bg-blue-500 text-white px-2 py-1 rounded hover:bg-blue-600 text-sm">Edit</a>
              <a href="delete.php?id=<?php echo $row['id']; ?>" class="bg-red-500 text-white px-2 py-1 rounded hover:bg-red-600 text-sm" onclick="return confirm('Yakin ingin menghapus?')">Hapus</a>
            </td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

</body>
</html>
