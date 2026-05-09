const loginButton = document.getElementById('loginButton');
const emailInput = document.getElementById('email');
const passwordInput = document.getElementById('password');
const messageEl = document.getElementById('message');

async function signIn() {
  messageEl.textContent = '';
  loginButton.disabled = true;

  const email = emailInput.value.trim();
  const password = passwordInput.value.trim();

  if (!email || !password) {
    messageEl.textContent = 'Completează email și parolă.';
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
      messageEl.textContent = result.error || 'Autentificare eșuată.';
      loginButton.disabled = false;
      return;
    }

    messageEl.style.color = '#8fd18f';
    messageEl.textContent = 'Autentificare reușită. Redirecționare...';

    if (result.user?.role === 'admin') {
      window.location.href = '/admin.html';
    } else {
      window.location.href = '/index.html';
    }
  } catch (error) {
    messageEl.textContent = 'Eroare de rețea. Încearcă din nou.';
  } finally {
    loginButton.disabled = false;
  }
}

loginButton.addEventListener('click', signIn);
passwordInput.addEventListener('keypress', (event) => {
  if (event.key === 'Enter') {
    signIn();
  }
});
