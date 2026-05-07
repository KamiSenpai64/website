export function initFloatingParticles() {
  const spawnParticle = () => {
    const particle = document.createElement('div');
    particle.className = 'particle';

    const size = Math.random() * 4 + 2;
    particle.style.cssText = `
      width:${size}px; height:${size}px;
      left:${Math.random() * 100}vw;
      background:${Math.random() > 0.5 ? '#d4a843' : '#c084fc'};
      animation-duration:${Math.random() * 12 + 8}s;
      animation-delay:${Math.random() * 5}s;
    `;

    document.body.appendChild(particle);
    setTimeout(() => particle.remove(), 20000);
  };

  setInterval(spawnParticle, 1800);
}
