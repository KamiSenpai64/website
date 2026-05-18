// DOM Elements
const accountsTable = document.getElementById('accountsTable');
const bookingsTable = document.getElementById('bookingsTable');
const createAccountButton = document.getElementById('createAccount');
const newEmail = document.getElementById('newEmail');
const newName = document.getElementById('newName');
const newPassword = document.getElementById('newPassword');
const newRole = document.getElementById('newRole');
const accountMessage = document.getElementById('accountMessage');
const logoutButton = document.getElementById('logoutButton');

// Tab elements
const tabs = document.querySelectorAll('.tab');
const tabContents = document.querySelectorAll('.tab-content');

// Stats elements
const totalAccountsEl = document.getElementById('totalAccounts');
const totalBookingsEl = document.getElementById('totalBookings');
const pendingBookingsEl = document.getElementById('pendingBookings');
const confirmedBookingsEl = document.getElementById('confirmedBookings');

function showMessage(message, success = false) {
  accountMessage.textContent = message;
  accountMessage.style.color = success ? '#8fd18f' : '#f4c9b8';
}

// Tab functionality
function switchTab(tabName) {
  tabs.forEach(tab => {
    tab.classList.toggle('active', tab.getAttribute('data-tab') === tabName);
  });
  tabContents.forEach(content => {
    content.classList.toggle('active', content.id === `${tabName}-tab`);
  });
  
  // Load data for the active tab
  if (tabName === 'accounts') {
    fetchAccounts();
  } else if (tabName === 'bookings') {
    fetchBookings();
  } else if (tabName === 'stats') {
    fetchStats();
  }
}

// Tab event listeners
tabs.forEach(tab => {
  tab.addEventListener('click', () => {
    switchTab(tab.getAttribute('data-tab'));
  });
});

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

async function fetchBookings() {
  try {
    const response = await fetch('/backend/admin_bookings.php', {
      method: 'GET',
      credentials: 'same-origin'
    });
    if (response.status === 401 || response.status === 403) {
      window.location.href = '/login.html';
      return;
    }
    const result = await response.json();
    if (!result.success) {
      console.error('Failed to load bookings:', result.error);
      return;
    }
    renderBookings(result.bookings);
  } catch (error) {
    console.error('Error fetching bookings:', error);
  }
}

async function fetchStats() {
  try {
    const response = await fetch('/backend/admin_stats.php', {
      method: 'GET',
      credentials: 'same-origin'
    });
    if (response.status === 401 || response.status === 403) {
      window.location.href = '/login.html';
      return;
    }
    const result = await response.json();
    if (!result.success) {
      console.error('Failed to load stats:', result.error);
      return;
    }
    renderStats(result.stats);
  } catch (error) {
    console.error('Error fetching stats:', error);
  }
}

function renderAccounts(accounts) {
  accountsTable.innerHTML = '';
  accounts.forEach((account) => {
    const row = document.createElement('tr');
    row.innerHTML = `
      <td>${account.id}</td>
      <td>${account.email}</td>
      <td>${account.name || '-'}</td>
      <td>${account.role === 'admin' ? '<span class="admin-active">Admin</span>' : 'User'}</td>
      <td><span class="admin-active">${account.status}</span></td>
      <td>${new Date(account.created_at).toLocaleDateString('ro-RO')}</td>
      <td>
        <button class="small-button edit" data-id="${account.id}">Editează</button>
        <button class="small-button delete" data-id="${account.id}">Șterge</button>
      </td>
    `;
    accountsTable.appendChild(row);
  });

  // Add event listeners for action buttons
  document.querySelectorAll('.small-button.delete').forEach((button) => {
    button.addEventListener('click', async () => {
      const id = button.getAttribute('data-id');
      if (!confirm('Ștergi acest cont?')) {
        return;
      }
      await deleteAccount(parseInt(id, 10));
    });
  });

  document.querySelectorAll('.small-button.edit').forEach((button) => {
    button.addEventListener('click', async () => {
      const id = button.getAttribute('data-id');
      await editAccount(parseInt(id, 10));
    });
  });
}

function renderBookings(bookings) {
  bookingsTable.innerHTML = '';
  bookings.forEach((booking) => {
    const row = document.createElement('tr');
    const statusClass = `status-${booking.status}`;
    const consultationDate = booking.consultation_date 
      ? new Date(booking.consultation_date).toLocaleString('ro-RO')
      : '-';
    
    row.innerHTML = `
      <td>${booking.id}</td>
      <td>${booking.name}</td>
      <td>${booking.email}</td>
      <td>${booking.preferred_time}</td>
      <td><span class="${statusClass}">${booking.status}</span></td>
      <td>${new Date(booking.booking_date).toLocaleDateString('ro-RO')}</td>
      <td>${consultationDate}</td>
      <td>${booking.notes ? booking.notes.substring(0, 50) + (booking.notes.length > 50 ? '...' : '') : '-'}</td>
      <td>
        <button class="small-button danger" onclick="deleteBooking(${booking.id})">Șterge</button>
      </td>
    `;
    bookingsTable.appendChild(row);
  });
}

function renderStats(stats) {
  totalAccountsEl.textContent = stats.total_accounts || 0;
  totalBookingsEl.textContent = stats.total_bookings || 0;
  pendingBookingsEl.textContent = stats.pending_bookings || 0;
  confirmedBookingsEl.textContent = stats.confirmed_bookings || 0;
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

async function editAccount(id) {
  // Simple edit functionality - for now just show an alert
  // In a real implementation, you'd open a modal or form
  const newName = prompt('Introduceti noul nume:');
  if (newName === null) return;
  
  try {
    const response = await fetch('/backend/admin_users.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'update', id, name: newName })
    });
    const result = await response.json();
    if (!result.success) {
      showMessage(result.error || 'Nu s-a putut actualiza contul.');
      return;
    }
    showMessage('Cont actualizat.', true);
    await fetchAccounts();
  } catch (error) {
    showMessage('Eroare de rețea. Încearcă din nou.');
  }
}

async function deleteBooking(bookingId) {
  if (!confirm('Ești sigur că vrei să ștergi această rezervare?')) {
    return;
  }
  
  try {
    const response = await fetch('/backend/admin_bookings.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'delete',
        booking_id: bookingId
      })
    });
    
    const result = await response.json();
    if (result.success) {
      fetchBookings(); // Refresh the bookings table
      fetchStats(); // Refresh statistics
    } else {
      alert(result.error || 'Nu s-a putut șterge rezervarea.');
    }
  } catch (error) {
    alert('Eroare de rețea. Încearcă din nou.');
  }
}

async function logout() {
  await fetch('/backend/logout.php', {
    method: 'POST',
    credentials: 'same-origin'
  });
  window.location.href = '/login.html';
}

// Event listeners
createAccountButton.addEventListener('click', createAccount);
logoutButton.addEventListener('click', logout);

// Initialize
document.addEventListener('DOMContentLoaded', () => {
  fetchAccounts();
});
