<?php
// Prevent any output before JSON response
ob_start();

session_start();
include '../includes/auth.php';
include '../includes/db.php';

// Verify teacher role
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Clear any output buffers
    ob_clean();
    
    header('Content-Type: application/json');
    
    try {
        // Get JSON data from request body
        $json = file_get_contents('php://input');
        error_log("Received QR data: " . $json);
        
        $data = json_decode($json, true);
        
        if (!isset($data['qr_data'])) {
            throw new Exception('Invalid request data');
        }

        if (!isLoggedIn()) {
            throw new Exception('Session expired. Please login again.');
        }

        $user_id = $_SESSION['user_id'];
        $school_name = getUserSchool();
        
        error_log("User school_name from session: " . $school_name);
        
        $qr_data = json_decode($data['qr_data'], true);
        error_log("Parsed QR data: " . print_r($qr_data, true));
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid QR code format: ' . json_last_error_msg());
        }
        
        // Verify QR code is for this school - case-insensitive comparison
        if (!isset($qr_data['school_name']) || 
            trim(strtoupper($qr_data['school_name'])) !== trim(strtoupper($school_name))) {
            error_log("School name mismatch - QR: " . $qr_data['school_name'] . 
                     " vs Session: " . $school_name);
            throw new Exception('Invalid QR code for this school');
        }
        
        // Check if user has an open attendance record
        $check_stmt = $pdo->prepare("SELECT id FROM attendance WHERE user_id = ? AND school_name = ? AND clock_out IS NULL");
        $check_stmt->execute([$user_id, $school_name]);
        $result = $check_stmt->fetch();
        
        if ($result) {
            // Clock out
            $update_stmt = $pdo->prepare("UPDATE attendance SET clock_out = NOW() WHERE id = ?");
            $update_stmt->execute([$result['id']]);
            echo json_encode([
                'success' => true, 
                'action' => 'clock_out', 
                'time' => date('Y-m-d H:i:s')
            ]);
        } else {
            // Clock in
            $insert_stmt = $pdo->prepare("INSERT INTO attendance (user_id, school_name, clock_in) VALUES (?, ?, NOW())");
            $insert_stmt->execute([$user_id, $school_name]);
            echo json_encode([
                'success' => true, 
                'action' => 'clock_in', 
                'time' => date('Y-m-d H:i:s')
            ]);
        }
    } catch (Exception $e) {
        error_log("Attendance Error: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'message' => $e->getMessage()
        ]);
    }
    exit;
}

// For non-POST requests, verify teacher role
if ($_SESSION['role'] != 'teacher') {
    header("Location: ../index.php");
    exit();
}

