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

  