// Register the service worker
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register('/sw.js')
        .then(registration => {
          console.log('Service Worker registered with scope:', registration.scope);
          
          // Check for updates
          registration.addEventListener('updatefound', () => {
            const newWorker = registration.installing;
            newWorker.addEventListener('statechange', () => {
              if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                // New service worker available
                showUpdateNotification();
              }
            });
          });
        })
        .catch(error => {
          console.error('Service Worker registration failed:', error);
        });
        
      // Register periodic sync if supported
      if ('periodicSync' in navigator.serviceWorker) {
        navigator.serviceWorker.ready.then(async registration => {
          try {
            await registration.periodicSync.register('update-content', {
              minInterval: 24 * 60 * 60 * 1000 // Once per day
            });
            console.log('Periodic sync registered');
          } catch (error) {
            console.log('Periodic sync could not be registered:', error);
          }
        });
      }
    });
    
    // Listen for messages from the service worker
    navigator.serviceWorker.addEventListener('message', event => {
      if (event.data && event.data.type === 'CONTENT_UPDATED') {
        console.log('Content updated in background at:', event.data.timestamp);
        showContentUpdateNotification();
      }
    });
    
    // Function to show update notification
    function showUpdateNotification() {
      const updateBanner = document.createElement('div');
      updateBanner.className = 'update-banner';
      updateBanner.innerHTML = `
        <p>A new version is available!</p>
        <button id="update-app">Update Now</button>
        <button id="dismiss-update">Later</button>
      `;
      document.body.appendChild(updateBanner);
      
      document.getElementById('update-app').addEventListener('click', () => {
        navigator.serviceWorker.controller.postMessage({ type: 'SKIP_WAITING' });
        window.location.reload();
      });
      
      document.getElementById('dismiss-update').addEventListener('click', () => {
        updateBanner.remove();
      });
    }
    
    // Function to show content update notification
    function showContentUpdateNotification() {
      const notification = document.createElement('div');
      notification.className = 'content-update-notification';
      notification.innerHTML = `
        <p>New content is available!</p>
        <button id="refresh-content">Refresh</button>
        <button id="dismiss-notification">Dismiss</button>
      `;
      document.body.appendChild(notification);
      
      document.getElementById('refresh-content').addEventListener('click', () => {
        window.location.reload();
      });
      
      document.getElementById('dismiss-notification').addEventListener('click', () => {
        notification.remove();
      });
    }
  }
  
  // Network status indicator
  function updateNetworkStatus() {
    const statusElement = document.getElementById('networkStatus');
    if (!statusElement) return;
    
    if (navigator.onLine) {
      statusElement.textContent = 'Online';
      statusElement.className = 'status-online';
      
      // Trigger sync if needed
      if ('serviceWorker' in navigator && 'SyncManager' in window) {
        navigator.serviceWorker.ready.then(registration => {
          registration.sync.register('sync-attendance');
          registration.sync.register('sync-recordings');
        }).catch(err => console.error('Sync registration failed:', err));
      }
    } else {
      statusElement.textContent = 'Offline';
      statusElement.className = 'status-offline';
    }
  }
  
  window.addEventListener('online', updateNetworkStatus);
  window.addEventListener('offline', updateNetworkStatus);
  updateNetworkStatus();

  // Add this to your main JavaScript file (e.g., assets/scripts.js)

let deferredPrompt;
const installBanner = document.createElement('div');

// Style the banner
installBanner.className = 'install-banner';
installBanner.innerHTML = `
  <div class="install-content">
    <img src="/assets/icons/icon-192x192.png" alt="SchoolMIS" class="install-icon">
    <div class="install-text">
      <h3>Install SchoolMIS App</h3>
      <p>Install this app on your device for quick access even when offline!</p>
    </div>
  </div>
  <div class="install-actions">
    <button id="install-button">Install Now</button>
    <button id="dismiss-install">Maybe Later</button>
  </div>
`;

