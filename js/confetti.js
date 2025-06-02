  // Trigger Confetti
  function triggerConfetti() {
    const confettiSettings = { target: 'confetti-canvas', max: 80, size: 1.2, animate: true };
    const confetti = new ConfettiGenerator(confettiSettings);
    confetti.render();
    
    setTimeout(() => confetti.clear(), 2000);
  }
