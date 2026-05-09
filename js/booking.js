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

  function openModal() {
    overlay.classList.add('open');
    document.body.style.overflow = 'hidden';
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
    const timeInput = document.getElementById('bookingTime');
    const notesInput = document.getElementById('bookingNotes');

    if (nameInput) nameInput.value = '';
    if (emailInput) emailInput.value = '';
    if (timeInput) timeInput.value = '';
    if (notesInput) notesInput.value = '';
  }

  function validateEmail(value) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
  }

  function submitBooking() {
    const nameInput = document.getElementById('bookingName');
    const emailInput = document.getElementById('bookingEmail');
    const timeInput = document.getElementById('bookingTime');
    const notesInput = document.getElementById('bookingNotes');

    const name = nameInput?.value?.trim() || '';
    const email = emailInput?.value?.trim() || '';
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
      notes
    };

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
          throw new Error(message);
        }

        resetForm();
        closeModal();

        successOverlay.classList.add('open');
        document.body.style.overflow = 'hidden';
      })
      .catch(function (error) {
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
