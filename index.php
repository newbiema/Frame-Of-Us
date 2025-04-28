<?php include 'db.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Frames of Us</title>
  <link href="https://fonts.googleapis.com/css2?family=Great+Vibes&family=Inter:wght@400;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Comic+Neue:wght@400;700&family=Quicksand:wght@400;600&display=swap" rel="stylesheet">
  <link href="https://unpkg.com/aos@2.3.4/dist/aos.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <script src="https://cdn.tailwindcss.com"></script>

  <style>
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
    <h1 class="text-5xl title-font text-pink-600">Frames of Us</h1>
  </header>

<!-- Gallery -->
<main class="max-w-7xl mx-auto px-6 py-12">
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
                <p class='text-pink-500 text-base cute-font leading-relaxed group-hover:text-pink-700 transition-colors duration-300'>
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

  <!-- Kontrol musik -->
  <div class="fixed bottom-4 right-4 z-50">
    <button id="toggleMusic" class="bg-pink-600 hover:bg-pink-700 text-white px-4 py-2 rounded-full shadow-lg">
      🔊 Play Music
    </button>
  </div>

  <audio id="backgroundMusic" loop>
    <source src="music/nothing.mp3" type="audio/mpeg">
    Browser tidak mendukung audio.
  </audio>

  <!-- Footer -->
  <footer class="bg-pink-100 mt-12 text-center text-sm text-gray-500 py-6">
    © 2025 Frames of Us. Made with ❤️ by Evan.
  </footer>

  <script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>
  <script>
    AOS.init();

    const music = document.getElementById('backgroundMusic');
    const toggleBtn = document.getElementById('toggleMusic');
    let isPlaying = false;

    toggleBtn.addEventListener('click', () => {
      if (isPlaying) {
        music.pause();
        toggleBtn.innerHTML = '🔊 Play Music';
      } else {
        music.play();
        toggleBtn.innerHTML = '🔇 Pause Music';
      }
      isPlaying = !isPlaying;
    });

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


// Confetti efek
function triggerConfetti() {
  const confettiSettings = { target: 'confetti-canvas', max: 80, size: 1.2, animate: true };
  const confetti = new ConfettiGenerator(confettiSettings);
  confetti.render();
  
  setTimeout(() => confetti.clear(), 2000); // Stop confetti setelah 2 detik
}
</script>

<!-- Canvas untuk confetti -->
<canvas id="confetti-canvas" class="fixed top-0 left-0 w-full h-full pointer-events-none z-50"></canvas>


  </script>

  <script src="https://cdn.jsdelivr.net/npm/confetti-js@0.0.18/dist/index.min.js"></script>
</body>
</html>
