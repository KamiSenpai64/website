const accountsTable = document.getElementById('accountsTable');
const createAccountButton = document.getElementById('createAccount');
const newEmail = document.getElementById('newEmail');
const newName = document.getElementById('newName');
const newPassword = document.getElementById('newPassword');
const newRole = document.getElementById('newRole');
const accountMessage = document.getElementById('accountMessage');
const logoutButton = document.getElementById('logoutButton');

function showMessage(message, success = false) {
  accountMessage.textContent = message;
  accountMessage.style.color = success ? '#8fd18f' : '#f4c9b8';
}

async function fetchAccounts() {
  showMessage('Încarcare conturi...');
  try {
    const response = await fetch('/backend/admin_users.php', {
      method: 'GET',
      credentials: 'same-origin'
    });
    if (response.status === 401 || response.status === 403) {
      window.location.href = '/login.html';
      return;
    }
    const result = await response.json();
    if (!result.success) {
      showMessage(result.error || 'Nu s-au putut încărca conturile.');
      return;
    }
    renderAccounts(result.accounts);
    showMessage('');
  } catch (error) {
    showMessage('Eroare server. Încearcă din nou.');
  }
}

function renderAccounts(accounts) {
  accountsTable.innerHTML = '';
  accounts.forEach((account) => {
    const row = document.createElement('tr');
    row.innerHTML = `
      <td>${account.id}</td>
      <td>${account.email}</td>
      <td>${account.name}</td>
      <td>${account.role === 'admin' ? '<span class="admin-active">Admin</span>' : 'User'}</td>
      <td>${account.status}</td>
      <td>
        <button class="small-button delete" data-id="${account.id}">Șterge</button>
      </td>
    `;
    accountsTable.appendChild(row);
  });

  document.querySelectorAll('.small-button.delete').forEach((button) => {
    button.addEventListener('click', async () => {
      const id = button.getAttribute('data-id');
      if (!confirm('Ștergi acest cont?')) {
        return;
      }
      await deleteAccount(parseInt(id, 10));
    });
  });
}

async function createAccount() {
  const email = newEmail.value.trim();
  const name = newName.value.trim();
  const password = newPassword.value.trim();
  const role = newRole.value;

  if (!email || !name || !password) {
    showMessage('Completează toate câmpurile.');
    return;
  }

  createAccountButton.disabled = true;
  showMessage('Creare cont...');

  try {
    const response = await fetch('/backend/admin_users.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'create', email, password, name, role })
    });
    const result = await response.json();
    if (!result.success) {
      showMessage(result.error || 'Nu s-a putut crea contul.');
      return;
    }
    showMessage('Cont creat cu succes.', true);
    newEmail.value = '';
    newName.value = '';
    newPassword.value = '';
    newRole.value = 'user';
    await fetchAccounts();
  } catch (error) {
    showMessage('Eroare de rețea. Încearcă din nou.');
  } finally {
    createAccountButton.disabled = false;
  }
}

async function deleteAccount(id) {
  try {
    const response = await fetch('/backend/admin_users.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'delete', id })
    });
    const result = await response.json();
    if (!result.success) {
      showMessage(result.error || 'Nu s-a putut șterge contul.');
      return;
    }
    showMessage('Cont șters.', true);
    await fetchAccounts();
  } catch (error) {
    showMessage('Eroare de rețea. Încearcă din nou.');
  }
}

async function logout() {
  await fetch('/backend/logout.php', {
    method: 'POST',
    credentials: 'same-origin'
  });
  window.location.href = '/login.html';
}

createAccountButton.addEventListener('click', createAccount);
logoutButton.addEventListener('click', logout);
fetchAccounts();
