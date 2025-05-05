// Service Worker for SchoolMIS
const CACHE_VERSION = 'v4';
const CACHE_NAME = `schoolmis-${CACHE_VERSION}`;
const STATIC_CACHE = `${CACHE_NAME}-static`;
const DYNAMIC_CACHE = `${CACHE_NAME}-dynamic`;
const OFFLINE_PAGE = '/offline.html';

// Cache these URLs immediately when service worker is installed
const PRECACHE_URLS = [
    '/teacher/dashboard.php',
    '/teacher/attendance.php',
    '/teacher/record_audio.php',
    '/teacher/view_recordings.php',
    '/manifest.json',
    '/offline.html',
    '/assets/clean-styles.css',
    '/assets/styles.css',
    '/assets/scripts.js',
    '/includes/auth.php',
    '/includes/db.php',
    '/includes/functions.php',
    '/components/offline_indicator.php',
    '/assets/icons/icon-192x192.png',
    '/assets/icons/icon-512x512.png',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css'
];

// URLs that should work offline (cache-first strategy)
const OFFLINE_FIRST_URLS = [
    { urlPattern: '/teacher/dashboard.php', strategy: 'cache-first' },
    { urlPattern: '/teacher/attendance.php', strategy: 'cache-first' },
    { urlPattern: '/teacher/record_audio.php', strategy: 'cache-first' },
    { urlPattern: '/teacher/view_recordings.php', strategy: 'cache-first' }
];

// Open IndexedDB for request queue
function openRequestQueue() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open('RequestQueue', 1);
        
        request.onupgradeneeded = event => {
            const db = event.target.result;
            if (!db.objectStoreNames.contains('requests')) {
                db.createObjectStore('requests', { keyPath: 'id', autoIncrement: true });
            }
        };
        
        request.onsuccess = event => resolve(event.target.result);
        request.onerror = event => reject(event.target.error);
    });
}

// Save failed request to IndexedDB queue
async function saveRequestToQueue(request) {
    const db = await openRequestQueue();
    const clone = request.clone();
    const data = {
        url: clone.url,
        method: clone.method,
        headers: Array.from(clone.headers.entries()),
        timestamp: Date.now(),
        body: await clone.text()
    };
    
    return new Promise((resolve, reject) => {
        const tx = db.transaction('requests', 'readwrite');
        const store = tx.objectStore('requests');
        const request = store.add(data);
        
        request.onsuccess = () => resolve();
        request.onerror = event => reject(event.target.error);
    });
}

// Process all queued requests
async function processQueue(tag) {
    const db = await openRequestQueue();
    const tx = db.transaction('requests', 'readwrite');
    const store = tx.objectStore('requests');
    const requests = await new Promise((resolve, reject) => {
        const request = store.getAll();
        request.onsuccess = () => resolve(request.result);
        request.onerror = event => reject(event.target.error);
    });
    
    // Filter requests based on sync tag
    const filteredRequests = requests.filter(req => {
        if (tag === 'sync-attendance') {
            return req.url.includes('attendance.php');
        } else if (tag === 'sync-recordings') {
            return req.url.includes('save_recordings.php');
        }
        return false;
    });
    
    // Process each request
    for (const req of filteredRequests) {
        try {
            const response = await fetch(req.url, {
                method: req.method,
                headers: new Headers(req.headers),
                body: req.method !== 'GET' ? req.body : undefined
            });
            
            if (response.ok) {
                // Remove from queue if successful
                await new Promise((resolve, reject) => {
                    const request = store.delete(req.id);
                    request.onsuccess = () => resolve();
                    request.onerror = event => reject(event.target.error);
                });
            }
        } catch (error) {
            console.error('Failed to process queued request:', error);
        }
    }
}

// Install event - cache core assets
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then(cache => cache.addAll(PRECACHE_URLS))
            .then(() => self.skipWaiting())
    );
});

// Activate event - clean up old caches
self.addEventListener('activate', event => {
    event.waitUntil(
        Promise.all([
            caches.keys().then(keys => Promise.all(
                keys.filter(key => key.startsWith('schoolmis-') && key !== STATIC_CACHE)
                    .map(key => caches.delete(key))
            )),
            self.clients.claim()
        ])
    );
});

// Helper function to check if a request is an API call
function isApiRequest(request) {
    return API_URLS.some(url => request.url.includes(url)) && request.method === 'POST';
}

// Helper function to determine if a response should be cached
function shouldCache(response) {
    if (!response || !response.ok) return false;
    
    const cacheControl = response.headers.get('Cache-Control');
    if (cacheControl && (cacheControl.includes('no-store') || cacheControl.includes('no-cache'))) return false;
    
    const url = new URL(response.url);
    if (url.search && url.search.length > 0) return false;
    
    return true;
}

// Fetch event - handle all requests
self.addEventListener('fetch', event => {
    const url = new URL(event.request.url);
    
    // Check if this is a teacher page that should work offline
    const offlineFirst = OFFLINE_FIRST_URLS.find(
        route => url.pathname.includes(route.urlPattern)
    );
    
    if (offlineFirst) {
        event.respondWith(
            caches.match(event.request)
                .then(cachedResponse => {
                    if (cachedResponse) {
                        // Return cached response and update cache in background
                        const fetchPromise = fetch(event.request)
                            .then(networkResponse => {
                                const cache = caches.open(STATIC_CACHE)
                                    .then(cache => cache.put(event.request, networkResponse.clone()));
                                return networkResponse;
                            })
                            .catch(() => cachedResponse);
                        
                        return cachedResponse;
                    }
                    
                    // If not in cache, try network
                    return fetch(event.request)
                        .then(response => {
                            const responseClone = response.clone();
                            caches.open(STATIC_CACHE)
                                .then(cache => cache.put(event.request, responseClone));
                            return response;
                        })
                        .catch(() => {
                            // If offline and not in cache, show offline page
                            return caches.match(OFFLINE_PAGE);
                        });
                })
        );
        return;
    }

    // For all other requests, try network first, then cache
    event.respondWith(
        fetch(event.request)
            .then(response => {
                // Clone the response before using it
                const responseClone = response.clone();
                
                // Only cache successful responses
                if (response.ok) {
                    caches.open(DYNAMIC_CACHE)
                        .then(cache => cache.put(event.request, responseClone));
                }
                
                return response;
            })
            .catch(() => {
                // If network fails, try cache
                return caches.match(event.request)
                    .then(cachedResponse => {
                        // Return cached response or offline page
                        return cachedResponse || caches.match(OFFLINE_PAGE);
                    });
            })
    );
});

// Handle offline sync requests
self.addEventListener('sync', event => {
    if (event.tag === 'sync-attendance') {
        event.waitUntil(processQueue('sync-attendance'));
    } else if (event.tag === 'sync-recordings') {
        event.waitUntil(processQueue('sync-recordings'));
    }
});

// Handle push notifications
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

// Handle notification clicks
self.addEventListener('notificationclick', event => {
    event.notification.close();
    
    if (event.notification.data && event.notification.data.url) {
        event.waitUntil(
            clients.openWindow(event.notification.data.url)
        );
    }
});