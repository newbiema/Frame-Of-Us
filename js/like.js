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