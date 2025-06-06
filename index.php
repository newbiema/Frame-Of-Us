<?php
 include 'db.php'; 
 
// Ambil IP Address pengunjung
function getUserIP() {
  if (!empty($_SERVER['HTTP_CLIENT_IP'])) return $_SERVER['HTTP_CLIENT_IP'];
  if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) return $_SERVER['HTTP_X_FORWARDED_FOR'];
  return $_SERVER['REMOTE_ADDR'];
}

$ip = getUserIP();
$now = date('Y-m-d H:i:s');

// Cek apakah IP sudah ada
$check = $conn->query("SELECT * FROM visitors WHERE ip_address = '$ip'");

if ($check->num_rows > 0) {
  // Update waktu kunjungan terakhir
  $conn->query("UPDATE visitors SET last_visit = '$now' WHERE ip_address = '$ip'");
} else {
  // Masukkan pengunjung baru
  $conn->query("INSERT INTO visitors (ip_address, last_visit) VALUES ('$ip', '$now')");
}

// Hitung total visitor (unique IP)
$totalVisitorsResult = $conn->query("SELECT COUNT(*) AS total FROM visitors");
$totalVisitors = $totalVisitorsResult->fetch_assoc()['total'];

// Hitung visitor online (yang aktif dalam 5 menit terakhir)
$timeLimit = date('Y-m-d H:i:s', strtotime('-5 minutes'));
$onlineVisitorsResult = $conn->query("SELECT COUNT(*) AS online FROM visitors WHERE last_visit >= '$timeLimit'");
$onlineVisitors = $onlineVisitorsResult->fetch_assoc()['online'];

 ?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Frames of Us</title>
  <link href="https://fonts.googleapis.com/css2?family=Great+Vibes&family=Inter:wght@400;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Comic+Neue:wght@400;700&family=Quicksand:wght@400;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link rel="stylesheet" href="style.css">
  <script src="https://cdn.jsdelivr.net/npm/typed.js@2.0.12"></script>
  <script src="js/like.js "></script>
  <link href="https://unpkg.com/aos@2.3.4/dist/aos.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <script src="https://cdn.tailwindcss.com"></script>

</head>
<body class="bg-pink-50 text-gray-800">

<!-- Header -->
<header class="text-center py-12 bg-gradient-to-r from-pink-200 to-pink-400">
  <h1 class="text-5xl md:text-6xl title-font text-pink-600 mb-4">
    Frames of Us
  </h1>
<p class="text-gray-600 title-font md:text-lg mb-8">
  <span id="typed-text"></span>
</p>

  <a href="#gallery" class="inline-block bg-pink-500 hover:bg-pink-600 text-white text-sm md:text-base font-medium py-3 px-6 rounded-full shadow-lg transition-transform transform hover:scale-105">
    View Gallery
  </a>

  <!-- Visitors Section -->
  <div class="flex flex-col items-center mt-8">
    <div class="flex flex-wrap gap-6 justify-center items-center bg-white/50 backdrop-blur-md shadow-md rounded-xl px-6 py-4 w-fit text-sm text-gray-600 font-medium">
      
      <div class="flex items-center gap-2">
        <i class="fas fa-user text-pink-400"></i>
        <span>Total Visitors:</span>
        <span id="totalVisitors" class="text-pink-500 font-bold"><?php echo $totalVisitors; ?></span>
      </div>

      <div class="flex items-center gap-2">
        <i class="fas fa-signal text-pink-400"></i>
        <span>Online Now:</span>
        <span id="onlineVisitors" class="text-pink-500 font-bold"><?php echo $onlineVisitors; ?></span>
      </div>

    </div>
  </div>
</header>


