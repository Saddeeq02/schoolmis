<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>School Management System</title>
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#4A90E2">
    <link rel="apple-touch-icon" href="assets/icons/icon-192x192.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/styles.css">
    <script src="assets/scripts.js"></script>
    <style>
        .install-prompt {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: #4A90E2;
            color: white;
            padding: 15px 25px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            display: none;
            z-index: 1000;
            text-align: center;
        }
        .install-prompt button {
            margin: 10px 5px;
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        #install-button {
            background: white;
            color: #4A90E2;
        }
        #dismiss-install {
            background: transparent;
            color: white;
            border: 1px solid white;
        }
        #networkStatus {
            position: fixed;
            top: 10px;
            right: 10px;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9em;
            z-index: 1000;
        }
        .status-online {
            background: #28a745;
            color: white;
        }
        .status-offline {
            background: #ffc107;
            color: black;
        }
    </style>
</head>
<body>
    <div id="networkStatus"></div>
    <div class="container">
        <div class="card text-center">
            <h1>Welcome to School Management System</h1>
            
            <div class="dashboard-grid">
                <a href="login.php" class="card">
                    <i class="fas fa-sign-in-alt mb-3" style="font-size: 2rem; color: var(--primary);"></i>
                    <h3>Login</h3>
                    <p class="text-light">Access your account</p>
                </a>
                
                <a href="register.php" class="card">
                    <i class="fas fa-user-plus mb-3" style="font-size: 2rem; color: var(--secondary);"></i>
                    <h3>Register</h3>
                    <p class="text-light">Create a new account</p>
                </a>
                
                <a href="student_results.php" class="card">
                    <i class="fas fa-graduation-cap mb-3" style="font-size: 2rem; color: var(--success);"></i>
                    <h3>Student Results</h3>
                    <p class="text-light">Check your exam results</p>
                </a>
            </div>
            
            <div class="mt-4">
                <a href="login.php" class="btn btn-lg">Get Started</a>
            </div>
        </div>
    </div>

    <div id="installPrompt" class="install-prompt">
        <p><i class="fas fa-download"></i> Install SchoolMIS for offline access</p>
        <button id="install-button">Install Now</button>
        <button id="dismiss-install">Maybe Later</button>
    </div>

    <script>
        // Register the service worker
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', async () => {
                try {
                    const registration = await navigator.serviceWorker.register('/sw.js', {
                        scope: '/'
                    });
                    console.log('Service Worker registered:', registration.scope);

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
                } catch (error) {
                    console.error('Service Worker registration failed:', error);
                }
            });
        }

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
    </script>
</body>
</html>
