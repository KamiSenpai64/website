import { initStars } from './modules/animations/stars.js';
import { initFloatingParticles } from './modules/animations/particles.js';
import { initScrollReveal } from './modules/ui/scrollReveal.js';
import { registerModalGlobals } from './modules/booking/modal.js';
import { initZodiacNavigation } from './modules/navigation/zodiacLinks.js';

function bootstrapApp() {
  initStars();
  initFloatingParticles();
  initScrollReveal();
  initZodiacNavigation();
  registerModalGlobals();
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', bootstrapApp);
} else {
  bootstrapApp();
}
