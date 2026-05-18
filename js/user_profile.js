// DOM Elements
const tabs = document.querySelectorAll('.tab');
const tabContents = document.querySelectorAll('.tab-content');
const userInfoEl = document.getElementById('userInfo');
const logoutButton = document.getElementById('logoutButton');

// Profile elements
const profileNameEl = document.getElementById('profileName');
const profileEmailEl = document.getElementById('profileEmail');
const memberSinceEl = document.getElementById('memberSince');
const editNameInput = document.getElementById('editName');
const editEmailInput = document.getElementById('editEmail');
const saveProfileBtn = document.getElementById('saveProfileBtn');
const cancelEditBtn = document.getElementById('cancelEditBtn');
const profileMessageEl = document.getElementById('profileMessage');

// Statistics elements
const totalBookingsStatEl = document.getElementById('totalBookingsStat');
const currentBookingsStatEl = document.getElementById('currentBookingsStat');
const completedBookingsStatEl = document.getElementById('completedBookingsStat');
const reviewsCountStatEl = document.getElementById('reviewsCountStat');

// Table elements
const allBookingsTable = document.getElementById('allBookingsTable');
const currentBookingsTable = document.getElementById('currentBookingsTable');
const pastBookingsTable = document.getElementById('pastBookingsTable');

// Action buttons
const newBookingBtn = document.getElementById('newBookingBtn');
const refreshBookingsBtn = document.getElementById('refreshBookingsBtn');

let currentUser = null;
let isEditing = false;

// Tab functionality
function switchTab(tabName) {
  tabs.forEach(tab => {
    tab.classList.toggle('active', tab.getAttribute('data-tab') === tabName);
  });
  tabContents.forEach(content => {
    content.classList.toggle('active', content.id === `${tabName}-tab`);
  });
  
  // Load data for active tab
  if (tabName === 'all') {
    loadAllBookings();
  } else if (tabName === 'current') {
    loadCurrentBookings();
  } else if (tabName === 'past') {
    loadPastBookings();
  }
}

// Tab event listeners
tabs.forEach(tab => {
  tab.addEventListener('click', () => {
    switchTab(tab.getAttribute('data-tab'));
  });
});

// Show message function
function showMessage(message, success = false) {
  profileMessageEl.textContent = message;
  profileMessageEl.className = success ? 'message success' : 'message';
}

// Load current user info
async function loadUserInfo() {
  try {
    const response = await fetch('/backend/login.php', {
      method: 'GET',
      credentials: 'same-origin'
    });
    
    if (response.status === 401) {
      window.location.href = '/login.html';
      return;
    }
    
    const result = await response.json();
    if (result.success && result.user) {
      currentUser = result.user;
      userInfoEl.textContent = `Bun venit, ${result.user.name || result.user.email}!`;
      
      // Update profile display
      profileNameEl.textContent = result.user.name || 'Utilizator';
      profileEmailEl.textContent = result.user.email;
      memberSinceEl.textContent = new Date(result.user.created_at).toLocaleDateString('ro-RO');
      
      // Load statistics
      loadStatistics();
    }
  } catch (error) {
    console.error('Error loading user info:', error);
  }
}

// Load user statistics
async function loadStatistics() {
  if (!currentUser) return;
  
  try {
    const response = await fetch('/backend/user_stats.php', {
      method: 'GET',
      credentials: 'same-origin'
    });
    
    if (response.ok) {
      const result = await response.json();
      if (result.success) {
        totalBookingsStatEl.textContent = result.stats.total_bookings || 0;
        currentBookingsStatEl.textContent = result.stats.current_bookings || 0;
        completedBookingsStatEl.textContent = result.stats.completed_bookings || 0;
        reviewsCountStatEl.textContent = result.stats.reviews_count || 0;
      }
    }
  } catch (error) {
    console.error('Error loading statistics:', error);
  }
}

// Load all bookings
async function loadAllBookings() {
  if (!currentUser) return;
  
  try {
    const response = await fetch('/backend/user_bookings.php?type=all', {
      method: 'GET',
      credentials: 'same-origin'
    });
    
    if (response.status === 401) {
      window.location.href = '/login.html';
      return;
    }
    
    const result = await response.json();
    if (result.success) {
      renderAllBookings(result.bookings);
    }
  } catch (error) {
    console.error('Error loading all bookings:', error);
  }
}

// Load current bookings
async function loadCurrentBookings() {
  if (!currentUser) return;
  
  try {
    const response = await fetch('/backend/user_bookings.php?type=current', {
      method: 'GET',
      credentials: 'same-origin'
    });
    
    if (response.status === 401) {
      window.location.href = '/login.html';
      return;
    }
    
    const result = await response.json();
    if (result.success) {
      renderCurrentBookings(result.bookings);
    }
  } catch (error) {
    console.error('Error loading current bookings:', error);
  }
}

