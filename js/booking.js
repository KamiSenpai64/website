/* ============================================================
   ASTRO TAROT — booking.js
   Handles the booking modal on index.html
   Include after main.js:
   <script src="js/booking.js"></script>
   ============================================================ */

(function initBooking() {
  const overlay = document.getElementById('modalOverlay');
  if (!overlay) return;

  /* Open */
  window.openModal = function () {
    overlay.classList.add('open');
    document.body.style.overflow = 'hidden';
  };

  /* Close */
  window.closeModal = function () {
    overlay.classList.remove('open');
    document.body.style.overflow = '';
  };

  /* Click outside */
  overlay.addEventListener('click', e => {
    if (e.target === overlay) closeModal();
  });

  /* ESC key */
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeModal();
  });

  /* Submit */
  window.submitBooking = function () {
    const name  = document.querySelector('#modalOverlay input[type="text"]')?.value?.trim();
    const email = document.querySelector('#modalOverlay input[type="email"]')?.value?.trim();

    if (!name) {
      alert('Te rog introdu numele tău.');
      return;
    }
    if (!email || !email.includes('@')) {
      alert('Te rog introdu o adresă de email validă.');
      return;
    }

    /* ✏️ EDITEAZĂ: Înlocuiește cu logica ta reală (ex: trimitere email, Calendly, etc.) */
    console.log('Booking request:', { name, email });

    alert(`✨ Mulțumesc, ${name}! Te voi contacta la ${email} în 24 de ore pentru a confirma lectura ta. Stelele te așteaptă.`);
    closeModal();
  };
})();
