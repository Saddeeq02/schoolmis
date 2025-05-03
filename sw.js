const CACHE_VERSION = 'v1';
const CACHE_NAME = `schoolmis-${CACHE_VERSION}`;

// Cache these URLs immediately when service worker is installed
const PRECACHE_URLS = [
    '/',
    '/index.php',
    '/login.php',
    '/offline.html',
    '/assets/clean-styles.css',
    '/assets/styles.css',
    '/assets/scripts.js',
    '/assets/css/modern.css',
    '/teacher/dashboard.php',
    '/teacher/attendance.php',
    '/teacher/record_audio.php',
    '/components/offline_indicator.php',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css',
    'https://rawgit.com/schmich/instascan-builds/master/instascan.min.js'
];

// APIs that should be handled by IndexedDB, not the cache
const API_URLS = [
    '/teacher/save_recordings.php',
    '/teacher/attendance.php'
];

// Install event - cache core assets
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => cache.addAll(PRECACHE_URLS))
            .then(() => self.skipWaiting())
    );
});

// Activate event - cleanup old caches
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys()
            .then(cacheNames => {
                return Promise.all(
                    cacheNames
                        .filter(cacheName => cacheName.startsWith('schoolmis-') && cacheName !== CACHE_NAME)
                        .map(cacheName => caches.delete(cacheName))
                );
            })
            .then(() => self.clients.claim())
    );
});

// Helper function to check if a request is an API call
function isApiRequest(request) {
    return API_URLS.some(url => request.url.includes(url));
}

// Helper function to determine if a response should be cached
function shouldCache(response) {
    // Only cache successful responses
    if (!response || response.status !== 200) return false;
    
    // Don't cache responses that say not to be cached
    const cacheControl = response.headers.get('Cache-Control');
    if (cacheControl && cacheControl.includes('no-store')) return false;
    
    return true;
}

// Network-first strategy with fallback to cache
async function networkFirstStrategy(request) {
    try {
        const response = await fetch(request);
        if (shouldCache(response)) {
            const cache = await caches.open(CACHE_NAME);
            cache.put(request, response.clone());
        }
        return response;
    } catch (error) {
        const cachedResponse = await caches.match(request);
        if (cachedResponse) return cachedResponse;
        
        // If it's a page navigation, return offline page
        if (request.mode === 'navigate') {
            return caches.match('/offline.html');
        }
        
        throw error;
    }
}

// Cache-first strategy with network fallback
async function cacheFirstStrategy(request) {
    const cachedResponse = await caches.match(request);
    if (cachedResponse) return cachedResponse;
    
    try {
        const response = await fetch(request);
        if (shouldCache(response)) {
            const cache = await caches.open(CACHE_NAME);
            cache.put(request, response.clone());
        }
        return response;
    } catch (error) {
        if (request.mode === 'navigate') {
            return caches.match('/offline.html');
        }
        throw error;
    }
}

// Fetch event - handle all requests
self.addEventListener('fetch', event => {
    // Don't handle non-GET requests
    if (event.request.method !== 'GET') return;
    
    // Skip API requests (they should be handled by IndexedDB)
    if (isApiRequest(event.request)) return;
    
    // Use appropriate caching strategy based on request type
    if (event.request.mode === 'navigate' || 
        event.request.url.includes('/teacher/') ||
        event.request.url.includes('/admin/')) {
        // Use network-first for main pages and dynamic content
        event.respondWith(networkFirstStrategy(event.request));
    } else {
        // Use cache-first for static assets
        event.respondWith(cacheFirstStrategy(event.request));
    }
});

// Handle offline sync requests
self.addEventListener('sync', event => {
    if (event.tag === 'sync-attendance') {
        event.waitUntil(syncAttendance());
    } else if (event.tag === 'sync-recordings') {
        event.waitUntil(syncRecordings());
    }
});

// Optional: Handle push notifications if needed
self.addEventListener('push', event => {
    if (!event.data) return;
    
    const data = event.data.json();
    const options = {
        body: data.body,
        icon: '/assets/icons/icon-192x192.png',
        badge: '/assets/icons/badge-72x72.png',
        vibrate: [100, 50, 100],
        data: {
            url: data.url
        }
    };
    
    event.waitUntil(
        self.registration.showNotification(data.title, options)
    );
});