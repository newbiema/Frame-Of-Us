<?php
include 'db.php'; 
 
function getUserIP() {
  if (!empty($_SERVER['HTTP_CLIENT_IP'])) return $_SERVER['HTTP_CLIENT_IP'];
  if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) return $_SERVER['HTTP_X_FORWARDED_FOR'];
  return $_SERVER['REMOTE_ADDR'];
}

$ip = getUserIP();
$now = date('Y-m-d H:i:s');

$check = $conn->query("SELECT * FROM visitors WHERE ip_address = '$ip'");

if ($check->num_rows > 0) {
  $conn->query("UPDATE visitors SET last_visit = '$now' WHERE ip_address = '$ip'");
} else {
  $conn->query("INSERT INTO visitors (ip_address, last_visit) VALUES ('$ip', '$now')");
}

$totalVisitorsResult = $conn->query("SELECT COUNT(*) AS total FROM visitors");
$totalVisitors = $totalVisitorsResult->fetch_assoc()['total'];

$timeLimit = date('Y-m-d H:i:s', strtotime('-5 minutes'));
$onlineVisitorsResult = $conn->query("SELECT COUNT(*) AS online FROM visitors WHERE last_visit >= '$timeLimit'");
$onlineVisitors = $onlineVisitorsResult->fetch_assoc()['online'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Frame Of Us</title>
  <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&family=Short+Stack&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/typed.js@2.0.12"></script>
  <script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="https://cdn.tailwindcss.com"></script>

  <style>
    /* Cute Pixel Art Styling */
    :root {
    scroll-behavior: smooth;
      --pink: #ff9bb3;
      --purple: #b5a1ff;
      --blue: #9bd4ff;
      --yellow: #ffe08a;
    }
    
    body {
      background-color: #fff5f7;
      font-family: 'Short Stack', cursive;
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
      font-size: 1.2rem;
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
    
    .pixel-card {
      background: white;
      border: 4px solid #000;
      box-shadow: 6px 6px 0 rgba(0,0,0,0.1);
      transition: all 0.2s;
      overflow: hidden;
    }
    
    .pixel-card:hover {
      transform: translate(-4px, -4px);
      box-shadow: 10px 10px 0 rgba(0,0,0,0.1);
    }
    
    .title-font {
      font-family: 'Press Start 2P', cursive;
      text-shadow: 3px 3px 0 var(--purple);
    }
    
    .pixel-loader {
      width: 16px;
      height: 16px;
      background-color: var(--pink);
      display: inline-block;
      animation: pixel-bounce 0.6s infinite ease-in-out;
    }
    
    @keyframes pixel-bounce {
      0%, 100% { transform: translateY(0); }
      50% { transform: translateY(-10px); background-color: var(--purple); }
    }
    
    .like-btn {
      background: white;
      border: 2px solid #000;
      padding: 5px 10px;
      font-size: 0.9rem;
      transition: all 0.2s;
    }
    
    .like-btn:hover {
      background: var(--pink);
      color: white;
    }
    
    .like-btn.liked {
      background: var(--pink);
      color: white;
      animation: heartBeat 0.5s;
    }
    
    @keyframes heartBeat {
      0% { transform: scale(1); }
      25% { transform: scale(1.2); }
      50% { transform: scale(1); }
      75% { transform: scale(1.1); }
      100% { transform: scale(1); }
    }
    
    .pixel-divider {
      height: 4px;
      background: repeating-linear-gradient(
        to right,
        var(--pink),
        var(--pink) 10px,
        var(--purple) 10px,
        var(--purple) 20px
      );
      margin: 1rem 0;
      border: 2px solid #000;
    }
    
    .music-btn {
      width: 60px;
      height: 60px;
      background: var(--purple);
      border: 3px solid #000;
      box-shadow: 4px 4px 0 rgba(0,0,0,0.2);
      transition: all 0.2s;
    }
    
    .music-btn:hover {
      transform: scale(1.1) rotate(5deg);
    }
    
    .floating {
      animation: floating 3s ease-in-out infinite;
    }
    
    @keyframes floating {
      0% { transform: translateY(0px); }
      50% { transform: translateY(-10px); }
      100% { transform: translateY(0px); }
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
<body class="overflow-x-hidden">

<!-- Decorative elements -->
<div class="pixel-cloud" style="width: 80px; height: 40px; top: 10%; left: 5%;"></div>
<div class="pixel-cloud" style="width: 60px; height: 30px; top: 15%; right: 10%;"></div>
<div class="pixel-star" style="top: 20%; left: 15%;">✦</div>
<div class="pixel-star" style="top: 25%; right: 20%;">✦</div>

<!-- Header -->
<header class="text-center py-16 relative" style="background: linear-gradient(to bottom right, var(--pink), var(--purple));">
  <div class="absolute inset-0 overflow-hidden">
    <div class="pixel-star floating" style="top: 30%; left: 20%; font-size: 24px;">✧</div>
    <div class="pixel-star floating" style="top: 40%; right: 25%; font-size: 24px;">✧</div>
    <div class="pixel-star floating" style="animation-delay: 0.5s; top: 70%; left: 15%; font-size: 18px;">✦</div>
  </div>
  
  <div class="relative z-10">
    <h1 class="text-5xl md:text-6xl title-font text-white mb-6">
      Frame Of Us
    </h1>
    
    <div class="pixel-divider w-1/3 mx-auto"></div>
    
    <p class="text-white text-xl md:text-2xl mb-8">
      <span id="typed-text"></span>
    </p>

    <button class="cute-btn mx-auto">
      <a href="#gallery">EXPLORE GALLERY</a>
    </button>

    <!-- Visitors Section -->
    <div class="flex flex-col items-center mt-12">
      <div class="pixel-border bg-white px-6 py-4 w-fit text-lg">
        <div class="flex flex-wrap gap-8 justify-center items-center">
          <div class="flex items-center gap-3">
            <div class="w-8 h-8 bg-var(--pink) border-2 border-black flex items-center justify-center">
              <i class="fas fa-users text-black"></i>
            </div>
            <span>Visitors:</span>
            <span id="totalVisitors" class="font-bold text-pink-600"><?php echo $totalVisitors; ?></span>
          </div>

          <div class="flex items-center gap-3">
            <div class="w-8 h-8 bg-var(--blue) border-2 border-black flex items-center justify-center">
              <i class="fas fa-signal text-black"></i>
            </div>
            <span>Online:</span>
            <span id="onlineVisitors" class="font-bold text-blue-600"><?php echo $onlineVisitors; ?></span>
          </div>
        </div>
      </div>
    </div>
  </div>
</header>

<!-- Gallery Section -->
<main id="gallery" class="max-w-7xl mx-auto px-4 sm:px-6 py-12 relative">
  <!-- Decorative elements -->
  <div class="pixel-star" style="top: 50px; right: 5%;">✧</div>
  <div class="pixel-star" style="bottom: 100px; left: 8%;">✦</div>
  
  <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-8">
    <?php
    $result = $conn->query("SELECT * FROM photos ORDER BY uploaded_at DESC");
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $uploadDate = date('M j, Y', strtotime($row['uploaded_at']));
            echo "
            <div class='pixel-card group relative'
                 data-aos='zoom-in-up'
                 onclick=\"openModal('uploads/{$row['filename']}', '{$row['description']}', {$row['id']}, '{$uploadDate}', {$row['likes']})\">
                 
              <div class='relative overflow-hidden aspect-square border-b-2 border-black'>
                <img src='uploads/{$row['filename']}' alt='{$row['description']}'
                    class='w-full h-full object-cover transition-transform duration-500 group-hover:scale-110'
                    loading='lazy' />
                
                <div class='absolute inset-0 bg-gradient-to-t from-black/50 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300'></div>
                
                <div class='absolute top-3 right-3 bg-white border-2 border-black px-2 py-1 text-xs opacity-0 group-hover:opacity-100 transition-opacity duration-300'>
                  <i class='fas fa-expand mr-1'></i> VIEW
                </div>
              </div>

              <div class='p-4'>
                <div class='flex justify-between items-center mb-3'>
                    <button onclick='event.stopPropagation(); likePhoto({$row['id']})'
                            id='like-button-{$row['id']}'
                          class='like-btn flex items-center space-x-1'
                          data-photo-id='{$row['id']}'>
                    <i class='fas fa-heart mr-1'></i>
                    <span class='likes-count' id='likes-{$row['id']}'>{$row['likes']}</span>
                  </button>

                  <span class='text-xs text-gray-600'>
                    <i class='far fa-clock mr-1'></i>{$uploadDate}
                  </span>
                </div>

                <p class='text-center text-gray-800 text-sm line-clamp-2'>
                  {$row['description']}
                </p>
              </div>
            </div>";
        }
    } else {
        echo "
        <div class='col-span-full text-center py-16 pixel-card' data-aos='fade-up'>
          <div class='mx-auto w-24 h-24 bg-pink-100 border-2 border-black rounded-full flex items-center justify-center mb-4'>
            <i class='fas fa-camera text-pink-500 text-3xl'></i>
          </div>
          <h3 class='text-xl font-bold mb-2'>NO PHOTOS YET</h3>
          <p class='text-gray-600 max-w-md mx-auto'>Share your first memory to start the gallery!</p>
        </div>";
    }
    ?>
  </div>
</main>

<!-- Modal -->
<div id="photoModal" class="hidden fixed inset-0 bg-black bg-opacity-80 flex items-center justify-center z-50 p-4">
  <div class="pixel-border bg-white max-w-md w-full mx-auto p-6 relative">
    <button onclick="closeModal()" class="absolute top-2 right-2 text-black hover:text-pink-500 text-2xl">&times;</button>

    <div id="modalLoading" class="flex items-center justify-center h-60 space-x-2">
      <div class="pixel-loader"></div>
      <div class="pixel-loader" style="animation-delay: 0.2s"></div>
      <div class="pixel-loader" style="animation-delay: 0.4s"></div>
    </div>

    <img id="modalImage" src="" class="w-full rounded mb-4 hidden">
    <p id="modalDesc" class="text-center text-pink-600 font-bold hidden"></p>
    <div class="flex justify-between items-center mt-4 text-sm">
      <span id="modalDate" class="text-gray-600"></span>
      <div class="flex items-center">
        <i class="fas fa-heart text-pink-500 mr-1"></i>
        <span id="modalLikes" class="font-bold"></span>
      </div>
    </div>
  </div>
</div>

<!-- Music Player -->
<div class="fixed bottom-6 right-6 z-50">
  <button id="toggleMusic" class="music-btn flex items-center justify-center">
    <i id="musicIcon" class="fas fa-play text-white text-xl"></i>
  </button>
</div>

<audio id="backgroundMusic" loop>
  <source src="music/nothing.mp3" type="audio/mpeg">
</audio>

<!-- Footer -->
<footer class="bg-pink-100 border-t-4 border-black py-12">
  <div class="max-w-6xl mx-auto px-6 space-y-8 text-center">

    <div class="flex justify-center gap-6">
      <a href="https://www.instagram.com/n4ve.666/" 
         class="w-10 h-10 bg-white border-2 border-black flex items-center justify-center hover:bg-pink-200 transition-colors">
        <i class="fab fa-instagram text-black"></i>
      </a>
      <a href="https://github.com/newbiema" 
         class="w-10 h-10 bg-white border-2 border-black flex items-center justify-center hover:bg-pink-200 transition-colors">
        <i class="fab fa-github text-black"></i>
      </a>
    </div>

    <div class="py-2">
      <div class="text-lg inline-block px-4 py-2 bg-white border-2 border-black">
        <span id="clock">00:00:00</span> WIB
      </div>
    </div>

    <div class="pt-6">
      <p class="text-gray-700">
        Made with <i class="fas fa-heart text-pink-500 mx-1"></i> 
        by <span class="font-bold">Evan</span>
      </p>
    </div>

  </div>
</footer>

<script>
  // Initialize AOS
  AOS.init({
    duration: 800,
    easing: 'ease-out-back'
  });

  // Typed.js initialization
  new Typed('#typed-text', {
    strings: ["Cute Pixel Memories", "Adorable Moments", "Share Your Pixie Life"],
    typeSpeed: 60,
    backSpeed: 30,
    loop: true,
    cursorChar: '▋',
    showCursor: true
  });

  // Animate visitor counters
  document.addEventListener('DOMContentLoaded', () => {
      animateValue("totalVisitors", 0, <?php echo $totalVisitors; ?>, 1500);
      animateValue("onlineVisitors", 0, <?php echo $onlineVisitors; ?>, 1000);
  });

  // Like function with cute animation
  function likePhoto(photoId) {
    if (localStorage.getItem('liked-' + photoId)) {
      Swal.fire({
        title: 'Oops!',
        text: 'You already liked this photo!',
        icon: 'info',
        confirmButtonText: 'OK',
        background: '#fff',
        confirmButtonColor: '#ff9bb3',
      });
      return;
    }

    const likeButton = document.getElementById('like-button-' + photoId);
    likeButton.classList.add('liked');

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
      localStorage.setItem('liked-' + photoId, true);
      triggerConfetti();
    })
    .catch(error => {
      console.error('Error:', error);
      Swal.fire({
        title: 'Error!',
        text: 'Failed to send like',
        icon: 'error',
        confirmButtonText: 'OK',
        background: '#fff',
        confirmButtonColor: '#ff9bb3',
      });
    });
  }

  // Modal functions
  function openModal(src, desc, id, date, likes) {
    const modal = document.getElementById('photoModal');
    const modalImg = document.getElementById('modalImage');
    const modalDesc = document.getElementById('modalDesc');
    const modalLoading = document.getElementById('modalLoading');
    const modalDate = document.getElementById('modalDate');
    const modalLikes = document.getElementById('modalLikes');
    
    modal.style.display = 'flex';
    modalLoading.style.display = 'flex';
    modalImg.style.display = 'none';
    modalDesc.style.display = 'none';
    
    modalImg.onload = function() {
      modalLoading.style.display = 'none';
      modalImg.style.display = 'block';
      modalDesc.style.display = 'block';
      modalDesc.textContent = desc;
      modalDate.textContent = date;
      modalLikes.textContent = likes;
    };
    
    modalImg.src = src;
  }
  
  function closeModal() {
    document.getElementById('photoModal').style.display = 'none';
  }
</script>

<script src="js/counter.js"></script>
<script src="js/clock.js"></script>
<script src="js/confetti.js"></script>
<script src="js/buttonmusic.js"></script>
<script src="https://cdn.jsdelivr.net/npm/confetti-js@0.0.18/dist/index.min.js"></script>

<!-- Confetti Canvas -->
<canvas id="confetti-canvas" class="fixed top-0 left-0 w-full h-full pointer-events-none z-40"></canvas>

</body>
</html>