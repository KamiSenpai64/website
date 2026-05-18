/* ============================================================
   ASTRO TAROT — booking.js
   Booking modal handler for Astro Tarot without module syntax.
   Include after main.js:
   <script src="js/booking.js"></script>
   ============================================================ */

(function initBooking() {
  const overlay = document.getElementById('modalOverlay');
  const successOverlay = document.getElementById('successOverlay');
  const submitButton = document.querySelector('.btn-submit');

  if (!overlay || !successOverlay) {
    return;
  }

  let currentUser = null;

  // Load current user info
  async function loadUserInfo() {
    try {
      const response = await fetch('/backend/login.php', {
        method: 'GET',
        credentials: 'same-origin'
      });
      
      if (response.ok) {
        const result = await response.json();
        if (result.success && result.user) {
          currentUser = result.user;
          updateBookingForm();
        }
      }
    } catch (error) {
      console.error('Error loading user info:', error);
    }
  }

  // Load available time slots for a specific date
  async function loadAvailableTimeSlots(date) {
    const timeSelect = document.getElementById('bookingTime');
    if (!timeSelect) {
      console.error('Time select element not found');
      return;
    }
    
    console.log('Loading time slots for date:', date);
    
    // Clear existing options
    timeSelect.innerHTML = '<option value="">Se încarcă...</option>';
    
    try {
      const response = await fetch('/backend/available_time_slots.php?date=' + encodeURIComponent(date), {
        method: 'GET',
        credentials: 'same-origin'
      });
      
      console.log('Response status:', response.status);
      
      if (response.ok) {
        const result = await response.json();
        console.log('API response:', result);
        
        if (result.success) {
          updateTimeSlotOptions(result.available_times);
        } else {
          console.error('API error:', result.error);
          loadDefaultTimeSlots();
        }
      } else {
        console.error('Response not ok:', response.status);
        loadDefaultTimeSlots();
      }
    } catch (error) {
      console.error('Error loading time slots:', error);
      loadDefaultTimeSlots();
    }
  }

  // Update time slot options
  function updateTimeSlotOptions(availableTimes) {
    const timeSelect = document.getElementById('bookingTime');
    if (!timeSelect) return;
    
    // Clear existing options
    timeSelect.innerHTML = '<option value="">Alege ora preferată</option>';
    
    // Add available time slots
    availableTimes.forEach(time => {
      const option = document.createElement('option');
      option.value = time;
      option.textContent = time;
      timeSelect.appendChild(option);
    });
  }

  // Load default time slots as fallback
  function loadDefaultTimeSlots() {
    const defaultTimeSlots = [
      '09:00', '09:30', '10:00', '10:30',
      '11:00', '11:30', '12:00', '12:30',
      '13:00', '13:30', '14:00', '14:30',
      '15:00', '15:30', '16:00', '16:30',
      '17:00', '17:30', '18:00', '18:30',
      '19:00', '19:30', '20:00', '20:30'
    ];
    updateTimeSlotOptions(defaultTimeSlots);
  }

  // Update booking form with user data
  function updateBookingForm() {
    const nameInput = document.getElementById('bookingName');
    const emailInput = document.getElementById('bookingEmail');
    const timeInput = document.getElementById('bookingTime');
    const notesInput = document.getElementById('bookingNotes');
    
    if (currentUser && nameInput && emailInput) {
      nameInput.value = currentUser.name || '';
      emailInput.value = currentUser.email || '';
      nameInput.readOnly = true;
      emailInput.readOnly = true;
      nameInput.style.background = '#2a1f3a';
      emailInput.style.background = '#2a1f3a';
      nameInput.style.cursor = 'not-allowed';
      emailInput.style.cursor = 'not-allowed';
    }
  }

  function openModal() {
    overlay.classList.add('open');
    document.body.style.overflow = 'hidden';
    loadUserInfo();
    
    // Load date options
    loadDateOptions();
    
    // Add date change listener
    const dateSelect = document.getElementById('bookingDate');
    if (dateSelect) {
      // Remove existing listeners to avoid duplicates
      dateSelect.removeEventListener('change', handleDateChange);
      dateSelect.addEventListener('change', handleDateChange);
      
      // Load today's time slots by default
      const today = new Date().toISOString().split('T')[0];
      loadAvailableTimeSlots(today);
    }
  }

  // Handle date change
  function handleDateChange(event) {
    if (event.target.value) {
      loadAvailableTimeSlots(event.target.value);
    }
  }

  // Load available dates (next 30 days)
  function loadDateOptions() {
    const dateSelect = document.getElementById('bookingDate');
    if (!dateSelect) {
      console.error('Date select element not found');
      return;
    }
    
    console.log('Loading date options...');
    
    // Clear existing options
    dateSelect.innerHTML = '<option value="">Alege data preferată</option>';
    
    // Add dates for next 30 days
    const today = new Date();
    for (let i = 0; i < 30; i++) {
      const date = new Date(today);
      date.setDate(today.getDate() + i);
      
      const option = document.createElement('option');
      option.value = date.toISOString().split('T')[0];
      
      // Format date as DD/MM/YYYY
      const formattedDate = date.toLocaleDateString('ro-RO', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
      });
      
      // Add day of week
      const dayOfWeek = date.toLocaleDateString('ro-RO', { weekday: 'short' });
      option.textContent = `${dayOfWeek}, ${formattedDate}`;
      
      dateSelect.appendChild(option);
    }
    
    console.log('Date options loaded, total options:', dateSelect.options.length - 1);
  }

  function closeModal() {
    overlay.classList.remove('open');
    document.body.style.overflow = '';
  }

  function closeModalOutside(event) {
    if (event.target === overlay) {
      closeModal();
    }
  }

  function closeSuccess() {
    successOverlay.classList.remove('open');
    document.body.style.overflow = '';
  }

  function resetForm() {
    const nameInput = document.getElementById('bookingName');
    const emailInput = document.getElementById('bookingEmail');
    const dateInput = document.getElementById('bookingDate');
    const timeInput = document.getElementById('bookingTime');
    const notesInput = document.getElementById('bookingNotes');

    if (nameInput) nameInput.value = '';
    if (emailInput) emailInput.value = '';
    if (dateInput) dateInput.value = '';
    if (timeInput) timeInput.value = '';
    if (notesInput) notesInput.value = '';
  }

  function validateEmail(value) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
  }

  function submitBooking() {
    const nameInput = document.getElementById('bookingName');
    const emailInput = document.getElementById('bookingEmail');
    const dateInput = document.getElementById('bookingDate');
    const timeInput = document.getElementById('bookingTime');
    const notesInput = document.getElementById('bookingNotes');

    const name = nameInput?.value?.trim() || '';
    const email = emailInput?.value?.trim() || '';
    const bookingDate = dateInput?.value?.trim() || '';
    const preferredTime = timeInput?.value?.trim() || '';
    const notes = notesInput?.value?.trim() || '';

    if (!name) {
      window.alert('Te rog introdu numele tău.');
      return;
    }

    if (!email || !validateEmail(email)) {
      window.alert('Te rog introdu o adresă de email validă.');
      return;
    }

    if (!bookingDate) {
      window.alert('Te rog selectează data preferată.');
      return;
    }

    if (!preferredTime) {
      window.alert('Te rog selectează o oră preferată pentru întâlnire.');
      return;
    }

    if (submitButton) {
      submitButton.disabled = true;
      submitButton.textContent = 'Se trimite...';
    }

    const payload = {
      name,
      email,
      preferredTime,
      notes,
      booking_date: bookingDate
    };

    console.log('Submitting booking:', payload); // Debug log

    fetch('backend/send_booking.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(payload)
    })
      .then(function (response) {
        return response.json().then(function (data) {
          return { status: response.status, body: data };
        });
      })
      .then(function (result) {
        if (result.status !== 200 || !result.body.success) {
          var message = result.body.error || 'A apărut o eroare la trimiterea rezervării.';
          console.error('Booking error:', result); // Debug log
          throw new Error(message);
        }

        resetForm();
        closeModal();

        successOverlay.classList.add('open');
        document.body.style.overflow = 'hidden';
      })
      .catch(function (error) {
        console.error('Network error:', error); // Debug log
        window.alert(error.message || 'Nu am putut trimite cererea. Încearcă din nou.');
      })
      .finally(function () {
        if (submitButton) {
          submitButton.disabled = false;
          submitButton.textContent = 'Trimite Cererea de Rezervare ✦';
        }
      });
  }

  window.openModal = openModal;
  window.closeModal = closeModal;
  window.closeModalOutside = closeModalOutside;
  window.submitBooking = submitBooking;
  window.closeSuccess = closeSuccess;

  overlay.addEventListener('click', closeModalOutside);
  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      closeModal();
      closeSuccess();
    }
  });
})();
