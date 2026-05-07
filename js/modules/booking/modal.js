const MODAL_OPEN_CLASS = 'open';
const SUCCESS_OPEN_CLASS = 'open';

function getModalOverlay() {
  return document.getElementById('modalOverlay');
}

function getSuccessOverlay() {
  return document.getElementById('successOverlay');
}

export function openModal() {
  const overlay = getModalOverlay();
  if (!overlay) return;
  overlay.classList.add(MODAL_OPEN_CLASS);
  document.body.style.overflow = 'hidden';
}

export function closeModal() {
  const overlay = getModalOverlay();
  if (!overlay) return;
  overlay.classList.remove(MODAL_OPEN_CLASS);
  document.body.style.overflow = '';
}

export function closeModalOutside(event) {
  const overlay = getModalOverlay();
  if (!overlay) return;
  if (event.target === overlay) {
    closeModal();
  }
}

export function closeSuccess() {
  const success = getSuccessOverlay();
  if (!success) return;
  success.classList.remove(SUCCESS_OPEN_CLASS);
  document.body.style.overflow = '';
}

export function submitBooking() {
  const nameInput = document.getElementById('bookingName');
  const emailInput = document.getElementById('bookingEmail');
  const timeInput = document.getElementById('bookingTime');
  const notesInput = document.getElementById('bookingNotes');
  const submitButton = document.querySelector('.btn-submit');

  const name = nameInput?.value?.trim() ?? '';
  const email = emailInput?.value?.trim() ?? '';
  const preferredTime = timeInput?.value?.trim() ?? '';
  const notes = notesInput?.value?.trim() ?? '';

  if (!name || !email || !preferredTime) {
    window.alert('Te rog completeaza numele, adresa de email si ora preferata.');
    return;
  }

  const payload = {
    name,
    email,
    preferredTime,
    notes
  };

  if (submitButton instanceof HTMLButtonElement) {
    submitButton.disabled = true;
    submitButton.textContent = 'Se trimite...';
  }

  fetch('backend/send_booking.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify(payload)
  })
    .then(async (response) => {
      let responseData = {};
      try {
        responseData = await response.json();
      } catch (error) {
        responseData = {};
      }

      if (!response.ok || !responseData.success) {
        throw new Error(responseData.error || 'A aparut o eroare la trimiterea emailului.');
      }

      const success = getSuccessOverlay();
      if (success) {
        success.classList.add(SUCCESS_OPEN_CLASS);
        document.body.style.overflow = 'hidden';
      }

      closeModal();
    })
    .catch((error) => {
      window.alert(error.message || 'Nu am putut trimite cererea. Incearca din nou.');
    })
    .finally(() => {
      if (submitButton instanceof HTMLButtonElement) {
        submitButton.disabled = false;
        submitButton.textContent = 'Trimite Cererea de Rezervare ✦';
      }
    });
}

export function registerModalGlobals() {
  window.openModal = openModal;
  window.closeModal = closeModal;
  window.closeModalOutside = closeModalOutside;
  window.submitBooking = submitBooking;
  window.closeSuccess = closeSuccess;
}
