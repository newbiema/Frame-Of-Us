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
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link href="https://unpkg.com/aos@2.3.4/dist/aos.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <script src="https://cdn.tailwindcss.com"></script>

  <style>

    html {
      scroll-behavior: smooth;
    }

    body {
      font-family: 'Inter', sans-serif;
    }
    .title-font {
      font-family: 'Great Vibes', cursive;
    }
    .cute-font {
      font-family: 'Comic Neue', 'Quicksand', cursive;
    }
    .gallery-item {
      position: relative;
      transition: transform 0.3s ease-in-out, box-shadow 0.3s ease;
    }
    .gallery-item:hover {
      transform: scale(1.05);
      box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
    }
    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }
    /* Heart Spinner */
    .heart {
      width: 24px;
      height: 24px;
      background-color: pink;
      transform: rotate(-45deg);
      animation: heartbeat 1s infinite;
      position: relative;
    }
    .heart::before,
    .heart::after {
      content: "";
      width: 24px;
      height: 24px;
      background-color: inherit;
      border-radius: 50%;
      position: absolute;
    }
    .heart::before {
      top: -12px;
      left: 0;
    }
    .heart::after {
      left: 12px;
      top: 0;
    }
    @keyframes heartbeat {
      0%, 100% {
        transform: scale(1) rotate(-45deg);
      }
      50% {
        transform: scale(1.3) rotate(-45deg);
      }
    }
    .animation-delay-200 {
      animation-delay: 0.2s;
    }
    .animation-delay-400 {
      animation-delay: 0.4s;
    }

    @keyframes ping-once {
      0% { transform: scale(1); opacity: 1; }
      50% { transform: scale(1.3); opacity: 0.75; }
      100% { transform: scale(1); opacity: 1; }
    }
    .animate-ping-once {
      animation: ping-once 0.6s ease-in-out;
    }

  </style>
</head>
<body class="bg-pink-50 text-gray-800">

<!-- Header -->
<header class="text-center py-12 bg-gradient-to-r from-pink-200 to-pink-400">
  <h1 class="text-5xl md:text-6xl title-font text-pink-600 mb-4">
    Frames of Us
  </h1>
  <p class="text-gray-600 title-font md:text-lg mb-8">
    Discover our beautiful collection of unforgettable moments.
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


