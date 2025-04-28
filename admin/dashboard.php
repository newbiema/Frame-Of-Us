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
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-gray-100 font-sans text-gray-800 min-h-screen">

<div class="p-6 max-w-7xl mx-auto">
  <header class="flex items-center justify-between mb-8">
    <h1 class="text-4xl font-extrabold text-pink-600">Dashboard Admin</h1>
    <div class="space-x-4">
      <a href="upload.php" class="bg-pink-500 hover:bg-pink-600 text-white px-5 py-2 rounded-lg font-semibold shadow transition">+ Tambah Foto</a>
      <a href="../index.php" class="bg-gray-700 hover:bg-gray-800 text-white px-5 py-2 rounded-lg font-semibold shadow transition">Lihat Web</a>
    </div>
  </header>

  <!-- Statistik -->
  <section class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
    <div class="bg-white rounded-lg shadow p-6 flex items-center justify-between">
      <div>
        <p class="text-gray-500 text-sm">Total Foto</p>
        <h2 class="text-2xl font-bold text-gray-800"><?php echo $total; ?> Foto</h2>
      </div>
      <div class="bg-pink-100 text-pink-600 rounded-full p-3">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path d="M3 5h18M3 10h18M9 15h6" />
        </svg>
      </div>
    </div>
  </section>

  <!-- Tabel Daftar Foto -->
  <div class="bg-white rounded-xl shadow-lg p-8">
    <h2 class="text-2xl font-bold mb-6 text-gray-800">Daftar Foto</h2>
    <div class="overflow-x-auto rounded-lg">
      <table class="min-w-full bg-white text-sm rounded-lg overflow-hidden">
        <thead class="bg-pink-200 text-gray-700">
          <tr>
            <th class="px-6 py-3 text-left">#</th>
            <th class="px-6 py-3 text-left">Deskripsi</th>
            <th class="px-6 py-3 text-left">Foto</th>
            <th class="px-6 py-3 text-left">Tanggal</th>
            <th class="px-6 py-3 text-left">Aksi</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          <?php
          $no = 1;
          while($row = $data->fetch_assoc()):
          ?>
          <tr class="hover:bg-pink-50 transition">
            <td class="px-6 py-4"><?php echo $no++; ?></td>
            <td class="px-6 py-4"><?php echo htmlspecialchars($row['description']); ?></td>
            <td class="px-6 py-4">
              <img src="../uploads/<?php echo htmlspecialchars($row['filename']); ?>" class="h-16 w-16 object-cover rounded-lg border">
            </td>
            <td class="px-6 py-4"><?php echo date("d M Y", strtotime($row['created_at'])); ?></td>
            <td class="px-6 py-4 space-x-2">
              <a href="edit.php?id=<?php echo $row['id']; ?>" class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded-lg text-xs font-medium shadow transition">Edit</a>
              <button onclick="confirmDelete(<?php echo $row['id']; ?>)" class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded-lg text-xs font-medium shadow transition">Hapus</button>
            </td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
function confirmDelete(id) {
  Swal.fire({
    title: 'Yakin ingin menghapus?',
    text: "Data yang dihapus tidak dapat dikembalikan!",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#e3342f',
    cancelButtonColor: '#6b7280',
    confirmButtonText: 'Ya, hapus!',
    cancelButtonText: 'Batal'
  }).then((result) => {
    if (result.isConfirmed) {
      window.location.href = 'delete.php?id=' + id;
    }
  })
}
</script>

</body>
</html>