// Add CSS for the banner
const style = document.createElement('style');
style.textContent = `
  .install-banner {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    background-color: #4A90E2;
    color: white;
    padding: 15px 20px;
    display: flex;
    flex-direction: column;
    z-index: 9999;
    box-shadow: 0 -2px 10px rgba(0,0,0,0.2);
    animation: slide-up 0.5s ease;
  }
  
  @keyframes slide-up {
    from { transform: translateY(100%); }
    to { transform: translateY(0); }
  }
  
  .install-content {
    display: flex;
    align-items: center;
    margin-bottom: 10px;
  }
  
  .install-icon {
    width: 50px;
    height: 50px;
    margin-right: 15px;
    border-radius: 10px;
  }
  
  .install-text {
    flex: 1;
  }
  
  .install-text h3 {
    margin: 0 0 5px 0;
    font-size: 18px;
  }
  
  .install-text p {
    margin: 0;
    font-size: 14px;
    opacity: 0.9;
  }
  
  .install-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
  }
  
  #install-button {
    background-color: white;
    color: #4A90E2;
    border: none;
    padding: 8px 16px;
    border-radius: 4px;
    font-weight: bold;
    cursor: pointer;
  }
  
  #dismiss-install {
    background: transparent;
    color: white;
    border: 1px solid white;
    padding: 8px 16px;
    border-radius: 4px;
    cursor: pointer;
  }
  
  @media (min-width: 768px) {
    .install-banner {
      flex-direction: row;
      align-items: center;
    }
    
    .install-content {
      margin-bottom: 0;
      margin-right: 20px;
    }
  }
`;

// Ensure the install prompt is shown only when appropriate
window.addEventListener('beforeinstallprompt', (e) => {
    // Prevent the mini-infobar from appearing on mobile
    e.preventDefault();

    // Stash the event so it can be triggered later
    deferredPrompt = e;

    // Check if the prompt should be shown
    if (shouldShowInstallPrompt()) {
        // Add the banner to the page
        document.head.appendChild(style);
        document.body.appendChild(installBanner);

        // Add click event for install button
        document.getElementById('install-button').addEventListener('click', async () => {
            // Hide the app install banner
            installBanner.style.display = 'none';

            // Show the install prompt
            deferredPrompt.prompt();

            // Wait for the user to respond to the prompt
            const { outcome } = await deferredPrompt.userChoice;

            // Clear the prompt
            deferredPrompt = null;

            // Log the user's response
            console.log(`User response to the install prompt: ${outcome}`);
        });

        // Add click event for dismiss button
        document.getElementById('dismiss-install').addEventListener('click', () => {
            installBanner.style.display = 'none';

            // Set a flag in localStorage to not show again for some time
            localStorage.setItem('installPromptDismissed', Date.now().toString());
        });
    }
});

// Only show the install prompt if it hasn't been dismissed recently
function shouldShowInstallPrompt() {
  const lastDismissed = localStorage.getItem('installPromptDismissed');
  if (!lastDismissed) return true;
  
  // Don't show again for 3 days after dismissal
  const threeDaysInMs = 3 * 24 * 60 * 60 * 1000;
  return (Date.now() - parseInt(lastDismissed)) > threeDaysInMs;
}
// Check if user is authenticated offline
function isOfflineAuthenticated() {
    try {
        const authData = localStorage.getItem('offline_auth');
        if (!authData) return false;
        
        const auth = JSON.parse(authData);
        
        // Check if data exists and token hasn't expired
        if (!auth || !auth.token || !auth.expiry) {
            return false;
        }
        
        // Check if token is expired
        if (auth.expiry < Math.floor(Date.now() / 1000)) {
            // Clear expired data
            localStorage.removeItem('offline_auth');
            return false;
        }
        
        return auth; // Return the auth data if valid
    } catch (e) {
        console.error('Error checking offline authentication:', e);
        return false;
    }
}

// Function to handle offline pages that require authentication
function handleOfflineAuth() {
    if (!navigator.onLine) {
        const auth = isOfflineAuthenticated();
        
        // If not authenticated and not on login page
        if (!auth && !window.location.pathname.includes('login.php')) {
            // Redirect to login with offline flag
            window.location.href = '/login.php?offline=true';
            return false;
        }
        
        // If authenticated, make auth data available to the page
        if (auth) {
            window.offlineUser = auth;
            
            // Add an offline indicator to the page
            const offlineIndicator = document.createElement('div');
            offlineIndicator.className = 'offline-indicator';
            offlineIndicator.innerHTML = '<i class="fas fa-wifi-slash"></i> Offline Mode';
            document.body.appendChild(offlineIndicator);
            
            // Add styles for the offline indicator
            const style = document.createElement('style');
            style.textContent = `
                .offline-indicator {
                    position: fixed;
                    top: 10px;
                    right: 10px;
                    background-color: #ff9800;
                    color: white;
                    padding: 5px 10px;
                    border-radius: 4px;
                    font-size: 14px;
                    z-index: 9999;
                    display: flex;
                    align-items: center;
                    gap: 5px;
                }
            `;
            document.head.appendChild(style);
        }
    }
    return true;
}

// Run the offline auth check when the page loads
document.addEventListener('DOMContentLoaded', handleOfflineAuth);

// Also check when online status changes
window.addEventListener('online', handleOfflineAuth);
window.addEventListener('offline', handleOfflineAuth);