<!-- Gallery -->
<main id="gallery" class="max-w-7xl mx-auto px-6 py-12">
  
  <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-6">
    <?php
    $result = $conn->query("SELECT * FROM photos ORDER BY uploaded_at DESC");

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
          echo "
            <div class='group relative overflow-hidden rounded-3xl border border-indigo-200 bg-white shadow-md hover:shadow-2xl transition duration-300 gallery-item' data-aos='fade-up' onclick=\"openModal('uploads/{$row['filename']}', '{$row['description']}')\">
              <img src='uploads/{$row['filename']}' alt='{$row['title']}' 
                  class='w-full h-60 object-cover rounded-t-3xl group-hover:scale-105 transition-transform duration-300' />
              <div class='p-5 text-center flex flex-col items-center space-y-4'>

                <!-- Tombol Like -->
                <button onclick=\"event.stopPropagation(); likePhoto({$row['id']})\"
                        id=\"like-button-{$row['id']}\"
                        class=\"flex items-center justify-center space-x-2 bg-pink-100 hover:bg-pink-200 text-pink-500 font-semibold py-2 px-4 rounded-full transition duration-300 active:scale-110 focus:outline-none\">
                  <svg id=\"heart-icon-{$row['id']}\" xmlns=\"http://www.w3.org/2000/svg\" class=\"h-6 w-6 fill-current\" viewBox=\"0 0 24 24\">
                    <path d=\"M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 
                      6.5 3.5 5 5.5 5c1.54 0 3.04 1.04 3.57 2.36h1.87C13.46 
                      6.04 14.96 5 16.5 5 18.5 5 20 6.5 20 8.5c0 3.78-3.4 
                      6.86-8.55 11.54L12 21.35z\"/>
                  </svg>
                  <span id=\"likes-{$row['id']}\" class=\"text-lg\">{$row['likes']}</span>
                </button>

                <!-- Deskripsi Foto -->

                <p class='flex items-center justify-center gap-2 bg-pink-50/80 text-pink-600 text-sm cute-font px-4 py-2 rounded-xl shadow-inner opacity-80 group-hover:opacity-100 translate-y-2 group-hover:translate-y-0 transition-all duration-300 ease-in-out'>
                  {$row['description']}
                </p>


              </div>
            </div>
          ";
        }
    } else {
        echo "<p class='text-center col-span-full text-lg text-gray-500 italic'>Belum ada foto yang diunggah.</p>";
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
    <svg id="musicIcon" xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 fill-current" viewBox="0 0 24 24">
      <path id="musicPath" d="M12 3v10.55A4 4 0 1 0 14 17V7h4V3h-6z"/>
    </svg>
    <span class="absolute -top-10 left-1/2 transform -translate-x-1/2 text-sm text-pink-700 opacity-0 group-hover:opacity-100 transition duration-300">Toggle Music</span>
  </button>
</div>

  <audio id="backgroundMusic" loop>
    <source src="music/nothing.mp3" type="audio/mpeg">
    Browser tidak mendukung audio.
  </audio>

<!-- Footer -->
<footer class="bg-pink-100 mt-16 py-8 text-center text-gray-500 text-sm border-t border-pink-300">
  <div class="max-w-4xl mx-auto space-y-8">
    
    <p>&copy; 2025 <span class="font-semibold text-pink-500">Frames of Us</span></p>

    <div class="flex justify-center gap-5 text-pink-400 text-xl">
      <a href="https://www.instagram.com/n4ve.666/" class="hover:text-pink-600 transition transform hover:scale-110">
        <i class="fab fa-instagram"></i>
      </a>
      <a href="https://github.com/newbiema" class="hover:text-pink-600 transition transform hover:scale-110">
        <i class="fab fa-github"></i>
      </a>
    </div>

    <p class="flex items-center justify-center gap-1 text-gray-500 text-base">
      Made with <span class="text-pink-400 animate-pulse">❤️</span> by 
      <span class="font-semibold text-gray-700">Evan</span>.
    </p>
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

  const music = document.getElementById('backgroundMusic');
  const toggleBtn = document.getElementById('toggleMusic');
  const musicIcon = document.getElementById('musicIcon');
  const musicPath = document.getElementById('musicPath');
  let isPlaying = false;

  toggleBtn.addEventListener('click', () => {
    if (isPlaying) {
      music.pause();
      // Ganti ICON ke "mute"
      musicPath.setAttribute('d', 'M16.5 12c0-1.77-.73-3.37-1.91-4.53L12 3v18l2.59-4.47C15.77 15.37 16.5 13.77 16.5 12z');
    } else {
      music.play();
      // Ganti ICON ke "music note"
      musicPath.setAttribute('d', 'M12 3v10.55A4 4 0 1 0 14 17V7h4V3h-6z');
    }
    isPlaying = !isPlaying;
  });


  // Modal untuk Foto
  function openModal(imageSrc, desc) {
    const modal = document.getElementById('photoModal');
    const modalImage = document.getElementById('modalImage');
    const modalDesc = document.getElementById('modalDesc');
    const modalLoading = document.getElementById('modalLoading');

    modal.classList.remove('hidden');
    modalLoading.style.display = 'flex';
    modalImage.style.display = 'none';
    modalDesc.style.display = 'none';

    setTimeout(() => {
      modalImage.src = imageSrc;
      modalDesc.textContent = desc;

      modalLoading.style.display = 'none';
      modalImage.style.display = 'block';
      modalDesc.style.display = 'block';

      modalImage.classList.add('opacity-0');
      modalDesc.classList.add('opacity-0');

      setTimeout(() => {
        modalImage.classList.remove('opacity-0');
        modalImage.classList.add('transition-opacity', 'duration-700', 'opacity-100');

        modalDesc.classList.remove('opacity-0');
        modalDesc.classList.add('transition-opacity', 'duration-700', 'opacity-100');
      }, 50);
    }, 500);
  }

  function closeModal() {
    const modal = document.getElementById('photoModal');
    const modalLoading = document.getElementById('modalLoading');
    const modalImage = document.getElementById('modalImage');
    const modalDesc = document.getElementById('modalDesc');

    modal.classList.add('hidden');
    modalLoading.style.display = 'flex';
    modalImage.style.display = 'none';
    modalDesc.style.display = 'none';
    modalImage.src = '';
  }

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

    fetch('like.php', {
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

  // Trigger Confetti
  function triggerConfetti() {
    const confettiSettings = { target: 'confetti-canvas', max: 80, size: 1.2, animate: true };
    const confetti = new ConfettiGenerator(confettiSettings);
    confetti.render();
    
    setTimeout(() => confetti.clear(), 2000);
  }



  // Fungsi animasi counter
function animateValue(id, start, end, duration) {
    const obj = document.getElementById(id);
    let startTimestamp = null;
    const step = (timestamp) => {
        if (!startTimestamp) startTimestamp = timestamp;
        const progress = Math.min((timestamp - startTimestamp) / duration, 1);
        obj.innerText = Math.floor(progress * (end - start) + start);
        if (progress < 1) {
            window.requestAnimationFrame(step);
        }
    };
    window.requestAnimationFrame(step);
}

// Jalankan animasi pas load
document.addEventListener('DOMContentLoaded', () => {
    animateValue("totalVisitors", 0, <?php echo $totalVisitors; ?>, 2000);
    animateValue("onlineVisitors", 0, <?php echo $onlineVisitors; ?>, 1500);
});

</script>

<!-- Canvas untuk Confetti -->
<canvas id="confetti-canvas" class="fixed top-0 left-0 w-full h-full pointer-events-none z-50"></canvas>

</body>
</html>
