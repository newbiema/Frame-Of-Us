function triggerConfetti() {
  const confettiSettings = { 
    target: 'confetti-canvas',
    max: 80,
    size: 1.2,
    animate: true,
    props: ['circle', 'square', 'triangle', 'line'],
    colors: [[255,155,179], [181,161,255], [155,212,255], [255,224,138]],
    clock: 25
  };
  
  const confetti = new ConfettiGenerator(confettiSettings);
  confetti.render();
  
  setTimeout(() => {
    confetti.clear();
    // Optional: Remove the canvas to clean up
    document.getElementById('confetti-canvas').innerHTML = '';
  }, 2000);
}