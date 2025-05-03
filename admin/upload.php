<?php
session_start();
if (!isset($_SESSION['login'])) {
    header("Location: login.php");
    exit;
}

include '../db.php';

$message = "";
$status = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = $_POST['title'] ?? ''; // opsional
    $description = $_POST['description'];
    $foto = $_FILES['foto']['name'];
    $tmp = $_FILES['foto']['tmp_name'];
    $path = "../uploads/" . $foto;

    // Validasi file
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $file_type = mime_content_type($tmp);

    if (!in_array($file_type, $allowed_types)) {
        $message = "Hanya file gambar (jpg/png/gif) yang diperbolehkan.";
        $status = "error";
    } elseif (move_uploaded_file($tmp, $path)) {
        $query = "INSERT INTO photos (title, description, filename) VALUES ('$title', '$description', '$foto')";
        if ($conn->query($query)) {
            $message = "Upload berhasil!";
            $status = "success";
        } else {
            $message = "Gagal menyimpan ke database.";
            $status = "error";
        }
    } else {
        $message = "Gagal mengupload file.";
        $status = "error";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Upload Foto</title>
  <link href="https://fonts.googleapis.com/css2?family=Great+Vibes&family=Inter:wght@400;600&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-pink-50 text-gray-800">

  <!-- SweetAlert -->
  <?php if (!empty($message)) : ?>
    <script>
      Swal.fire({
        icon: '<?= $status ?>',
        title: '<?= $status === "success" ? "Berhasil!" : "Oops..." ?>',
        text: '<?= $message ?>',
        confirmButtonText: 'OK'
      }).then(() => {
        <?php if ($status === "success") : ?>
          window.location.href = 'upload.php';
        <?php endif; ?>
      });
    </script>
  <?php endif; ?>

  <!-- Form Upload Foto -->
  <div class="max-w-md mx-auto mt-12 p-6 bg-white rounded-lg shadow-lg">
    <h1 class="text-2xl font-bold text-center text-pink-600 mb-4">Upload Foto</h1>
    <form action="upload.php" method="POST" enctype="multipart/form-data">
      
      <!-- Deskripsi Foto -->
      <div class="mb-4">
        <label for="description" class="block text-sm font-medium text-gray-700">Deskripsi Foto</label>
        <textarea name="description" id="description" required rows="4"
          class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-pink-500"
          placeholder="Masukkan deskripsi foto"></textarea>
      </div>

      <!-- Pilih Foto -->
      <div class="mb-4">
        <label for="foto" class="block text-sm font-medium text-gray-700">Pilih Foto</label>
        <input type="file" name="foto" id="foto" accept="image/*" required
          class="mt-1 block w-full text-sm text-gray-700 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-pink-500">
      </div>

      <!-- Preview Gambar -->
      <div class="mb-4">
        <label class="block text-sm font-medium text-gray-700">Preview</label>
        <img id="preview-image" class="w-full h-48 object-cover rounded-md border" src="#" alt="Preview" style="display: none;" />
      </div>

      <!-- Tombol -->
      <div class="grid grid-cols-1 gap-3 mt-4">
        <button type="submit"
          class="w-full py-2 bg-pink-600 text-white font-semibold rounded-lg shadow-md hover:bg-pink-700 transition">
          Upload Foto
        </button>
        <a href="../index.php">
          <button type="button"
            class="w-full py-2 border border-pink-600 text-pink-600 font-semibold rounded-lg shadow-md hover:bg-pink-100 transition">
            Lihat Web
          </button>
        </a>
      </div>

    </form>
  </div>

  <!-- Script Preview Gambar -->
  <script>
    document.getElementById('foto').addEventListener('change', function(event) {
      const reader = new FileReader();
      reader.onload = function(e) {
        const preview = document.getElementById('preview-image');
        preview.src = e.target.result;
        preview.style.display = 'block';
      };
      reader.readAsDataURL(event.target.files[0]);
    });
  </script>
</body>
</html>
