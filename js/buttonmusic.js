  const toggleButton = document.getElementById("toggleMusic");
  const music = document.getElementById("backgroundMusic");
  const icon = document.getElementById("musicIcon");

  toggleButton.addEventListener("click", () => {
    if (music.paused) {
      music.play();
      icon.classList.remove("fa-play");
      icon.classList.add("fa-pause");
    } else {
      music.pause();
      icon.classList.remove("fa-pause");
      icon.classList.add("fa-play");
    }
  });
