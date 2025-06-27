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
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Pixel Admin Dashboard</title>
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
    
    .pixel-card {
      background: white;
      border: 4px solid #000;
      box-shadow: 6px 6px 0 rgba(0,0,0,0.1);
    }
    
    .pixel-table {
      border-collapse: separate;
      border-spacing: 0;
    }
    
    .pixel-table th {
      background: var(--pink);
      color: white;
      border: 3px solid #000;
      border-bottom: none;
    }
    
    .pixel-table td {
      border: 3px solid #000;
      border-top: none;
    }
    
    .pixel-table tr:hover td {
      background: #ffebf0;
    }
    
    .pixel-stat {
      background: white;
      border: 3px solid #000;
      box-shadow: 5px 5px 0 rgba(0,0,0,0.1);
    }
    
    .pixel-icon {
      width: 40px;
      height: 40px;
      border: 3px solid #000;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    
    .pixel-lightbox {
      border: 5px solid #000;
      box-shadow: 10px 10px 0 rgba(0,0,0,0.3);
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

<div class="max-w-7xl mx-auto relative z-10">
  <header class="flex flex-wrap items-center justify-between mb-8 gap-4">
    <h1 class="text-3xl sm:text-4xl title-font text-white">ADMIN DASHBOARD</h1>
    <div class="flex flex-wrap gap-3">
      <a href="upload.php" class="cute-btn">
        <i class="fas fa-plus mr-2"></i> UPLOAD
      </a>
      <a href="../index.php" class="cute-btn" style="background: var(--purple);">
        <i class="fas fa-globe mr-2"></i> VIEW SITE
      </a>
    </div>
  </header>

  <!-- Statistik -->
  <section class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6 mb-10">
    <div class="pixel-stat p-6 flex items-center justify-between">
      <div>
        <p class="text-gray-600 text-sm">TOTAL PHOTOS</p>
        <h2 class="text-2xl font-bold" style="color: var(--pink);"><?php echo $total; ?></h2>
      </div>
      <div class="pixel-icon" style="background: var(--pink);">
        <i class="fas fa-camera text-white"></i>
      </div>
    </div>
  </section>

<!-- Tabel Daftar Foto -->
<div class="pixel-card p-6">
  <h2 class="text-2xl title-font mb-6" style="color: var(--purple);">PHOTO GALLERY</h2>
  <div class="overflow-x-auto">
    <table class="pixel-table w-full rounded-lg overflow-hidden">
      <thead>
        <tr>
          <th class="px-4 py-3 text-left w-12">#</th>
          <th class="px-4 py-3 text-left min-w-[200px]">DESCRIPTION</th>
          <th class="px-4 py-3 text-left w-24">PHOTO</th>
          <th class="px-4 py-3 text-left w-32">DATE</th>
          <th class="px-4 py-3 text-left w-24">ACTIONS</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $no = 1;
        while($row = $data->fetch_assoc()):
        ?>
        <tr class="hover:bg-pink-50 transition-colors">
          <td class="px-4 py-3 font-mono"><?php echo $no++; ?></td>
          <td class="px-4 py-3 max-w-[300px] truncate" title="<?php echo htmlspecialchars($row['description']); ?>">
            <?php echo htmlspecialchars($row['description']); ?>
          </td>
          <td class="px-4 py-3">
            <img src="../uploads/<?php echo htmlspecialchars($row['filename']); ?>"
                onclick="openLightbox(this.src)"
                class="h-16 w-16 object-cover border-2 border-black cursor-pointer hover:scale-110 transition mx-auto">
          </td>
          <td class="px-4 py-3 font-mono text-sm">
            <?php echo date("d M Y", strtotime($row['created_at'])); ?>
          </td>
          <td class="px-4 py-3">
            <div class="flex items-center justify-center gap-3">
              <a href="edit.php?id=<?php echo $row['id']; ?>" title="Edit" class="text-blue-600 hover:text-blue-800 text-lg transition-colors">
                <i class="fas fa-pen-to-square"></i>
              </a>
              <button onclick="confirmDelete(<?php echo $row['id']; ?>)" title="Delete" class="text-red-600 hover:text-red-800 text-lg transition-colors">
                <i class="fas fa-trash-alt"></i>
              </button>
            </div>
          </td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>
</div>

<!-- Modal Lightbox -->
<div id="lightboxModal" class="fixed inset-0 bg-black bg-opacity-90 flex items-center justify-center z-50 hidden">
  <span onclick="closeLightbox()" class="absolute top-5 right-5 text-white text-3xl cursor-pointer hover:text-pink-400">&times;</span>
  <img id="lightboxImg" src="" class="pixel-lightbox max-w-3xl max-h-[80vh]">
</div>

<script>
function confirmDelete(id) {
  Swal.fire({
    title: 'DELETE PHOTO?',
    text: "This action cannot be undone!",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#ff9bb3',
    cancelButtonColor: '#b5a1ff',
    confirmButtonText: 'YES, DELETE',
    cancelButtonText: 'CANCEL',
    background: '#fff',
    customClass: {
      title: 'title-font',
      confirmButton: 'pixel-border',
      cancelButton: 'pixel-border'
    }
  }).then((result) => {
    if (result.isConfirmed) {
      window.location.href = 'delete.php?id=' + id;
    }
  })
}

function openLightbox(src) {
  document.getElementById('lightboxImg').src = src;
  document.getElementById('lightboxModal').classList.remove('hidden');
}

function closeLightbox() {
  document.getElementById('lightboxModal').classList.add('hidden');
}
</script>

</body>
</html>