// Load past bookings
async function loadPastBookings() {
  if (!currentUser) return;
  
  try {
    const response = await fetch('/backend/user_bookings.php?type=past', {
      method: 'GET',
      credentials: 'same-origin'
    });
    
    if (response.status === 401) {
      window.location.href = '/login.html';
      return;
    }
    
    const result = await response.json();
    if (result.success) {
      renderPastBookings(result.bookings);
    }
  } catch (error) {
    console.error('Error loading past bookings:', error);
  }
}

// Render all bookings
function renderAllBookings(bookings) {
  allBookingsTable.innerHTML = '';
  
  if (bookings.length === 0) {
    allBookingsTable.innerHTML = '<tr><td colspan="6" style="text-align:center; color:#999;">Nu există rezervări.</td></tr>';
    return;
  }
  
  bookings.forEach((booking) => {
    const row = document.createElement('tr');
    const statusClass = `status-${booking.status}`;
    
    row.innerHTML = `
      <td>${booking.id}</td>
      <td>${new Date(booking.booking_date).toLocaleDateString('ro-RO')}</td>
      <td>${booking.preferred_time}</td>
      <td><span class="${statusClass}">${booking.status}</span></td>
      <td>${booking.notes ? booking.notes.substring(0, 30) + (booking.notes.length > 30 ? '...' : '') : '-'}</td>
      <td>
        <button class="small-button review" onclick="viewBookingDetails(${booking.id})">Detalii</button>
        ${booking.status === 'pending' ? `<button class="small-button danger" onclick="cancelBooking(${booking.id})">Anulează</button>` : ''}
        ${booking.status === 'confirmed' && !booking.has_review ? `<button class="small-button review" onclick="openReviewModal(${booking.id})">Recenzie</button>` : ''}
      </td>
    `;
    allBookingsTable.appendChild(row);
  });
}

// Render current bookings
function renderCurrentBookings(bookings) {
  currentBookingsTable.innerHTML = '';
  
  if (bookings.length === 0) {
    currentBookingsTable.innerHTML = '<tr><td colspan="6" style="text-align:center; color:#999;">Nu există rezervări curente.</td></tr>';
    return;
  }
  
  bookings.forEach((booking) => {
    const row = document.createElement('tr');
    const statusClass = `status-${booking.status}`;
    
    row.innerHTML = `
      <td>${booking.id}</td>
      <td>${new Date(booking.booking_date).toLocaleDateString('ro-RO')}</td>
      <td>${booking.preferred_time}</td>
      <td><span class="${statusClass}">${booking.status}</span></td>
      <td>${booking.notes ? booking.notes.substring(0, 30) + (booking.notes.length > 30 ? '...' : '') : '-'}</td>
      <td>
        <button class="small-button review" onclick="viewBookingDetails(${booking.id})">Detalii</button>
        ${booking.status === 'pending' ? `<button class="small-button danger" onclick="cancelBooking(${booking.id})">Anulează</button>` : ''}
      </td>
    `;
    currentBookingsTable.appendChild(row);
  });
}

// Render past bookings
function renderPastBookings(bookings) {
  pastBookingsTable.innerHTML = '';
  
  if (bookings.length === 0) {
    pastBookingsTable.innerHTML = '<tr><td colspan="6" style="text-align:center; color:#999;">Nu există rezervări trecute.</td></tr>';
    return;
  }
  
  bookings.forEach((booking) => {
    const row = document.createElement('tr');
    const statusClass = `status-${booking.status}`;
    
    row.innerHTML = `
      <td>${booking.id}</td>
      <td>${new Date(booking.booking_date).toLocaleDateString('ro-RO')}</td>
      <td>${booking.preferred_time}</td>
      <td><span class="${statusClass}">${booking.status}</span></td>
      <td>${booking.notes ? booking.notes.substring(0, 30) + (booking.notes.length > 30 ? '...' : '') : '-'}</td>
      <td>
        <button class="small-button review" onclick="viewBookingDetails(${booking.id})">Detalii</button>
        ${booking.status === 'confirmed' && !booking.has_review ? `<button class="small-button review" onclick="openReviewModal(${booking.id})">Recenzie</button>` : ''}
        ${booking.has_review ? 'Recenziat' : ''}
      </td>
    `;
    pastBookingsTable.appendChild(row);
  });
}