<!-- Gallery Section -->
<main id="gallery" class="max-w-7xl mx-auto px-4 sm:px-6 py-12">
  <!-- Gallery Grid -->
  <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
    <?php
    $result = $conn->query("SELECT * FROM photos ORDER BY uploaded_at DESC");
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $uploadDate = date('M j, Y', strtotime($row['uploaded_at']));
            echo "
            <div class='group relative overflow-hidden rounded-xl bg-white shadow-md hover:shadow-lg transition-all duration-300 transform hover:-translate-y-1'
                 data-aos='zoom-in-up'
                 onclick=\"openModal('uploads/{$row['filename']}', '{$row['description']}', {$row['id']}, '{$uploadDate}', {$row['likes']})\"
                 role='button'
                 aria-label='View photo details'>
                 
              <div class='relative overflow-hidden aspect-square'>
                <img src='uploads/{$row['filename']}' alt='{$row['description']}'
                    class='w-full h-full object-cover transition-transform duration-500 group-hover:scale-105'
                    loading='lazy' />
                
                <div class='absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300'></div>
                
                <div class='absolute top-3 right-3 bg-white/90 text-pink-500 px-2 py-1 rounded-full text-xs font-semibold flex items-center opacity-0 group-hover:opacity-100 transition-opacity duration-300 shadow-sm'>
                  <i class='fas fa-expand mr-1'></i> View
                </div>
              </div>

              <div class='p-4'>
                <div class='flex justify-between items-center mb-3'>
                    <button onclick='event.stopPropagation(); likePhoto({$row['id']})'
                            id='like-button-{$row['id']}'
                          class='like-button flex items-center space-x-1 bg-pink-50 hover:bg-pink-100 text-pink-600 font-medium py-1 px-3 rounded-full transition duration-200 active:scale-95 focus:outline-none'
                          data-photo-id='{$row['id']}'
                          aria-label='Like this photo'>
                    <i class='far fa-heart'></i>
                    <span class='likes-count text-sm' id='likes-{$row['id']}'>{$row['likes']}</span>

                  </button>

                  <span class='text-xs text-gray-500 font-medium'>
                    <i class='far fa-clock mr-1'></i>{$uploadDate}
                  </span>
                </div>

                <p class='font-poppins text-center text-gray-700 text-sm line-clamp-2 transition-colors duration-300 group-hover:text-gray-900'>
                  {$row['description']}
                </p>
              </div>
            </div>";
        }
    } else {
        echo "
        <div class='col-span-full text-center py-16' data-aos='fade-up'>
          <div class='mx-auto w-24 h-24 bg-pink-100 rounded-full flex items-center justify-center mb-4 shadow-inner'>
            <i class='fas fa-camera text-pink-500 text-3xl'></i>
          </div>
          <h3 class='text-xl font-medium text-gray-600 mb-2 font-poppins'>No Photos Yet</h3>
          <p class='text-gray-500 max-w-md mx-auto font-poppins'>Be the first to share your memories! Upload a photo to get started.</p>
        </div>";
    }
    ?>
  </div>
</main>


  <!-- Modal untuk klik foto -->
  <div id="photoModal" class="hidden fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50">
    <div class="bg-white rounded-2xl overflow-hidden max-w-md mx-auto p-6 relative">
      <button onclick="closeModal()" class="absolute top-2 right-2 text-gray-500 hover:text-gray-800">&times;</button>

      <div id="modalLoading" class="flex items-center justify-center h-60 space-x-2">
        <div class="heart bg-pink-500"></div>
        <div class="heart bg-pink-400 animation-delay-200"></div>
        <div class="heart bg-pink-300 animation-delay-400"></div>
      </div>

      <img id="modalImage" src="" class="w-full rounded-lg mb-4 hidden opacity-0">
      <p id="modalDesc" class="text-center cute-font text-pink-500 hidden opacity-0"></p>

    </div>
  </div>

<!-- Kontrol Musik -->
<div class="fixed bottom-6 right-6 z-50 group">
  <button id="toggleMusic" class="bg-transparent border-2 border-pink-400 hover:border-pink-600 text-pink-500 p-3 rounded-full shadow-lg backdrop-blur-md transition-transform transform hover:scale-110 active:scale-95 focus:outline-none relative">
    <!-- Font Awesome Icon -->
    <i id="musicIcon" class="fas fa-play fa-lg"></i>
    
    <!-- Tooltip -->
    <span class="absolute -top-10 left-1/2 transform -translate-x-1/2 text-sm text-pink-700 opacity-0 group-hover:opacity-100 transition duration-300">Toggle Music</span>
  </button>
