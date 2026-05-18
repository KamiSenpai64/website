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
  updateUserNavigation();
}

// Update navigation based on user login status
async function updateUserNavigation() {
  try {
    const response = await fetch('/backend/login.php', {
      method: 'GET',
      credentials: 'same-origin'
    });
    
    const result = await response.json();
    const loginLink = document.getElementById('loginLink');
    const userDashboardLink = document.getElementById('userDashboardLink');
    const userProfileLink = document.getElementById('userProfileLink');
    
    if (result.success && result.user) {
      // User is logged in
      if (loginLink) loginLink.style.display = 'none';
      if (userDashboardLink) userDashboardLink.style.display = 'inline';
      if (userProfileLink) userProfileLink.style.display = 'inline';
    } else {
      // User is not logged in
      if (loginLink) loginLink.style.display = 'inline';
      if (userDashboardLink) userDashboardLink.style.display = 'none';
      if (userProfileLink) userProfileLink.style.display = 'none';
    }
  } catch (error) {
    console.error('Error checking login status:', error);
  }
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', bootstrapApp);
} else {
  bootstrapApp();
}
