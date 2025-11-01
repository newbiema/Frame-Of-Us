<?php
include '../db.php';
session_start();

if (!isset($_SESSION['login'])) {
    header("Location: login.php");
    exit;
}

$id = intval($_GET['id']);
$photo = $conn->query("SELECT * FROM photos WHERE id = $id")->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $desc = $conn->real_escape_string($_POST['description']);

    if ($_FILES['new_photo']['name']) {
        $filename = $_FILES['new_photo']['name'];
        $tmp = $_FILES['new_photo']['tmp_name'];
        $path = "../uploads/" . basename($filename);

        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $file_type = mime_content_type($tmp);

        if (in_array($file_type, $allowed_types)) {
            // Delete old file if it exists
            if (file_exists("../uploads/".$photo['filename'])) {
                unlink("../uploads/".$photo['filename']);
            }
            move_uploaded_file($tmp, $path);
            $conn->query("UPDATE photos SET filename='$filename', description='$desc' WHERE id=$id");
        } else {
            $error = "Format file tidak didukung (hanya JPG/PNG/GIF).";
        }
    } else {
        $conn->query("UPDATE photos SET description='$desc' WHERE id=$id");
    }

    header("Location: dashboard.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Pixel Edit Photo</title>
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
      padding: 8px 16px;
      font-size: 0.8rem;
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
    
    .pixel-cloud {
      position: absolute;
      background: white;
      border: 3px solid #000;
      border-radius: 50%;
    }
    
    .pixel-star {
      position: absolute;
      color: var(--yellow);
      text-shadow: 2px 2px 0 rgba(0,0,0,0.2);
      animation: twinkle 2s infinite alternate;
    }
    
    .error-message {
      color: #ff3333;
      font-family: 'Press Start 2P', cursive;
      font-size: 0.7rem;
      text-shadow: 1px 1px 0 rgba(0,0,0,0.1);
    }
    
    @keyframes twinkle {
      from { opacity: 0.6; transform: scale(1); }
      to { opacity: 1; transform: scale(1.2); }
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
  <header class="flex justify-center mb-6">
    <h1 class="text-2xl sm:text-3xl title-font text-white">EDIT PHOTO</h1>
  </header>

  <!-- Error message -->
  <?php if (isset($error)): ?>
    <div class="pixel-border bg-white p-3 mb-4">
      <p class="error-message">! <?php echo $error; ?> !</p>
    </div>
  <?php endif; ?>

  <!-- Edit Form -->
  <div class="pixel-border bg-white p-5">
    <form method="POST" enctype="multipart/form-data">
      <!-- Description -->
      <div class="mb-4">
        <label class="block text-sm font-medium mb-1">DESCRIPTION</label>
        <textarea name="description" required class="pixel-textarea w-full" rows="4"><?php echo htmlspecialchars($photo['description']); ?></textarea>
      </div>

      <!-- Current Photo -->
      <div class="mb-4">
        <label class="block text-sm font-medium mb-1">CURRENT PHOTO</label>
        <img src="../uploads/<?php echo $photo['filename']; ?>" class="w-full h-48 pixel-preview" alt="Current Photo">
      </div>

      <!-- New Photo -->
      <div class="mb-5">
        <label class="block text-sm font-medium mb-1">NEW PHOTO (OPTIONAL)</label>
        <input type="file" name="new_photo" accept="image/*" class="pixel-file-input w-full">
      </div>

      <!-- Buttons -->
      <div class="flex justify-between">
        <button type="submit" class="cute-btn">
          <i class="fas fa-save mr-2"></i> SAVE
        </button>
        <a href="dashboard.php" class="cute-btn" style="background: var(--purple);">
          <i class="fas fa-times mr-2"></i> CANCEL
        </a>
      </div>
    </form>
  </div>
</div>

</body>
</html>