// public/service-worker.js

const CACHE_NAME = 'mmb-pwa-v2';
const ASSETS = [
  '/', '/manifest.json', '/style.css',
  '/directory/js/directory.js', '/directory/styles/directory.css',
  '/activity/js/tabs.js', '/activity/js/addRecord.js',
  '/activity/js/modifyRecord.js', '/activity/styles/activity_styles.css',
  '/projects/js/projects.js', '/projects/styles/projects.css',
  '/scheduler/js/scheduler.js', '/scheduler/styles/scheduler.css',
  '/assets/js/sw-register.js',
  '/assets/images/logo-white.png',
  '/assets/images/icons/icon-192.png',
  '/assets/images/icons/icon-512.png'
];

self.addEventListener('install', e =>
  e.waitUntil(caches.open(CACHE_NAME).then(c => c.addAll(ASSETS)))
);
self.addEventListener('activate', e =>
  e.waitUntil(
    caches.keys().then(keys =>
      Promise.all(keys.filter(k => k !== CACHE_NAME)
                      .map(old => caches.delete(old)))
    )
  )
);

// *** SUPER IMPORTANT: bypass ALL /api/ calls ***
self.addEventListener('fetch', evt => {
  const url = new URL(evt.request.url);

  // if it’s an API call, do nothing (let it go to network)
  if (url.pathname.startsWith('/api/') || url.pathname.startsWith('/directory/api/')) {
    return;
  }

  // only intercept GETs for your known ASSETS
  if (evt.request.method === 'GET' && ASSETS.includes(url.pathname)) {
    evt.respondWith(
      caches.match(evt.request).then(cached => cached || fetch(evt.request))
    );
  }
});
