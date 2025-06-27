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
    $title = $_POST['title'] ?? '';
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
  <title>Pixel Upload</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&family=Short+Stack&display=swap" rel="stylesheet">

  <style>
    :root {
      --pink: #ff9bb3;
      --purple: #b5a1ff;
      --blue: #9bd4ff;
      --yellow: #ffe08a;
    }
    
    body {
      font-family: 'Short Stack', cursive;
      background-color: #fff5f7;
      image-rendering: pixelated;
    }
    
    .pixel-border {
      border: 4px solid #000;
      box-shadow: 8px 8px 0 rgba(0,0,0,0.2);
      position: relative;
    }
    
    .pixel-border:before {
      content: '';
      position: absolute;
      top: 2px;
      left: 2px;
      right: 2px;
      bottom: 2px;
      border: 2px solid white;
      pointer-events: none;
    }
    
    .cute-btn {
      background: var(--pink);
      color: white;
      border: 3px solid #000;
      padding: 10px 20px;
      font-size: 0.9rem;
      box-shadow: 5px 5px 0 rgba(0,0,0,0.2);
      transition: all 0.1s;
      font-family: 'Press Start 2P', cursive;
      text-shadow: 2px 2px 0 rgba(0,0,0,0.2);
    }
    
    .cute-btn:hover {
      transform: translate(2px, 2px);
      box-shadow: 3px 3px 0 rgba(0,0,0,0.2);
    }
    
    .cute-btn:active {
      transform: translate(4px, 4px);
      box-shadow: none;
    }
    
    .title-font {
      font-family: 'Press Start 2P', cursive;
      text-shadow: 3px 3px 0 var(--purple);
    }
    
    .pixel-input {
      border: 3px solid #000;
      padding: 8px 12px;
      font-family: 'Short Stack', cursive;
      box-shadow: 4px 4px 0 rgba(0,0,0,0.1);
    }
    
    .pixel-input:focus {
      outline: none;
      box-shadow: 4px 4px 0 var(--purple);
    }
    
    .pixel-file-input {
      border: 3px solid #000;
      padding: 8px;
      font-family: 'Short Stack', cursive;
      box-shadow: 4px 4px 0 rgba(0,0,0,0.1);
      background: white;
    }
    
    .pixel-file-input::file-selector-button {
      background: var(--blue);
      border: 3px solid #000;
      padding: 4px 8px;
      font-family: 'Press Start 2P', cursive;
      font-size: 0.7rem;
      margin-right: 10px;
    }
    
    .pixel-textarea {
      border: 3px solid #000;
      padding: 8px 12px;
      font-family: 'Short Stack', cursive;
      box-shadow: 4px 4px 0 rgba(0,0,0,0.1);
      resize: none;
    }
    
    .pixel-textarea:focus {
      outline: none;
      box-shadow: 4px 4px 0 var(--purple);
    }
    
    .pixel-preview {
      border: 3px solid #000;
      box-shadow: 4px 4px 0 rgba(0,0,0,0.1);
      object-fit: contain;
      background: white;
    }
  </style>
</head>
<body class="min-h-screen p-6" style="background: linear-gradient(to bottom right, var(--pink), var(--purple));">

<!-- Decorative elements -->
<div class="pixel-cloud" style="width: 80px; height: 40px; top: 5%; left: 5%;"></div>
<div class="pixel-cloud" style="width: 60px; height: 30px; top: 10%; right: 10%;"></div>
<div class="pixel-star" style="top: 15%; left: 15%;">✦</div>
<div class="pixel-star" style="top: 20%; right: 20%;">✦</div>

<div class="max-w-md mx-auto relative z-10">
  <header class="flex justify-center mb-8">
    <h1 class="text-3xl sm:text-4xl title-font text-white">UPLOAD PHOTO</h1>
  </header>

  <!-- SweetAlert -->
  <?php if (!empty($message)) : ?>
    <script>
      Swal.fire({
        icon: '<?= $status ?>',
        title: '<?= $status === "success" ? "SUCCESS!" : "ERROR!" ?>',
        text: '<?= $message ?>',
        confirmButtonText: 'OK',
        background: 'white',
        customClass: {
          title: 'title-font',
          confirmButton: 'pixel-border'
        }
      }).then(() => {
        <?php if ($status === "success") : ?>
          window.location.href = 'upload.php';
        <?php endif; ?>
      });
    </script>
  <?php endif; ?>

  <!-- Form Upload Foto -->
  <div class="pixel-border bg-white p-6">
    <form action="upload.php" method="POST" enctype="multipart/form-data">
      
      <!-- Deskripsi Foto -->
      <div class="mb-4">
        <label for="description" class="block text-sm font-medium mb-1">DESCRIPTION</label>
        <textarea name="description" id="description" required rows="4"
          class="pixel-textarea w-full"
          placeholder="Enter photo description"></textarea>
      </div>

      <!-- Pilih Foto -->
      <div class="mb-4">
        <label for="foto" class="block text-sm font-medium mb-1">CHOOSE PHOTO</label>
        <input type="file" name="foto" id="foto" accept="image/*" required
          class="pixel-file-input w-full">
      </div>

      <!-- Preview Gambar -->
      <div class="mb-4">
        <label class="block text-sm font-medium mb-1">PREVIEW</label>
        <img id="preview-image" class="w-full h-48 pixel-preview" src="#" alt="Preview" style="display: none;" />
      </div>

      <!-- Tombol -->
      <div class="grid grid-cols-1 gap-3 mt-6">
        <button type="submit" class="cute-btn">
          <i class="fas fa-upload mr-2"></i> UPLOAD
        </button>
        <a href="dashboard.php" class="cute-btn text-center" style="background: var(--purple);">
          <i class="fas fa-arrow-left mr-2"></i> BACK TO DASHBOARD
        </a>
      </div>

    </form>
  </div>
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