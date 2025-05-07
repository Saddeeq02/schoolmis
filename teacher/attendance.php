<?php
// Prevent any output before JSON response
ob_start();

// Turn off PHP error display for AJAX requests
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    ini_set('display_errors', 0);
}

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
        
        // Get user's school information
        $stmt = $pdo->prepare("SELECT u.school_id, s.name as school_name FROM users u 
                              LEFT JOIN schools s ON u.school_id = s.id 
                              WHERE u.id = ?");
        $stmt->execute([$user_id]);
        $userSchool = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$userSchool || empty($userSchool['school_id'])) {
            // Fallback to QR code's school if user's school is not set
            $qr_data = json_decode($data['qr_data'], true);
            if (isset($qr_data['school_name']) && !empty($qr_data['school_name'])) {
                // Try to find the school ID for this school name
                $schoolStmt = $pdo->prepare("SELECT id FROM schools WHERE name = ?");
                $schoolStmt->execute([$qr_data['school_name']]);
                $school_id = $schoolStmt->fetchColumn();
                
                if (!$school_id) {
                    // Create a new school if it doesn't exist
                    $insertStmt = $pdo->prepare("INSERT INTO schools (name, code, created_at) VALUES (?, ?, NOW())");
                    $insertStmt->execute([$qr_data['school_name'], substr(md5($qr_data['school_name']), 0, 8)]);
                    $school_id = $pdo->lastInsertId();
                    
                    // Update user's school_id
                    $updateStmt = $pdo->prepare("UPDATE users SET school_id = ? WHERE id = ?");
                    $updateStmt->execute([$school_id, $user_id]);
                }
            } else {
                throw new Exception('Could not determine your school. Please contact administrator.');
            }
        } else {
            $school_id = $userSchool['school_id'];
        }
        
        error_log("User school_id: " . $school_id);
        
        $qr_data = json_decode($data['qr_data'], true);
        error_log("Parsed QR data: " . print_r($qr_data, true));
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid QR code format: ' . json_last_error_msg());
        }
        
        // Verify QR code is for this school - case-insensitive comparison
        if (isset($qr_data['school_name']) && !empty($qr_data['school_name'])) {
            // Get the name of the school from the school_id
            $schoolNameStmt = $pdo->prepare("SELECT name FROM schools WHERE id = ?");
            $schoolNameStmt->execute([$school_id]);
            $db_school_name = $schoolNameStmt->fetchColumn();
            
            $qr_school_name = trim((string)$qr_data['school_name']);
            $user_school_name = trim((string)$db_school_name);
            
            if (empty($qr_school_name)) {
                throw new Exception('Invalid QR code: missing school name');
            }
            
            // Allow attendance if the QR code school matches the user's school
            if (strtoupper($qr_school_name) !== strtoupper($user_school_name) && !empty($user_school_name)) {
                error_log("School name mismatch - QR: " . $qr_school_name . 
                         " vs User: " . $user_school_name);
                throw new Exception('Invalid QR code for this school');
            }
        }
        
        // Check if user has an open attendance record
        $check_stmt = $pdo->prepare("
            SELECT id FROM attendance 
            WHERE user_id = ? 
            AND school_id = ? 
            AND clock_out IS NULL
        ");
        $check_stmt->execute([$user_id, $school_id]);
        $result = $check_stmt->fetch();
        
        if ($result) {
            // Clock out
            $update_stmt = $pdo->prepare("
                UPDATE attendance 
                SET clock_out = NOW() 
                WHERE id = ?
            ");
            $update_stmt->execute([$result['id']]);
            echo json_encode([
                'success' => true, 
                'action' => 'clock_out',
                'school_id' => $school_id,
                'time' => date('Y-m-d H:i:s')
            ]);
        } else {
            // Clock in
            $insert_stmt = $pdo->prepare("
                INSERT INTO attendance (
                    user_id, 
                    school_id,
                    clock_in
                ) VALUES (?, ?, NOW())
            ");
            $insert_stmt->execute([
                $user_id,
                $school_id
            ]);
            echo json_encode([
                'success' => true, 
                'action' => 'clock_in',
                'school_id' => $school_id,
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
    <!-- Use jsQR which has better compatibility -->
    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
    <script src="../assets/scripts.js"></script>
    <style>
        :root {
            --primary-color: #4a90e2;
            --danger-color: #ff4444;
            --success-color: #28a745;
            --warning-color: #ffc107;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            line-height: 1.6;
            background: #f5f7fa;
            min-height: 100vh;
            padding: 20px;
            color: #333;
        }

        .container {
            max-width: 100%;
            margin: 0 auto;
            padding: 20px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .scanner-container {
            position: relative;
            border-radius: 15px;
            overflow: hidden;
            margin-bottom: 1rem;
            border: 2px solid #eee;
        }

        #preview-container {
            width: 100%;
            height: 300px;
            position: relative;
            overflow: hidden;
            border-radius: 15px;
        }

        #preview {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        #canvas {
            display: none;
        }

        .scanner-laser {
            position: absolute;
            width: 100%;
            height: 2px;
            background: var(--primary-color);
            top: 50%;
            animation: scan 2s infinite;
            box-shadow: 0 0 8px var(--primary-color);
            z-index: 100;
            pointer-events: none;
        }

        .scanner-guide {
            position: absolute;
            top: 50%;
            left: 50%;
            width: 200px;
            height: 200px;
            transform: translate(-50%, -50%);
            border: 2px solid rgba(255, 255, 255, 0.5);
            border-radius: 10px;
            box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.3);
            z-index: 10;
        }

        .status-container {
            position: relative;
            margin-top: 1.5rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 15px;
            border: 1px solid #eee;
            text-align: center;
            display: none; /* Hide initially, show after scan */
        }

        .network-status {
            position: fixed;
            top: 10px;
            right: 10px;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9em;
            z-index: 1000;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .online {
            background: var(--success-color);
            color: white;
        }

        .offline {
            background: var(--warning-color);
            color: black;
        }

        .checking {
            background: #6c757d;
            color: white;
        }

        .pending-sync {
            margin-top: 2rem;
            padding: 1rem;
            background: #fff3cd;
            border: 1px solid #ffeeba;
            border-radius: 8px;
            display: none; /* Hide initially, show when there are pending records */
        }

        .btn-sync {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .btn-sync:hover {
            background: #357ABD;
        }

        .pending-list {
            margin-top: 2rem;
        }

        .pending-item {
            background: white;
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 0.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .sync-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8em;
        }

        .sync-pending {
            background: var(--warning-color);
            color: black;
        }

        .sync-success {
            background: var(--success-color);
            color: white;
        }

        .camera-controls {
            margin-bottom: 1rem;
        }

        .camera-select {
            width: 100%;
            padding: 0.5rem;
            border-radius: 8px;
            border: 1px solid #ddd;
            margin-bottom: 0.5rem;
        }

        .debug-info {
            margin-top: 1rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
            font-family: monospace;
            font-size: 0.8rem;
            max-height: 200px;
            overflow-y: auto;
            display: none;
        }

        .camera-permission-error {
            text-align: center;
            padding: 20px;
            background-color: #f8d7da;
            border-radius: 8px;
            margin-bottom: 15px;
            display: none;
        }

        @keyframes scan {
            0% { top: 20%; }
            50% { top: 80%; }
            100% { top: 20%; }
        }

        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            .pending-item {
                flex-direction: column;
                gap: 0.5rem;
            }
        }
        .bottom-nav {
            display: none;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background-color: var(--white);
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
            padding: 10px 0;
            z-index: 1000;
        }

        .nav-link {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            color: var(--gray);
            font-size: 0.8rem;
            padding: 5px 0;
        }

        .nav-link i {
            font-size: 1.2rem;
            margin-bottom: 5px;
        }

        .nav-link.active {
            color: var(--primary);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }

            .user-info {
                flex-direction: column;
                gap: 10px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .menu-grid {
                grid-template-columns: 1fr 1fr;
            }

            .bottom-nav {
                display: flex;
                justify-content: space-around;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div id="networkStatus" class="network-status checking">
            <i class="fas fa-wifi"></i> 
            <span>Checking...</span>
        </div>

        <h2 class="text-center mb-4">
            <i class="fas fa-qrcode me-2"></i>Attendance Scanner
        </h2>
        
        <div class="card mb-4">
            <div class="card-body">
                <div id="cameraPermissionError" class="camera-permission-error">
                    <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
                    <h5>Camera Access Required</h5>
                    <p>Your browser isn't allowing camera access. This might be because:</p>
                    <ul class="text-start">
                        <li>Camera permissions are denied</li>
                        <li>You're using an older browser</li>
                        <li>Your device doesn't support camera access</li>
                    </ul>
                    <button id="retryCamera" class="btn btn-primary">
                        <i class="fas fa-redo"></i> Try Again
                    </button>
                    <div class="mt-3">
                        <small class="text-muted">For best results, use the latest version of Chrome or Firefox on a device with a camera.</small>
                    </div>
                </div>
                
                <div class="camera-controls">
                    <select id="cameraSelect" class="camera-select">
                        <option value="environment">Back Camera (default)</option>
                        <option value="user">Front Camera</option>
                    </select>
                    <div class="d-flex justify-content-between">
                        <button id="startCamera" class="btn btn-primary btn-sm">
                            <i class="fas fa-play"></i> Start Camera
                        </button>
                        <button id="toggleDebug" class="btn btn-secondary btn-sm">
                            <i class="fas fa-bug"></i> Show Debug Info
                        </button>
                    </div>
                </div>
                
                <div class="scanner-container">
                    <div id="preview-container">
                        <video id="preview" autoplay playsinline muted></video>
                        <div class="scanner-guide"></div>
                        <div class="scanner-laser"></div>
                    </div>
                    <canvas id="canvas" width="320" height="240"></canvas>
                </div>
                
                <div class="status-container" id="status-display">
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
                <div id="debugInfo" class="debug-info"></div>
            </div>
        </div>

        <!-- Pending Synchronization Section -->
        <div id="pendingSync" class="pending-sync">
            <h3>
                <i class="fas fa-clock"></i> 
                Pending Attendance Records
                <span id="pendingCount" class="badge bg-warning text-dark ms-2">0</span>
            </h3>
            <div id="pendingList" class="pending-list">
                <!-- Pending records will be listed here -->
            </div>
            <button id="syncAll" class="btn btn-sync">
                <i class="fas fa-sync"></i> Sync All Records
            </button>
        </div>
        
        <div class="text-center mt-4">
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
            </a>
        </div>
    </div>
    <nav class="bottom-nav">
            <a href="attendance.php" class="nav-link">
                <i class="fas fa-clipboard-check"></i>
                <span>Attendance</span>
            </a>
            <a href="record_audio.php" class="nav-link">
                <i class="fas fa-microphone-alt"></i>
                <span>Record</span>
            </a>
            <a href="view_recordings.php" class="nav-link">
                <i class="fas fa-headphones"></i>
                <span>Recordings</span>
            </a>
            <a href="exams_list.php" class="nav-link">
                <i class="fas fa-graduation-cap"></i>
                <span>Exams</span>
            </a>
        </nav>
    <!-- Audio elements -->
    <audio id="successSound" src="https://cdn.jsdelivr.net/npm/success-audio@1.0.0/success.mp3" preload="auto"></audio>
    <audio id="errorSound">
        <source src="data:audio/mpeg;base64,//uQxAAAAAAAAAAAAAAAAAAAAAAASW5mbwAAAA8AAAADAAAGhgBVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVWqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqr///////////////////////////////////////////8AAAA8TEFNRTMuOTlyBK8AAAAAAAAAABSAJAOkQgAAgAAABobXqrnWAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA//sQxAADwAABpAAAACAAADSAAAAETEFNRTMuOTkuNVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVU=" type="audio/mpeg">
    </audio>

    <script>
        // Debug logging
        let debugMode = false; // Start with debug mode on to help troubleshoot
        const debugInfo = document.getElementById('debugInfo');
        debugInfo.style.display = 'block'; // Show debug info by default
        document.getElementById('toggleDebug').innerHTML = '<i class="fas fa-bug"></i> Hide Debug Info';
        
        function log(message, data = null) {
            const timestamp = new Date().toLocaleTimeString();
            const logMessage = `[${timestamp}] ${message}`;
            console.log(logMessage, data || '');
            
            if (debugMode) {
                const logEntry = document.createElement('div');
                logEntry.textContent = logMessage + (data ? ': ' + JSON.stringify(data) : '');
                debugInfo.appendChild(logEntry);
                debugInfo.scrollTop = debugInfo.scrollHeight;
            }
        }
        
        // Toggle debug mode
        document.getElementById('toggleDebug').addEventListener('click', function() {
            debugMode = !debugMode;
            debugInfo.style.display = debugMode ? 'block' : 'none';
            this.innerHTML = debugMode ? 
                '<i class="fas fa-bug"></i> Hide Debug Info' : 
                '<i class="fas fa-bug"></i> Show Debug Info';
        });

        // Initialize IndexedDB
        const DB_NAME = 'attendanceDB';
        const STORE_NAME = 'attendance';
        let db;

        const dbRequest = indexedDB.open(DB_NAME, 1);

        dbRequest.onerror = (event) => {
            log('IndexedDB error:', event.target.error);
        };

        dbRequest.onupgradeneeded = (event) => {
            db = event.target.result;
            if (!db.objectStoreNames.contains(STORE_NAME)) {
                const store = db.createObjectStore(STORE_NAME, { keyPath: 'id', autoIncrement: true });
                store.createIndex('timestamp', 'timestamp');
                store.createIndex('synced', 'synced');
            }
        };

        dbRequest.onsuccess = (event) => {
            db = event.target.result;
            log('IndexedDB initialized successfully');
            checkPendingAttendance();
        };

        // Save attendance record locally
        async function saveAttendanceLocally(qrData) {
            return new Promise((resolve, reject) => {
                try {
                    if (!db) {
                        log('Database not initialized');
                        reject(new Error('Database not initialized'));
                        return;
                    }
                    
                    const transaction = db.transaction([STORE_NAME], 'readwrite');
                    const store = transaction.objectStore(STORE_NAME);
                    
                    const record = {
                        qrData: qrData,
                        timestamp: new Date(),
                        synced: false
                    };
                    
                    log('Saving attendance record locally', record);
                    
                    const request = store.add(record);
                    
                    request.onsuccess = () => {
                        log('Record saved locally with ID', request.result);
                        checkPendingAttendance();
                        resolve();
                    };
                    
                    request.onerror = () => {
                        log('Error saving record locally', request.error);
                        reject(request.error);
                    };
                    
                    transaction.oncomplete = () => {
                        log('Transaction completed successfully');
                    };
                    
                    transaction.onerror = (event) => {
                        log('Transaction error', event.target.error);
                        reject(event.target.error);
                    };
                } catch (error) {
                    log('Exception saving record locally', error);
                    reject(error);
                }
            });
        }

        // Check and display pending attendance records
        async function checkPendingAttendance() {
            try {
                if (!db) {
                    log('Database not initialized');
                    return;
                }
                
                const transaction = db.transaction([STORE_NAME], 'readonly');
                const store = transaction.objectStore(STORE_NAME);
                const index = store.index('synced');
                
                // Use cursor instead of getAll with IDBKeyRange
                const request = index.openCursor();
                const pendingRecords = [];
                
                request.onsuccess = (event) => {
                    const cursor = event.target.result;
                    if (cursor) {
                        // Only collect records that are not synced
                        if (cursor.value.synced === false) {
                            pendingRecords.push(cursor.value);
                        }
                        cursor.continue();
                    } else {
                        // All records have been collected
                        log('Pending records found', pendingRecords.length);
                        
                        const pendingSync = document.getElementById('pendingSync');
                        const pendingList = document.getElementById('pendingList');
                        const pendingCount = document.getElementById('pendingCount');
                        const syncButton = document.getElementById('syncAll');
                        
                        pendingCount.textContent = pendingRecords.length;
                        
                        // Only show the pending section if there are pending records
                        pendingSync.style.display = pendingRecords.length > 0 ? 'block' : 'none';
                        
                        if (pendingRecords.length > 0) {
                            pendingList.innerHTML = '';
                            pendingRecords.forEach(record => {
                                const div = document.createElement('div');
                                div.className = 'pending-item';
                                div.innerHTML = `
                                    <div>
                                        <strong>${record.qrData.school_name}</strong>
                                        <br>
                                        <small>${new Date(record.timestamp).toLocaleString()}</small>
                                    </div>
                                    <div class="recording-actions">
                                        ${navigator.onLine ? `
                                            <button onclick="syncAttendance(${record.id})" class="btn btn-sm btn-primary">
                                                <i class="fas fa-sync"></i> Sync
                                            </button>
                                        ` : ''}
                                        <span class="sync-badge sync-pending">
                                            <i class="fas fa-clock"></i> Pending
                                        </span>
                                    </div>
                                `;
                                pendingList.appendChild(div);
                            });
                        }
                        
                        // Show/hide sync button based on pending records and network status
                        syncButton.style.display = (pendingRecords.length > 0 && navigator.onLine) ? 'inline-flex' : 'none';
                    }
                };
                
                request.onerror = (event) => {
                    log('Error checking pending attendance', event.target.error);
                };
            } catch (error) {
                log('Exception checking pending attendance', error);
            }
        }

        // Sync a single attendance record
        async function syncAttendance(recordId) {
            if (!navigator.onLine) {
                log('Sync attempted while offline - will retry when online');
                return;
            }

            try {
                if (!db) {
                    log('Database not initialized');
                    return;
                }
                
                const transaction = db.transaction([STORE_NAME], 'readonly');
                const store = transaction.objectStore(STORE_NAME);
                const request = store.get(recordId);

                request.onsuccess = async () => {
                    const record = request.result;
                    if (!record) {
                        log('Record not found', recordId);
                        return;
                    }
                    
                    try {
                        log('Syncing record', record);
                        
                        const response = await fetch('attendance.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            credentials: 'same-origin', // Include cookies for session
                            body: JSON.stringify({ qr_data: JSON.stringify(record.qrData) })
                        });

                        // First get the text response to check for any PHP errors
                        const responseText = await response.text();
                        log('Server response', responseText);
                        
                        // Try to parse as JSON, but handle potential PHP errors
                        let result;
                        try {
                            // If there's PHP output before the JSON, extract just the JSON part
                            const jsonStart = responseText.indexOf('{');
                            const jsonEnd = responseText.lastIndexOf('}') + 1;
                            
                            if (jsonStart >= 0 && jsonEnd > jsonStart) {
                                const jsonString = responseText.substring(jsonStart, jsonEnd);
                                result = JSON.parse(jsonString);
                                log('Parsed JSON response', result);
                            } else {
                                throw new Error("Invalid JSON response");
                            }
                        } catch (e) {
                            log('Failed to parse response', e);
                            throw new Error("Server returned invalid response");
                        }
                        
                        if (result.success) {
                            // Mark as synced
                            const updateTx = db.transaction([STORE_NAME], 'readwrite');
                            const updateStore = updateTx.objectStore(STORE_NAME);
                            record.synced = true;
                            await updateStore.put(record);
                            log('Record marked as synced', record.id);
                            
                            // Update UI
                            checkPendingAttendance();
                        } else {
                            log('Sync failed for record', { record, message: result.message });
                        }
                    } catch (error) {
                        log('Sync error', error);
                    }
                };
                
                request.onerror = (event) => {
                    log('Error getting record', event.target.error);
                };
            } catch (error) {
                log('Exception syncing attendance', error);
            }
        }

        // Sync all pending attendance records
        async function syncAllAttendance() {
            if (!navigator.onLine) {
                log('Sync attempted while offline - will retry when online');
                return;
            }

            try {
                if (!db) {
                    log('Database not initialized');
                    return;
                }
                
                const transaction = db.transaction([STORE_NAME], 'readonly');
                const store = transaction.objectStore(STORE_NAME);
                const index = store.index('synced');
                
                // Use cursor instead of getAll with IDBKeyRange
                const request = index.openCursor();
                const pendingRecords = [];
                
                request.onsuccess = async (event) => {
                    const cursor = event.target.result;
                    if (cursor) {
                        // Only collect records that are not synced
                        if (cursor.value.synced === false) {
                            pendingRecords.push(cursor.value);
                        }
                        cursor.continue();
                    } else {
                        // All records have been collected, now process them
                        log('Syncing all pending records', pendingRecords.length);
                        
                        let syncFailed = false;
                        
                        for (const record of pendingRecords) {
                            try {
                                log('Syncing record', record);
                                
                                const response = await fetch('attendance.php', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-Requested-With': 'XMLHttpRequest'
                                    },
                                    credentials: 'same-origin',
                                    body: JSON.stringify({ qr_data: JSON.stringify(record.qrData) })
                                });

                                // First get the text response to check for any PHP errors
                                const responseText = await response.text();
                                log('Server response', responseText);
                                
                                // Try to parse as JSON, but handle potential PHP errors
                                let result;
                                try {
                                    // If there's PHP output before the JSON, extract just the JSON part
                                    const jsonStart = responseText.indexOf('{');
                                    const jsonEnd = responseText.lastIndexOf('}') + 1;
                                    
                                    if (jsonStart >= 0 && jsonEnd > jsonStart) {
                                        const jsonString = responseText.substring(jsonStart, jsonEnd);
                                        result = JSON.parse(jsonString);
                                        log('Parsed JSON response', result);
                                    } else {
                                        throw new Error("Invalid JSON response");
                                    }
                                } catch (e) {
                                    log('Failed to parse response', e);
                                    throw new Error("Server returned invalid response");
                                }
                                
                                if (result.success) {
                                    // Mark as synced
                                    const updateTx = db.transaction([STORE_NAME], 'readwrite');
                                    const updateStore = updateTx.objectStore(STORE_NAME);
                                    record.synced = true;
                                    await updateStore.put(record);
                                    log('Record marked as synced', record.id);
                                } else {
                                    syncFailed = true;
                                    log('Sync failed for record', { record, message: result.message });
                                }
                            } catch (error) {
                                syncFailed = true;
                                log('Sync error', error);
                            }
                        }
                        
                        // Only update the UI if we have pending records to show
                        await checkPendingAttendance();
                        
                        // If any syncs failed, schedule a retry
                        if (syncFailed && navigator.onLine) {
                            log('Some records failed to sync - scheduling retry...');
                            setTimeout(syncAllAttendance, 30000); // Retry after 30 seconds
                        }
                    }
                };
                
                request.onerror = (event) => {
                    log('Error getting pending records', event.target.error);
                };
            } catch (error) {
                log('Exception syncing all attendance', error);
            }
        }

        // Network status monitoring and auto-sync
        function updateNetworkStatus() {
            const online = navigator.onLine;
            const statusElement = document.getElementById('networkStatus');
            
            // Remove all classes first
            statusElement.classList.remove('online', 'offline', 'checking');
            
            // Add the appropriate class
            if (online) {
                statusElement.classList.add('online');
                statusElement.innerHTML = `
                    <i class="fas fa-wifi"></i>
                    <span>Online</span>
                `;
                
                // Attempt to sync any pending records when we come online
                syncAllAttendance().catch(error => log('Error syncing when coming online', error));
            } else {
                statusElement.classList.add('offline');
                statusElement.innerHTML = `
                    <i class="fas fa-wifi-slash"></i>
                    <span>Offline</span>
                `;
            }
            
            log('Network status updated', online ? 'online' : 'offline');
        }

        // Auto-sync when back online
        window.addEventListener('online', updateNetworkStatus);
        window.addEventListener('offline', updateNetworkStatus);
        
        // Initial network check and auto-sync
        updateNetworkStatus();

        // QR Code Scanner using jsQR
        let video = document.getElementById('preview');
        let canvasElement = document.getElementById('canvas');
        let canvas = canvasElement.getContext('2d');
        let scanning = false;
        let isProcessing = false;
        let activeStream = null;
        
        // Camera selection and initialization
        function adaptivenessCheck() {
    // Check if the browser supports required features
    const report = {
        userAgent: navigator.userAgent,
        mediaDevices: !!navigator.mediaDevices,
        getUserMedia: !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia) || 
                      !!navigator.getUserMedia || 
                      !!navigator.webkitGetUserMedia || 
                      !!navigator.mozGetUserMedia,
        secure: location.protocol === 'https:' || location.hostname === 'localhost',
        indexedDB: !!window.indexedDB
    };
    
    log('Browser compatibility check', report);
    return report;
}

// Add a polyfill for older browsers
function setMediaDevicesPolyfill() {
    // Older browsers might not have mediaDevices at all, so we set it up
    if (navigator.mediaDevices === undefined) {
        navigator.mediaDevices = {};
    }
    
    // Some browsers partially implement mediaDevices. We need getUserMedia.
    if (navigator.mediaDevices.getUserMedia === undefined) {
        // Add the getUserMedia function
        navigator.mediaDevices.getUserMedia = function(constraints) {
            const getUserMedia = navigator.webkitGetUserMedia || navigator.mozGetUserMedia;
            
            if (!getUserMedia) {
                return Promise.reject(new Error("Browser does not support camera access. Please try using the latest Chrome or Firefox."));
            }
            
            // Use older getUserMedia functions with a Promise wrapper
            return new Promise(function(resolve, reject) {
                getUserMedia.call(navigator, constraints, resolve, reject);
            });
        }
    }
    
    log('Media devices polyfill applied');
}

// Camera selection and initialization
async function initializeCameras() {
    try {
        log('Initializing camera');
        document.getElementById('cameraPermissionError').style.display = 'none';
        
        // Set up polyfill for older browsers
        setMediaDevicesPolyfill();
        
        // Run compatibility check
        const compat = adaptivenessCheck();
        
        // Check if camera access is likely to fail
        if (!compat.getUserMedia) {
            throw new Error("Your browser doesn't support camera access. Please try using Chrome or Firefox.");
        }
        
        if (!compat.secure && location.hostname !== 'localhost') {
            throw new Error("Camera access requires a secure HTTPS connection.");
        }
        
        // Start with default camera
        startCamera();
    } catch (error) {
        log('Camera initialization error', error);
        document.getElementById('cameraPermissionError').style.display = 'block';
        document.getElementById('result').innerHTML = `
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> Camera error: ${error.message}
            </div>
        `;
    }
}

// Start the selected camera
async function startCamera() {
    try {
        // Stop any active stream
        if (activeStream) {
            activeStream.getTracks().forEach(track => {
                track.stop();
            });
            activeStream = null;
        }
        
        const cameraSelect = document.getElementById('cameraSelect');
        const facingMode = cameraSelect.value;
        
        log('Starting camera with facing mode', facingMode);
        
        // Try multiple approaches to get the camera working
        await tryStartCamera(facingMode);
        
    } catch (error) {
        log('All camera start attempts failed', error);
        document.getElementById('cameraPermissionError').style.display = 'block';
        document.getElementById('result').innerHTML = `
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> Failed to start camera: ${error.message}
            </div>
        `;
    }
}

// Try multiple approaches to start the camera
async function tryStartCamera(facingMode) {
    const approaches = [
        // Approach 1: Standard constraints with facingMode
        async () => {
            log('Trying standard constraints with facingMode');
            const constraints = {
                video: {
                    facingMode: facingMode
                },
                audio: false
            };
            return await navigator.mediaDevices.getUserMedia(constraints);
        },
        
        // Approach 2: Basic video constraints
        async () => {
            log('Trying basic video constraints');
            return await navigator.mediaDevices.getUserMedia({ 
                video: true,
                audio: false
            });
        },
        
        // Approach 3: Specific resolution constraints
        async () => {
            log('Trying with specific resolution');
            return await navigator.mediaDevices.getUserMedia({
                video: {
                    width: { ideal: 640 },
                    height: { ideal: 480 }
                },
                audio: false
            });
        },
        
        // Approach 4: Minimum resolution constraints
        async () => {
            log('Trying with minimum resolution');
            return await navigator.mediaDevices.getUserMedia({
                video: {
                    width: { min: 320 },
                    height: { min: 240 }
                },
                audio: false
            });
        },
        
        // Approach 5: Last resort - try with legacy getUserMedia directly
        async () => {
            log('Trying with legacy getUserMedia');
            const oldGetUserMedia = navigator.getUserMedia || 
                                   navigator.webkitGetUserMedia || 
                                   navigator.mozGetUserMedia;
            
            if (!oldGetUserMedia) {
                throw new Error("Browser does not support camera access");
            }
            
            return new Promise((resolve, reject) => {
                oldGetUserMedia.call(navigator, 
                    { video: true, audio: false },
                    resolve,
                    reject
                );
            });
        }
    ];
    
    let lastError = null;
    
    for (let i = 0; i < approaches.length; i++) {
        try {
            const stream = await approaches[i]();
            
            // Success! Set up the video
            activeStream = stream;
            video.srcObject = stream;
            video.setAttribute('playsinline', true); // Required for iOS
            video.muted = true; // Ensure muted to prevent audio feedback
            
            // Wait for video to be ready
            await new Promise((resolve) => {
                video.onloadedmetadata = () => {
                    resolve();
                };
            });
            
            await video.play();
            
            // Adjust canvas size to match video
            canvasElement.width = video.videoWidth;
            canvasElement.height = video.videoHeight;
            
            log('Camera started successfully', {
                approach: i + 1,
                width: video.videoWidth,
                height: video.videoHeight
            });
            
            document.getElementById('result').innerHTML = `
                <div class="alert alert-success">
                    <i class="fas fa-camera"></i> Camera activated successfully
                </div>
            `;
            
            // Start scanning
            scanning = true;
            requestAnimationFrame(tick);
            
            return; // Success, exit the function
        } catch (error) {
            lastError = error;
            log(`Approach ${i + 1} failed:`, error.message);
        }
    }
    
    // If we get here, all approaches failed
    throw new Error(`Could not start camera: ${lastError?.message || 'Your device might not support camera access'}`);
}

        // Process video frames to detect QR codes
        function tick() {
            if (!scanning) return;
            
            if (video.readyState === video.HAVE_ENOUGH_DATA) {
                // Draw video frame to canvas
                canvas.drawImage(video, 0, 0, canvasElement.width, canvasElement.height);
                const imageData = canvas.getImageData(0, 0, canvasElement.width, canvasElement.height);
                
                // Process with jsQR
                try {
                    const code = jsQR(imageData.data, imageData.width, imageData.height, {
                        inversionAttempts: "dontInvert",
                    });
                    
                    if (code && !isProcessing) {
                        // Found a QR code
                        handleQrCodeScan(code.data);
                    }
                } catch (error) {
                    log('jsQR processing error', error);
                }
            }
            
            // Continue scanning
            requestAnimationFrame(tick);
        }
        
        // Handle QR code scan
        async function handleQrCodeScan(decodedText) {
            if (isProcessing) return;
            isProcessing = true;

            try {
                // Add debug output to see what's being scanned
                log("Scanned content", decodedText);
                
                let qrData;
                try {
                    qrData = JSON.parse(decodedText);
                } catch (error) {
                    throw new Error('Invalid QR code format: Not valid JSON');
                }
                
                log("Parsed QR data", qrData);
                
                if (!qrData.school_name || !qrData.type || qrData.type !== 'attendance') {
                    throw new Error('Invalid QR code format');
                }

                if (navigator.onLine) {
                    try {
                        // Debug the request being sent
                        log("Sending request with data", { qr_data: decodedText });
                        
                        const response = await fetch('attendance.php', {
                            method: 'POST',
                            headers: { 
                                'Content-Type': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            credentials: 'same-origin', // Include cookies for session
                            body: JSON.stringify({ qr_data: decodedText })
                        });

                        // First get the text response to check for any PHP errors
                        const responseText = await response.text();
                        log("Response text", responseText);
                        
                        // Try to parse as JSON, but handle potential PHP errors
                        let data;
                        try {
                            // If there's PHP output before the JSON, extract just the JSON part
                            const jsonStart = responseText.indexOf('{');
                            const jsonEnd = responseText.lastIndexOf('}') + 1;
                            
                            if (jsonStart >= 0 && jsonEnd > jsonStart) {
                                const jsonString = responseText.substring(jsonStart, jsonEnd);
                                data = JSON.parse(jsonString);
                                log("Parsed JSON response", data);
                            } else {
                                throw new Error("Invalid JSON response");
                            }
                        } catch (e) {
                            log("Failed to parse response", e);
                            throw new Error("Server returned invalid response");
                        }
                        
                        if (data.success) {
                            document.getElementById('successSound').play().catch(e => log('Audio play failed', e));
                            
                            const isClockIn = data.action === 'clock_in';
                            const statusDisplay = document.getElementById('status-display');
                            const clockStatus = document.getElementById('clock-status');
                            const statusTime = document.getElementById('status-time');
                            
                            statusDisplay.style.display = 'block';
                            clockStatus.innerHTML = `
                                <i class="fas fa-${isClockIn ? 'sign-in-alt' : 'sign-out-alt'}"></i>
                                ${isClockIn ? 'Clocked In' : 'Clocked Out'}
                            `;
                            statusTime.textContent = data.time;
                            statusDisplay.className = 'status-container ' + (isClockIn ? 'bg-success' : 'bg-danger') + ' text-white';

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
                            throw new Error(data.message || "Server returned an error");
                        }
                    } catch (error) {
                        log("Error during online attendance", error);
                        
                        // Save locally when online request fails
                        await saveAttendanceLocally(qrData);
                        document.getElementById('result').innerHTML = `
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-circle"></i>
                                Failed to record attendance: ${error.message}. Will retry automatically when connection improves.
                            </div>
                        `;
                        // Try to sync immediately in case it was just a temporary error
                        setTimeout(syncAllAttendance, 5000); // Try again after 5 seconds
                    }
                } else {
                    await saveAttendanceLocally(qrData);
                    document.getElementById('result').innerHTML = `
                        <div class="alert alert-warning">
                            <i class="fas fa-wifi-slash"></i>
                            You are currently offline. Attendance will sync automatically when online.
                        </div>
                    `;
                }
            } catch (error) {
                log('QR scan error', error);
                document.getElementById('errorSound').play().catch(e => log('Audio play failed', e));
                document.getElementById('result').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> ${error.message}
                    </div>
                `;
            } finally {
                // Allow processing again after a short delay to prevent multiple scans
                setTimeout(() => {
                    isProcessing = false;
                }, 3000);
            }
        }
        
        // Camera selection change event
        document.getElementById('cameraSelect').addEventListener('change', startCamera);
        
        // Manual camera start button
        document.getElementById('startCamera').addEventListener('click', startCamera);
        
        // Retry camera button
        document.getElementById('retryCamera').addEventListener('click', initializeCameras);

        // Auto-sync when coming back online
        window.addEventListener('online', () => {
            log('Connection restored - attempting to sync...');
            syncAllAttendance();
        });

        // Add click handler for sync button
        document.getElementById('syncAll').addEventListener('click', syncAllAttendance);
        
        // Initialize cameras when page loads
        window.addEventListener('DOMContentLoaded', function() {
    // Run compatibility check at startup
    adaptivenessCheck();
    
    // Try to initialize the camera
    setTimeout(initializeCameras, 500);  // Short delay to ensure DOM is fully ready
});
    </script>
</body>
</html>
