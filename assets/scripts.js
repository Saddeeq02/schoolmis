// Service Worker Registration
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js')
            .then(registration => {
                console.log('ServiceWorker registration successful');
            })
            .catch(err => {
                console.log('ServiceWorker registration failed: ', err);
            });
    });
}

// Network status handling
function updateNetworkStatus() {
    const isOnline = navigator.onLine;
    document.body.classList.toggle('offline', !isOnline);
    
    // Dispatch custom event for components that need to react to network changes
    const event = new CustomEvent('networkStatusChanged', { detail: { isOnline } });
    window.dispatchEvent(event);
}

window.addEventListener('online', updateNetworkStatus);
window.addEventListener('offline', updateNetworkStatus);
updateNetworkStatus();