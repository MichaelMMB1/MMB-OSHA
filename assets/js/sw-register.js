// public/assets/js/sw-register.js

if ('serviceWorker' in navigator) {
  window.addEventListener('load', async () => {
    try {
      const reg = await navigator.serviceWorker.register('/service-worker.js', {
        scope: '/'
      });
      console.log('✅ Service Worker registered with scope:', reg.scope);
    } catch (err) {
      console.error('❌ SW registration failed:', err);
    }
  });
}