// Enable profile editing
function enableProfileEdit() {
  isEditing = true;
  editNameInput.value = currentUser.name || '';
  editEmailInput.value = currentUser.email || '';
  editNameInput.disabled = false;
  editEmailInput.disabled = false;
  editNameInput.style.background = '#120b27';
  editEmailInput.style.background = '#120b27';
  editNameInput.style.cursor = 'text';
  editEmailInput.style.cursor = 'text';
  saveProfileBtn.style.display = 'inline-block';
  cancelEditBtn.style.display = 'inline-block';
  showMessage('');
}

// Cancel profile editing
function cancelProfileEdit() {
  isEditing = false;
  editNameInput.value = '';
  editEmailInput.value = '';
  editNameInput.disabled = true;
  editEmailInput.disabled = true;
  editNameInput.style.background = '#2a1f3a';
  editEmailInput.style.background = '#2a1f3a';
  editNameInput.style.cursor = 'not-allowed';
  editEmailInput.style.cursor = 'not-allowed';
  saveProfileBtn.style.display = 'none';
  cancelEditBtn.style.display = 'none';
  showMessage('');
}

// Save profile changes
async function saveProfile() {
  const name = editNameInput.value.trim();
  const email = editEmailInput.value.trim();
  
  if (!name || !email) {
    showMessage('Completează toate câmpurile.');
    return;
  }
  
  if (!isValidEmail(email)) {
    showMessage('Introdu o adresă de email validă.');
    return;
  }
  
  saveProfileBtn.disabled = true;
  saveProfileBtn.textContent = 'Se salvează...';
  
  try {
    const response = await fetch('/backend/user_profile.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ name, email })
    });
    
    const result = await response.json();
    if (result.success) {
      showMessage('Profil actualizat cu succes!', true);
      
      // Update current user data
      currentUser.name = name;
      currentUser.email = email;
      
      // Update display
      profileNameEl.textContent = name;
      profileEmailEl.textContent = email;
      
      cancelProfileEdit();
      loadStatistics(); // Refresh statistics
    } else {
      showMessage(result.error || 'Nu s-a putut actualiza profilul.');
    }
  } catch (error) {
    showMessage('Eroare de rețea. Încearcă din nou.');
  } finally {
    saveProfileBtn.disabled = false;
    saveProfileBtn.textContent = 'Salvează Modificările';
  }
}

// Email validation
function isValidEmail(email) {
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return emailRegex.test(email);
}

// View booking details
function viewBookingDetails(bookingId) {
  // For now, just show an alert with basic info
  // In a real implementation, this would open a modal with full details
  alert(`Detalii rezervare #${bookingId}\n\nAceastă funcționalitate va fi disponibilă curând.`);
}

// Cancel booking
async function cancelBooking(bookingId) {
  if (!confirm('Ești sigur că vrei să anulezi această rezervare?')) {
    return;
  }
  
  try {
    const response = await fetch('/backend/user_bookings.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'cancel',
        booking_id: bookingId
      })
    });
    
    const result = await response.json();
    if (result.success) {
      loadAllBookings();
      loadCurrentBookings();
      loadPastBookings();
      loadStatistics(); // Refresh statistics
    } else {
      alert(result.error || 'Nu s-a putut anula rezervarea.');
    }
  } catch (error) {
    alert('Eroare de rețea. Încearcă din nou.');
  }
}

// Open review modal (simplified - would normally open a modal)
function openReviewModal(bookingId) {
  // For now, redirect to user dashboard where review modal exists
  if (confirm('Vrei să lași o recenzie pentru această rezervare?')) {
    window.location.href = '/user_dashboard.html?tab=reviews&booking=' + bookingId;
  }
}

// Logout
async function logout() {
  await fetch('/backend/logout.php', {
    method: 'POST',
    credentials: 'same-origin'
  });
  window.location.href = '/index.html';
}

// Event listeners
logoutButton.addEventListener('click', logout);
saveProfileBtn.addEventListener('click', saveProfile);
cancelEditBtn.addEventListener('click', cancelProfileEdit);
newBookingBtn.addEventListener('click', () => {
  // Open booking modal from main page
  window.location.href = '/index.html#lectura';
});
refreshBookingsBtn.addEventListener('click', () => {
  loadAllBookings();
  loadCurrentBookings();
  loadPastBookings();
  loadStatistics();
});

// Initialize
document.addEventListener('DOMContentLoaded', () => {
  loadUserInfo();
  loadAllBookings();
  
  // Set initial state for edit form
  editNameInput.disabled = true;
  editEmailInput.disabled = true;
  editNameInput.style.background = '#2a1f3a';
  editEmailInput.style.background = '#2a1f3a';
  editNameInput.style.cursor = 'not-allowed';
  editEmailInput.style.cursor = 'not-allowed';
  saveProfileBtn.style.display = 'none';
  cancelEditBtn.style.display = 'none';
});
