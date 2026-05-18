// DOM Elements
const tabs = document.querySelectorAll('.tab');
const tabContents = document.querySelectorAll('.tab-content');
const userInfoEl = document.getElementById('userInfo');
const logoutButton = document.getElementById('logoutButton');

// Table elements
const currentBookingsTable = document.getElementById('currentBookingsTable');
const pastBookingsTable = document.getElementById('pastBookingsTable');
const reviewsTable = document.getElementById('reviewsTable');

// Review modal elements
const reviewModal = document.getElementById('reviewModal');
const reviewForm = document.getElementById('reviewForm');
const reviewMessageEl = document.getElementById('reviewMessage');

let currentUser = null;
let currentBookingId = null;

// Tab functionality
function switchTab(tabName) {
  tabs.forEach(tab => {
    tab.classList.toggle('active', tab.getAttribute('data-tab') === tabName);
  });
  tabContents.forEach(content => {
    content.classList.toggle('active', content.id === `${tabName}-tab`);
  });
  
  // Load data for active tab
  if (tabName === 'current') {
    loadCurrentBookings();
  } else if (tabName === 'past') {
    loadPastBookings();
  } else if (tabName === 'reviews') {
    loadReviews();
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
  reviewMessageEl.textContent = message;
  reviewMessageEl.className = success ? 'message success' : 'message';
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
    }
  } catch (error) {
    console.error('Error loading user info:', error);
  }
}

// Load current bookings
async function loadCurrentBookings() {
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

// Load reviews
async function loadReviews() {
  try {
    const response = await fetch('/backend/user_reviews.php', {
      method: 'GET',
      credentials: 'same-origin'
    });
    
    if (response.status === 401) {
      window.location.href = '/login.html';
      return;
    }
    
    const result = await response.json();
    if (result.success) {
      renderReviews(result.reviews);
    }
  } catch (error) {
    console.error('Error loading reviews:', error);
  }
}

// Render current bookings
function renderCurrentBookings(bookings) {
  currentBookingsTable.innerHTML = '';
  
  if (bookings.length === 0) {
    currentBookingsTable.innerHTML = '<tr><td colspan="5" style="text-align:center; color:#999;">Nu există rezervări curente.</td></tr>';
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
        ${booking.status === 'pending' ? `<button class="small-button danger" onclick="cancelBooking(${booking.id})">Anulează</button>` : '-'}
      </td>
    `;
    currentBookingsTable.appendChild(row);
  });
}

// Render past bookings
function renderPastBookings(bookings) {
  pastBookingsTable.innerHTML = '';
  
  if (bookings.length === 0) {
    pastBookingsTable.innerHTML = '<tr><td colspan="5" style="text-align:center; color:#999;">Nu există rezervări trecute.</td></tr>';
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
        ${booking.status === 'confirmed' && !booking.has_review ? 
          `<button class="small-button review" onclick="openReviewModal(${booking.id})">Lasă Recenzie</button>` : 
          booking.has_review ? 'Recenziat' : '-'}
      </td>
    `;
    pastBookingsTable.appendChild(row);
  });
}

// Render reviews
function renderReviews(reviews) {
  reviewsTable.innerHTML = '';
  
  if (reviews.length === 0) {
    reviewsTable.innerHTML = '<tr><td colspan="4" style="text-align:center; color:#999;">Nu există recenzii.</td></tr>';
    return;
  }
  
  reviews.forEach((review) => {
    const row = document.createElement('tr');
    
    row.innerHTML = `
      <td>${new Date(review.created_at).toLocaleDateString('ro-RO')}</td>
      <td>${review.booking_name || 'Rezervare #' + review.booking_id}</td>
      <td>${'★'.repeat(review.rating)}${'☆'.repeat(5 - review.rating)}</td>
      <td>${review.review_text ? review.review_text.substring(0, 50) + (review.review_text.length > 50 ? '...' : '') : '-'}</td>
    `;
    reviewsTable.appendChild(row);
  });
}

// Open review modal
function openReviewModal(bookingId) {
  currentBookingId = bookingId;
  reviewModal.style.display = 'flex';
  document.body.style.overflow = 'hidden';
  
  // Reset form
  reviewForm.reset();
  showMessage('');
}

// Close review modal
function closeReviewModal() {
  reviewModal.style.display = 'none';
  document.body.style.overflow = '';
  currentBookingId = null;
}

// Submit review
async function submitReview(event) {
  event.preventDefault();
  
  const formData = new FormData(reviewForm);
  const rating = formData.get('rating');
  const reviewText = formData.get('reviewText');
  
  if (!rating) {
    showMessage('Te rugăm să alegi un rating.');
    return;
  }
  
  try {
    const response = await fetch('/backend/user_reviews.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        booking_id: currentBookingId,
        rating: parseInt(rating),
        review_text: reviewText
      })
    });
    
    const result = await response.json();
    if (result.success) {
      showMessage('Recenzia trimisă cu succes!', true);
      closeReviewModal();
      loadPastBookings();
      loadReviews();
    } else {
      showMessage(result.error || 'Nu s-a putut trimite recenzia.');
    }
  } catch (error) {
    showMessage('Eroare de rețea. Încearcă din nou.');
  }
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
      loadCurrentBookings();
      loadPastBookings();
    } else {
      alert(result.error || 'Nu s-a putut anula rezervarea.');
    }
  } catch (error) {
    alert('Eroare de rețea. Încearcă din nou.');
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
reviewForm.addEventListener('submit', submitReview);

// Close modal when clicking outside
reviewModal.addEventListener('click', (event) => {
  if (event.target === reviewModal) {
    closeReviewModal();
  }
});

// Initialize
document.addEventListener('DOMContentLoaded', () => {
  loadUserInfo();
  loadCurrentBookings();
});
