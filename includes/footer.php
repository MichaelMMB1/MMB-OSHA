  </main>
  <footer class="site-footer">
    <div class="container">
      © <?= date('Y') ?> MMB Contractors. All rights reserved.
    </div>
  </footer>


  <style>
    .site-footer .container {
  text-align: center;
  b
}
</style>




  <!-- CLICK-ONLY PROFILE DROPDOWN SCRIPT -->
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const container = document.querySelector('.navbar-profile-container');
      if (!container) {
        console.warn('Profile container not found');
        return;
      }
      const avatar = container.querySelector('.navbar-profile');
      if (!avatar) {
        console.warn('Avatar element not found');
        return;
      }

      // 1) Toggle on avatar click
      avatar.addEventListener('click', function(e) {
        e.stopPropagation();
        container.classList.toggle('open');
      });

      // 2) Close when clicking anywhere else
      document.addEventListener('click', function(e) {
        if (!container.contains(e.target)) {
          container.classList.remove('open');
        }
      });
    });
  </script>
</body>
</html>