</div>

<audio id="backgroundMusic" loop>
  <source src="music/nothing.mp3" type="audio/mpeg">
  Browser tidak mendukung audio.
</audio>


<footer class="title-font bg-gradient-to-br from-pink-300 via-pink-400 to-pink-500 text-white border-t border-pink-200">
  <div class="max-w-6xl mx-auto px-6 py-12 space-y-8 text-center">
    
    <!-- Brand & Year (font tidak diubah) -->
    <div>
      <p class="text-2xl font-bold tracking-wide">
        &copy; <?= date('Y') ?> <span class="text-pink-100">Frames of Us</span>
      </p>
    </div>

    <!-- Social Links - lebih rapi -->
    <div class="flex justify-center gap-6">
      <a href="https://www.instagram.com/n4ve.666/" 
         class="p-3 rounded-lg bg-pink-100/10 hover:bg-pink-100/20 transition-all duration-200">
        <i class="fab fa-instagram text-2xl text-pink-100"></i>
      </a>
      <a href="https://github.com/newbiema" 
         class="p-3 rounded-lg bg-pink-100/10 hover:bg-pink-100/20 transition-all duration-200">
        <i class="fab fa-github text-2xl text-pink-100"></i>
      </a>
    </div>

    <!-- Clock - lebih sederhana tapi elegan -->
    <div class="py-2">
      <div class="text-lg font-mono text-pink-100 inline-block px-4 py-2 rounded-lg bg-pink-100/10">
        <span id="clock">00:00:00</span> WIB
      </div>
    </div>

    <!-- Credit Line - lebih minimalis -->
    <div class="pt-6">
      <p class="text-base text-pink-100">
        Made with 
        <span class="inline-block animate-pulse text-pink-200 mx-1">❤️</span> 
        by <span class="font-semibold text-white">Evan</span>
      </p>
    </div>

  </div>

</footer>

<!-- AOS Library -->
<script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>

<!-- Confetti JS Library -->
<script src="https://cdn.jsdelivr.net/npm/confetti-js@0.0.18/dist/index.min.js"></script>

<!-- Custom Script -->
<script>
  // Inisialisasi AOS
  AOS.init();

// Jalankan animasi pas load
document.addEventListener('DOMContentLoaded', () => {
    animateValue("totalVisitors", 0, <?php echo $totalVisitors; ?>, 2000);
    animateValue("onlineVisitors", 0, <?php echo $onlineVisitors; ?>, 1500);
});

// Fungsi Like Foto
  function likePhoto(photoId) {
    if (localStorage.getItem('liked-' + photoId)) {
      Swal.fire({
        icon: 'info',
        title: 'Oops!',
        text: 'Kamu sudah love foto ini! ❤️',
        confirmButtonColor: '#ec4899',
        confirmButtonText: 'Mengerti',
      });
      return;
    }

    const likeButton = document.getElementById('like-button-' + photoId);
    likeButton.classList.add('scale-125');

    fetch('../like.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: 'photo_id=' + photoId,
    })
    .then(response => response.text())
    .then(data => {
      document.getElementById('likes-' + photoId).textContent = data;

      likeButton.classList.add('bg-pink-300', 'rounded-full', 'text-white', 'shadow-lg');
      likeButton.innerHTML = '💖 ' + data;

      localStorage.setItem('liked-' + photoId, true);

      triggerConfetti();

      setTimeout(() => {
        likeButton.classList.remove('scale-125');
      }, 300);
    })
    .catch(error => {
      console.error('Error:', error);
      Swal.fire({
        icon: 'error',
        title: 'Gagal!',
        text: 'Ada masalah saat memberikan like. Coba lagi nanti.',
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Tutup',
      });
    });
  }

</script>
<script src="js/modal.js"></script>
<script src="js/counter.js"></script>
<script src="js/clock.js"></script>
<script src="js/confetti.js"></script>
<script src="js/buttonmusic.js"></script>
<script src="js/typed.js"></script>

<!-- Canvas untuk Confetti -->
<canvas id="confetti-canvas" class="fixed top-0 left-0 w-full h-full pointer-events-none z-50"></canvas>

</body>
</html>
