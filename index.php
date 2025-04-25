<?php include 'db.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Frames of Us</title>
  <link href="https://fonts.googleapis.com/css2?family=Great+Vibes&family=Inter:wght@400;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Comic+Neue:wght@400;700&family=Quicksand:wght@400;600&display=swap" rel="stylesheet">

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

    /* Hover effect for gallery items */
    .gallery-item {
      position: relative;
      transition: transform 0.3s ease-in-out, box-shadow 0.3s ease;
    }
    .gallery-item:hover {
      transform: scale(1.05);
      box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
    }

    /* Description overlay */
    .photo-description {
      position: absolute;
      bottom: 0;
      left: 0;
      right: 0;
      background: rgba(0, 0, 0, 0.5);
      color: white;
      padding: 10px;
      font-size: 14px;
      text-align: center;
      opacity: 0;
      transition: opacity 0.3s;
    }
    .gallery-item:hover .photo-description {
      opacity: 1;
    }
  </style>
</head>
<body class="bg-pink-50 text-gray-800">

  <!-- Header -->
  <header class="text-center py-12">
    <h1 class="text-5xl title-font text-pink-600">Frames of Us</h1>
  </header>

  <!-- Gallery -->
  <main class="max-w-7xl mx-auto px-6 py-12">
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-8">
      <?php
      $result = $conn->query("SELECT * FROM photos ORDER BY uploaded_at DESC");

      if ($result->num_rows > 0) {
          while ($row = $result->fetch_assoc()) {
              echo "
              <div class='group relative overflow-hidden rounded-3xl border border-indigo-200 bg-white shadow-md hover:shadow-2xl transition duration-300'>
                <img src='uploads/{$row['filename']}' alt='{$row['title']}' 
                     class='w-full h-60 object-cover rounded-t-3xl group-hover:scale-105 transition-transform duration-300' />
                
                <div class='p-5 text-center flex flex-col items-center space-y-3'>
                  <div class='text-pink-500 text-4xl group-hover:scale-110 transition-transform duration-300'>
                    <svg xmlns=\"http://www.w3.org/2000/svg\" class=\"h-8 w-8\" fill=\"none\" viewBox=\"0 0 24 24\" stroke=\"currentColor\">
                      <path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M14.752 11.168l-3.197-2.132a4 4 0 10-4.59 6.36l2.093-1.396m1.253 2.636a4 4 0 005.372-5.572l-2.928 2.04\" />
                    </svg>
                  </div>
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
  <footer class="mt-12 text-center text-sm text-gray-500 py-6">
    © 2025 Frames of Us. Made with ❤️ by Evan.
  </footer>

  <script>
  const music = document.getElementById('backgroundMusic');
  const toggleBtn = document.getElementById('toggleMusic');
  let isPlaying = false;

  toggleBtn.addEventListener('click', () => {
    if (isPlaying) {
      music.pause();
      toggleBtn.textContent = '🔊 Play Music';
    } else {
      music.play();
      toggleBtn.textContent = '🔇 Pause Music';
    }
    isPlaying = !isPlaying;
  });

  document.getElementById('scrollBtn').addEventListener('click', function () {
    window.scrollBy({
      top: window.innerHeight,
      behavior: 'smooth'
    });
  });

</script>
</body>
</html>
