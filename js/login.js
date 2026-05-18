// DOM Elements
const tabs = document.querySelectorAll('.tab');
const tabContents = document.querySelectorAll('.tab-content');

// Login elements
const loginButton = document.getElementById('loginButton');
const loginEmailInput = document.getElementById('loginEmail');
const loginPasswordInput = document.getElementById('loginPassword');
const loginMessageEl = document.getElementById('loginMessage');

// Register elements
const registerButton = document.getElementById('registerButton');
const registerNameInput = document.getElementById('registerName');
const registerEmailInput = document.getElementById('registerEmail');
const registerPasswordInput = document.getElementById('registerPassword');
const registerConfirmPasswordInput = document.getElementById('registerConfirmPassword');
const registerMessageEl = document.getElementById('registerMessage');

// Tab functionality
function switchTab(tabName) {
  tabs.forEach(tab => {
    tab.classList.toggle('active', tab.getAttribute('data-tab') === tabName);
  });
  tabContents.forEach(content => {
    content.classList.toggle('active', content.id === `${tabName}-tab`);
  });
  
  // Clear messages when switching tabs
  loginMessageEl.textContent = '';
  registerMessageEl.textContent = '';
  loginMessageEl.className = 'message';
  registerMessageEl.className = 'message';
}

// Tab event listeners
tabs.forEach(tab => {
  tab.addEventListener('click', () => {
    switchTab(tab.getAttribute('data-tab'));
  });
});

function showMessage(messageEl, message, success = false) {
  messageEl.textContent = message;
  messageEl.className = success ? 'message success' : 'message';
}

async function signIn() {
  showMessage(loginMessageEl, '');
  loginButton.disabled = true;

  const email = loginEmailInput.value.trim();
  const password = loginPasswordInput.value.trim();

  if (!email || !password) {
    showMessage(loginMessageEl, 'Completează email și parolă.');
    loginButton.disabled = false;
    return;
  }

  try {
    const response = await fetch('/backend/login.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify({ email, password })
    });

    const result = await response.json();
    if (!result.success) {
      showMessage(loginMessageEl, result.error || 'Autentificare eșuată.');
      loginButton.disabled = false;
      return;
    }

    showMessage(loginMessageEl, 'Autentificare reușită. Redirecționare...', true);

    setTimeout(() => {
      if (result.user?.role === 'admin') {
        window.location.href = '/admin.html';
      } else {
        window.location.href = '/index.html';
      }
    }, 1000);
  } catch (error) {
    showMessage(loginMessageEl, 'Eroare de rețea. Încearcă din nou.');
  } finally {
    loginButton.disabled = false;
  }
}

async function register() {
  showMessage(registerMessageEl, '');
  registerButton.disabled = true;

  const name = registerNameInput.value.trim();
  const email = registerEmailInput.value.trim();
  const password = registerPasswordInput.value.trim();
  const confirmPassword = registerConfirmPasswordInput.value.trim();

  // Validation
  if (!name || !email || !password || !confirmPassword) {
    showMessage(registerMessageEl, 'Completează toate câmpurile.');
    registerButton.disabled = false;
    return;
  }

  if (password !== confirmPassword) {
    showMessage(registerMessageEl, 'Parolele nu se potrivesc.');
    registerButton.disabled = false;
    return;
  }

  if (password.length < 6) {
    showMessage(registerMessageEl, 'Parola trebuie să aibă cel puțin 6 caractere.');
    registerButton.disabled = false;
    return;
  }

  if (!isValidEmail(email)) {
    showMessage(registerMessageEl, 'Introdu o adresă de email validă.');
    registerButton.disabled = false;
    return;
  }

  try {
    const response = await fetch('/backend/register.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify({ name, email, password })
    });

    const result = await response.json();
    if (!result.success) {
      showMessage(registerMessageEl, result.error || 'Crearea contului a eșuat.');
      registerButton.disabled = false;
      return;
    }

    showMessage(registerMessageEl, 'Cont creat cu succes! Redirecționare...', true);

    // Clear form
    registerNameInput.value = '';
    registerEmailInput.value = '';
    registerPasswordInput.value = '';
    registerConfirmPasswordInput.value = '';

    // Switch to login tab after successful registration
    setTimeout(() => {
      switchTab('login');
      loginEmailInput.value = email;
      loginPasswordInput.focus();
    }, 1500);

  } catch (error) {
    showMessage(registerMessageEl, 'Eroare de rețea. Încearcă din nou.');
  } finally {
    registerButton.disabled = false;
  }
}

function isValidEmail(email) {
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return emailRegex.test(email);
}

// Event listeners
loginButton.addEventListener('click', signIn);
registerButton.addEventListener('click', register);

// Enter key support
loginPasswordInput.addEventListener('keypress', (event) => {
  if (event.key === 'Enter') {
    signIn();
  }
});

registerConfirmPasswordInput.addEventListener('keypress', (event) => {
  if (event.key === 'Enter') {
    register();
  }
});
