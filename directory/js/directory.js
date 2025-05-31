// public/directory/js/directory.js
document.addEventListener('DOMContentLoaded', () => {

  //
  // 1) INLINE-EDIT SAVE (UNCHANGED)
  //
  document.querySelectorAll('table[data-endpoint]').forEach(table => {
    const endpoint = table.dataset.endpoint;

    table.querySelectorAll('.editable').forEach(el => {
      const isTd      = el.tagName === 'TD';
      const eventType = isTd ? 'blur' : 'change';
      if (isTd) el.contentEditable = 'true';

      el.addEventListener(eventType, async () => {
        const tr    = el.closest('tr');
        const id    = tr.dataset.id;
        const field = el.dataset.field;
        const value = isTd ? el.textContent.trim() : el.value;

        const res  = await fetch(endpoint, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ id, field, value })
        });
        const json = await res.json();

        if (!json.success) {
          alert(`Save failed for ${field}: ${json.error || 'Unknown error'}`);
        }
      });
    });
  });


  //
  // 2) LOGIN-INFO MODAL (STANDARD STYLING)
  //
  // Grab your one shared modal elements
  const loginModal      = document.getElementById('loginInfoModal');
  const closeBtn        = document.getElementById('loginModalClose');
  const cancelBtn       = document.getElementById('cancelLoginModal');
  const modalUsername   = document.getElementById('modalUsername');
  const modalEmail      = document.getElementById('modalEmail');
  const modalUserId     = document.getElementById('modalUserId');
  const modalResetLink  = document.getElementById('modalResetLink');

  // Open it when any Login-Info button is clicked
  document.querySelectorAll('.btn-login-info').forEach(btn => {
    btn.addEventListener('click', () => {
      const id = btn.dataset.userid;
      modalUsername.textContent = btn.dataset.username;
      modalEmail.textContent    = btn.dataset.email;
      modalUserId.value         = id;
      modalResetLink.href       = `/admin/reset_password.php?user_id=${id}`;

      // show the standard modal
      loginModal.classList.add('show');
    });
  });

  // Close via the × button
  closeBtn.addEventListener('click', () => {
    loginModal.classList.remove('show');
  });

  // Close via the Cancel button
  cancelBtn.addEventListener('click', () => {
    loginModal.classList.remove('show');
  });

  // Close by clicking outside the modal-box (on the overlay)
  loginModal.addEventListener('click', e => {
    if (e.target === loginModal) {
      loginModal.classList.remove('show');
    }
  });

});
// Shared Login‐Info modal handlers
const loginModal     = document.getElementById('loginInfoModal');
const closeBtn       = document.getElementById('loginModalClose');
const cancelBtn      = document.getElementById('cancelLoginModal');
const modalUsername  = document.getElementById('modalUsername');
const modalEmail     = document.getElementById('modalEmail');
const modalUserId    = document.getElementById('modalUserId');
const modalResetLink = document.getElementById('modalResetLink');

document.querySelectorAll('.btn-login-info').forEach(btn => {
  btn.addEventListener('click', () => {
    // pull directly from the button's data-attributes:
    const id       = btn.dataset.userid;
    const username = btn.dataset.username;
    const email    = btn.dataset.email;

    // inject into the modal
    modalUsername.textContent = username;
    modalEmail.textContent    = email;
    modalUserId.value         = id;
    modalResetLink.href       =
      `/admin/reset_password.php?user_id=${encodeURIComponent(id)}`;

    // show your standard modal
    loginModal.classList.add('show');
  });
});

closeBtn.addEventListener('click', () => {
  loginModal.classList.remove('show');
});
cancelBtn.addEventListener('click', () => {
  loginModal.classList.remove('show');
});
loginModal.addEventListener('click', e => {
  if (e.target === loginModal) {
    loginModal.classList.remove('show');
  }
});