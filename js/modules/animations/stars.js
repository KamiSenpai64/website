export function initStars() {
  const canvas = document.getElementById('stars-canvas');
  if (!canvas) return;

  const ctx = canvas.getContext('2d');
  let stars = [];

  const resizeCanvas = () => {
    canvas.width = window.innerWidth;
    canvas.height = window.innerHeight;
  };

  const buildStars = () => {
    stars = [];
    for (let i = 0; i < 220; i += 1) {
      stars.push({
        x: Math.random() * canvas.width,
        y: Math.random() * canvas.height,
        r: Math.random() * 1.4 + 0.2,
        speed: Math.random() * 0.008 + 0.003,
        phase: Math.random() * Math.PI * 2,
      });
    }
  };

  const draw = (timestamp) => {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    stars.forEach((star) => {
      const alpha = 0.3 + 0.7 * Math.abs(Math.sin(star.phase + timestamp * star.speed));
      ctx.beginPath();
      ctx.arc(star.x, star.y, star.r, 0, Math.PI * 2);
      ctx.fillStyle = `rgba(255,250,230,${alpha})`;
      ctx.fill();
    });
    requestAnimationFrame(draw);
  };

  resizeCanvas();
  buildStars();
  requestAnimationFrame(draw);
  window.addEventListener('resize', () => {
    resizeCanvas();
    buildStars();
  });
}
