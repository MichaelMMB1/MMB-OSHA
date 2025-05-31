// public/assets/js/header.js

// delegate all clicks, open/close profile menu
document.addEventListener('click', function onPageClick(e) {
  // 1) click on the avatar?
  let btn = e.target.closest('.navbar-profile');
  if (btn) {
    e.stopPropagation();
    let container = btn.closest('.navbar-profile-container');
    console.log('[header.js] avatar clicked, toggling open');
    container.classList.toggle('open');
    return;
  }
  // 2) otherwise close any open menus
  document
    .querySelectorAll('.navbar-profile-container.open')
    .forEach(c => {
      console.log('[header.js] click outside, closing menu');
      c.classList.remove('open');
    });
});