// Clear any buffered output before showing the HTML
ob_end_clean();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Attendance Scanner</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="manifest" href="../manifest.json">
    <meta name="theme-color" content="#4A90E2">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://rawgit.com/schmich/instascan-builds/master/instascan.min.js"></script>
    <style>
        :root {
            --primary-color: #4A90E2;
            --secondary-color: #67B26F;
            --error-color: #ff4444;
            --success-color: #00C851;
            --glass-bg: rgba(255, 255, 255, 0.1);
            --glass-border: rgba(255, 255, 255, 0.2);
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            line-height: 1.6;
            background: linear-gradient(45deg, #4A90E2, #67B26F);
            min-height: 100vh;
            padding: 20px;
            color: white;
        }

        .container {
            max-width: 100%;
            margin: 0 auto;
            padding: 20px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
            border: 1px solid var(--glass-border);
        }

        .card {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            border: 1px solid var(--glass-border);
            overflow: hidden;
        }

        .card-body {
            padding: 1.5rem;
        }

        .scanner-container {
            position: relative;
            border-radius: 15px;
            overflow: hidden;
            margin-bottom: 1rem;
        }

        #preview {
            width: 100%;
            max-height: 70vh;
            object-fit: cover;
            border-radius: 15px;
        }

        .scanner-overlay {
            position: relative;
        }

        .scanner-laser {
            position: absolute;
            width: 100%;
            height: 2px;
            background: var(--error-color);
            top: 50%;
            animation: scan 2s infinite;
            box-shadow: 0 0 8px var(--error-color);
        }

        .status-container {
            position: relative;
            margin-top: 1.5rem;
            padding: 1rem;
            background: var(--glass-bg);
            border-radius: 15px;
            border: 1px solid var(--glass-border);
            text-align: center;
        }

        .clock-status {
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .status-icon {
            font-size: 2rem;
            margin-bottom: 1rem;
            animation: pulse 1.5s infinite;
        }

        .status-time {
            font-size: 0.9rem;
            opacity: 0.8;
        }

        .alert {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            color: white;
        }

        .alert-success {
            background: rgba(0, 200, 81, 0.2);
            border-color: var(--success-color);
        }

        .alert-danger {
            background: rgba(255, 68, 68, 0.2);
            border-color: var(--error-color);
        }

        .btn-secondary {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            color: white;
            transition: all 0.3s ease;
        }

        .btn-secondary:hover {
            background: var(--primary-color);
            border-color: var(--primary-color);
            transform: translateY(-2px);
        }

        @keyframes scan {
            0% { top: 20%; }
            50% { top: 80%; }
            100% { top: 20%; }
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }

        @media (max-width: 768px) {
            body {
                padding: 10px;
            }

            .container {
                padding: 15px;
            }

            .card-body {
                padding: 1rem;
            }

            #preview {
                max-height: 50vh;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h2 class="text-center mb-4"><i class="fas fa-qrcode me-2"></i>Attendance Scanner</h2>
        
        <div class="card mb-4">
            <div class="card-body">
                <div class="scanner-container">
                    <div class="scanner-overlay">
                        <video id="preview"></video>
                        <div class="scanner-laser"></div>
                    </div>
                </div>
                
                <div class="status-container" id="status-display" style="display: none;">
                    <div class="status-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="clock-status" id="clock-status">
                        <!-- Status will be inserted here -->
                    </div>
                    <div class="status-time" id="status-time">
                        <!-- Time will be inserted here -->
                    </div>
                </div>

                <div id="result" class="mt-3"></div>
            </div>
        </div>
        
        <div class="text-center">
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
            </a>
        </div>
    </div>

    <!-- Audio elements -->
    <audio id="successSound" src="https://cdn.jsdelivr.net/npm/success-audio@1.0.0/success.mp3" preload="auto"></audio>
    <audio id="errorSound">
        <source src="data:audio/mpeg;base64,//uQxAAAAAAAAAAAAAAAAAAAAAAASW5mbwAAAA8AAAADAAAGhgBVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVWqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqr///////////////////////////////////////////8AAAA8TEFNRTMuOTlyBK8AAAAAAAAAABSAJAOkQgAAgAAABobXqrnWAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA//sQxAADwAABpAAAACAAADSAAAAETEFNRTMuOTkuNVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVU=" type="audio/mpeg">
    </audio>

    <script>
        // IndexedDB configuration
        const DB_NAME = 'schoolMISDB';
        const ATTENDANCE_STORE = 'attendance';
        let db;

        // Initialize IndexedDB
        const dbRequest = indexedDB.open(DB_NAME, 1);

        dbRequest.onerror = (event) => {
            console.error('IndexedDB error:', event.target.error);
        };

        dbRequest.onupgradeneeded = (event) => {
            db = event.target.result;
            if (!db.objectStoreNames.contains(ATTENDANCE_STORE)) {
                const store = db.createObjectStore(ATTENDANCE_STORE, { keyPath: 'id', autoIncrement: true });
                store.createIndex('timestamp', 'timestamp');
                store.createIndex('synced', 'synced');
            }
        };

        dbRequest.onsuccess = (event) => {
            db = event.target.result;
            checkPendingAttendance();
        };

        // Save attendance record locally
        async function saveAttendanceLocally(qrData) {
            const transaction = db.transaction([ATTENDANCE_STORE], 'readwrite');
            const store = transaction.objectStore(ATTENDANCE_STORE);
            
            const attendanceRecord = {
                qrData: qrData,
                timestamp: new Date(),
                synced: false
            };
            
            await store.add(attendanceRecord);
            checkPendingAttendance();
        }

        // Check and display pending attendance records
        async function checkPendingAttendance() {
            const transaction = db.transaction([ATTENDANCE_STORE], 'readonly');
            const store = transaction.objectStore(ATTENDANCE_STORE);
            const request = store.index('synced').getAll(false);

            request.onsuccess = () => {
                const pendingRecords = request.result;
                const pendingCount = pendingRecords.length;
                
                const statusContainer = document.getElementById('status-display');
                if (pendingCount > 0) {
                    const pendingDiv = document.createElement('div');
                    pendingDiv.className = 'pending-sync-status';
                    pendingDiv.innerHTML = `
                        <div class="alert alert-warning">
                            <i class="fas fa-clock"></i> ${pendingCount} attendance record${pendingCount > 1 ? 's' : ''} pending sync
                            <button onclick="syncAllAttendance()" class="btn btn-sm btn-primary ms-2">
                                <i class="fas fa-sync"></i> Sync Now
                            </button>
                        </div>
                    `;
                    
                    // Insert after the clock status
                    const clockStatus = statusContainer.querySelector('.clock-status');
                    if (clockStatus) {
                        clockStatus.parentNode.insertBefore(pendingDiv, clockStatus.nextSibling);
                    } else {
                        statusContainer.appendChild(pendingDiv);
                    }
                } else {
                    const pendingDiv = statusContainer.querySelector('.pending-sync-status');
                    if (pendingDiv) {
                        pendingDiv.remove();
                    }
                }
            };
        }

        // Sync all pending attendance records
        async function syncAllAttendance() {
            if (!navigator.onLine) {
                alert('You are offline. Please check your internet connection and try again.');
                return;
            }

            const transaction = db.transaction([ATTENDANCE_STORE], 'readonly');
            const store = transaction.objectStore(ATTENDANCE_STORE);
            const request = store.index('synced').getAll(false);

            request.onsuccess = async () => {
                const pendingRecords = request.result;
                
                for (const record of pendingRecords) {
                    try {
                        const response = await fetch('attendance.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: JSON.stringify({ qr_data: JSON.stringify(record.qrData) })
                        });

                        const result = await response.json();
                        
                        if (result.success) {
                            // Mark as synced
                            const updateTx = db.transaction([ATTENDANCE_STORE], 'readwrite');
                            const updateStore = updateTx.objectStore(ATTENDANCE_STORE);
                            record.synced = true;
                            await updateStore.put(record);
                        } else {
                            throw new Error(result.message || 'Sync failed');
                        }
                    } catch (error) {
                        console.error('Sync error:', error);
                    }
                }
                
                checkPendingAttendance();
            };
        }

        // Get last clock status from localStorage if it exists
        let lastClockStatus = localStorage.getItem('lastClockStatus');
        let lastClockTime = localStorage.getItem('lastClockTime');
        
        if (lastClockStatus && lastClockTime) {
            document.getElementById('status-display').style.display = 'block';
            updateClockStatus(lastClockStatus === 'in', new Date(lastClockTime));
        }

        // Prepare audio
        const successSound = document.getElementById('successSound');
        const errorSound = document.getElementById('errorSound');

        // Initialize scanner with better options
        let scanner = new Instascan.Scanner({ 
            video: document.getElementById('preview'),
            mirror: false,
            captureImage: true,
            backgroundScan: false,
            scanPeriod: 5
        });
        
        let isProcessing = false;

        function updateClockStatus(isClockIn, time) {
            const statusDisplay = document.getElementById('status-display');
            const clockStatus = document.getElementById('clock-status');
            const statusTime = document.getElementById('status-time');
            const statusIcon = document.querySelector('.status-icon i');

            statusDisplay.style.display = 'block';
            
            if (isClockIn) {
                clockStatus.innerHTML = '<i class="fas fa-sign-in-alt text-success"></i> Currently Clocked In';
                statusIcon.className = 'fas fa-clock text-success';
            } else {
                clockStatus.innerHTML = '<i class="fas fa-sign-out-alt text-danger"></i> Currently Clocked Out';
                statusIcon.className = 'fas fa-clock text-danger';
            }
            
            statusTime.textContent = 'Last update: ' + new Date(time).toLocaleTimeString();
            
            // Save to localStorage
            localStorage.setItem('lastClockStatus', isClockIn ? 'in' : 'out');
            localStorage.setItem('lastClockTime', time);
        }

        // Modified scanner listener to support offline mode
        scanner.addListener('scan', function(content) {
            if (isProcessing) return;
            isProcessing = true;

            try {
                const qrData = JSON.parse(content);
                if (!qrData.school_name || !qrData.type || qrData.type !== 'attendance') {
                    throw new Error('Invalid QR code format');
                }
                
                if (navigator.onLine) {
                    // Online mode - try immediate sync
                    fetch('attendance.php', {
                        method: 'POST',
                        headers: { 
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({ qr_data: content })
                    })
                    .then(response => response.json())
                    .then(async data => {
                        if (data.success) {
                            successSound.play().catch(e => console.log('Audio play failed:', e));
                            
                            const isClockIn = data.action === 'clock_in';
                            updateClockStatus(isClockIn, data.time);

                            document.getElementById('result').innerHTML = `
                                <div class="alert alert-success">
                                    <i class="fas fa-${isClockIn ? 'sign-in-alt' : 'sign-out-alt'}"></i> 
                                    Successfully ${isClockIn ? 'Clocked In' : 'Clocked Out'} at ${data.time}
                                </div>
                            `;

                            if (navigator.vibrate) {
                                navigator.vibrate(200);
                            }
                        } else {
                            throw new Error(data.message);
                        }
                    })
                    .catch(async error => {
                        // If online sync fails, save locally
                        await saveAttendanceLocally(qrData);
                        document.getElementById('result').innerHTML = `
                            <div class="alert alert-warning">
                                <i class="fas fa-clock"></i> Attendance saved offline. Will sync when online.
                            </div>
                        `;
                    })
                    .finally(() => {
                        isProcessing = false;
                    });
                } else {
                    // Offline mode - save locally
                    saveAttendanceLocally(qrData)
                    .then(() => {
                        document.getElementById('result').innerHTML = `
                            <div class="alert alert-warning">
                                <i class="fas fa-clock"></i> Attendance saved offline. Will sync when online.
                            </div>
                        `;
                    })
                    .catch(error => {
                        document.getElementById('result').innerHTML = `
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle"></i> Failed to save attendance: ${error.message}
                            </div>
                        `;
                    })
                    .finally(() => {
                        isProcessing = false;
                    });
                }

            } catch (e) {
                console.error('QR parse error:', e);
                errorSound.play().catch(e => console.log('Audio play failed:', e));
                document.getElementById('result').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> Invalid QR code format
                    </div>
                `;
                isProcessing = false;
            }
        });

        // Start camera with improved error handling
        Instascan.Camera.getCameras().then(function(cameras) {
            if (cameras.length > 0) {
                const backCamera = cameras.find(camera => camera.name.toLowerCase().includes('back'));
                scanner.start(backCamera || cameras[0]).catch(function(e) {
                    console.error('Camera start error:', e);
                    document.getElementById('result').innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle"></i> Failed to start camera: ${e.message}
                        </div>
                    `;
                });
            } else {
                document.getElementById('result').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> No cameras found. Please check your device permissions.
                    </div>
                `;
            }
        }).catch(function(e) {
            console.error('Camera error:', e);
            document.getElementById('result').innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> Camera access error: ${e.message}
                </div>
            `;
        });

        // Add online/offline event listeners
        window.addEventListener('online', () => {
            updateNetworkStatus();
            checkPendingAttendance();
        });
        
        window.addEventListener('offline', () => {
            updateNetworkStatus();
            checkPendingAttendance();
        });
    </script>
</body>
</html>