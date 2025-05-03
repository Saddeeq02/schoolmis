<?php
include '../includes/auth.php';
if ($_SESSION['role'] != 'teacher') {
    header("Location: ../index.php");
    exit();
}

include '../includes/db.php';

// Fetch classes and subjects for current teacher's school
$stmt = $pdo->prepare("SELECT * FROM classes WHERE school_id = (SELECT school_id FROM users WHERE id = ?)");
$stmt->execute([$_SESSION['user_id']]);
$classes = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM subjects WHERE school_id = (SELECT school_id FROM users WHERE id = ?)");
$stmt->execute([$_SESSION['user_id']]);
$subjects = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="manifest" href="../manifest.json">
    <meta name="theme-color" content="#4A90E2">
    <title>Record Audio - SchoolMIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/clean-styles.css" rel="stylesheet">
    <script src="../assets/scripts.js"></script>
    <style>
        :root {
            --primary-color: #4a90e2;
            --danger-color: #ff4444;
            --success-color: #28a745;
            --warning-color: #ffc107;
        }

        .recording-container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }

        .recording-status {
            text-align: center;
            margin: 20px 0;
            padding: 15px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            backdrop-filter: blur(5px);
        }

        .timer {
            font-size: 2.5rem;
            font-weight: bold;
            color: var(--primary-color);
            font-family: monospace;
            margin-bottom: 10px;
        }

        #recordingIndicator {
            color: var(--danger-color);
            font-weight: 500;
            animation: pulse 1.5s infinite;
        }

        .controls {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin: 20px 0;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-record {
            background: var(--danger-color);
            color: white;
        }

        .btn-stop {
            background: var(--primary-color);
            color: white;
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .recordings-list {
            margin-top: 30px;
        }

        .recording-item {
            background: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .recording-info {
            flex: 1;
        }

        .recording-actions {
            display: flex;
            gap: 10px;
        }

        .sync-status {
            font-size: 0.8em;
            padding: 4px 8px;
            border-radius: 12px;
            margin-left: 8px;
        }

        .sync-pending {
            background: var(--warning-color);
            color: black;
        }

        .sync-success {
            background: var(--success-color);
            color: white;
        }

        .network-status {
            position: fixed;
            top: 10px;
            right: 10px;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9em;
            z-index: 1000;
        }

        .online {
            background: var(--success-color);
            color: white;
        }

        .offline {
            background: var(--warning-color);
            color: black;
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }

        @media (max-width: 768px) {
            .recording-container {
                padding: 10px;
            }

            .controls {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }

            .recording-item {
                flex-direction: column;
                gap: 10px;
            }

            .recording-actions {
                width: 100%;
                justify-content: space-between;
            }
        }
    </style>
</head>
<body>
    <div class="recording-container">
        <div id="networkStatus" class="network-status">
            <i class="fas fa-wifi"></i> <span>Checking...</span>
        </div>

        <div class="navigation-buttons">
            <a href="dashboard.php" class="btn btn-link">
                <i class="fas fa-home"></i> Back to Dashboard
            </a>
        </div>

        <h1>Record Audio Lesson</h1>
        
        <form id="recordingForm">
            <div class="form-group">
                <label for="class_id">Class:</label>
                <select name="class_id" required>
                    <?php foreach ($classes as $class): ?>
                        <option value="<?= $class['id'] ?>"><?= htmlspecialchars($class['class_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="subject_id">Subject:</label>
                <select name="subject_id" required>
                    <?php foreach ($subjects as $subject): ?>
                        <option value="<?= $subject['id'] ?>"><?= htmlspecialchars($subject['subject_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="recording-status">
                <div id="recordingTimer" class="timer">00:00:00</div>
                <div id="recordingIndicator" style="display: none;">Recording in progress...</div>
            </div>
            
            <div class="controls">
                <button type="button" id="startRecord" class="btn btn-record">
                    <i class="fas fa-microphone"></i> Start Recording
                </button>
                <button type="button" id="stopRecord" class="btn btn-stop" disabled>
                    <i class="fas fa-stop"></i> Stop Recording
                </button>
                <button type="button" id="syncRecordings" class="btn" style="display: none;">
                    <i class="fas fa-sync"></i> Sync Recordings (<span id="pendingCount">0</span>)
                </button>
            </div>
        </form>

        <div id="audioPreviewContainer"></div>

        <div class="recordings-list">
            <h2>Recent Recordings</h2>
            <div id="localRecordings"></div>
        </div>
    </div>

    <script>
        let mediaRecorder;
        let audioChunks = [];
        let startTime;
        let timerInterval;
        let recordingNumber = 0;
        const DB_NAME = 'recordingsDB';
        const STORE_NAME = 'recordings';
        let db;

        // Initialize IndexedDB
        const request = indexedDB.open(DB_NAME, 1);

        request.onerror = (event) => {
            console.error('IndexedDB error:', event.target.error);
        };

        request.onupgradeneeded = (event) => {
            db = event.target.result;
            if (!db.objectStoreNames.contains(STORE_NAME)) {
                const store = db.createObjectStore(STORE_NAME, { keyPath: 'id', autoIncrement: true });
                store.createIndex('timestamp', 'timestamp');
                store.createIndex('synced', 'synced');
            }
        };

        request.onsuccess = (event) => {
            db = event.target.result;
            loadLocalRecordings();
            updateSyncButton();
            cleanupOldRecordings();
        };

        // Network status monitoring
        function updateNetworkStatus() {
            const status = navigator.onLine;
            const statusElement = document.getElementById('networkStatus');
            statusElement.className = 'network-status ' + (status ? 'online' : 'offline');
            statusElement.innerHTML = `<i class="fas fa-wifi"></i> ${status ? 'Online' : 'Offline'}`;
            document.getElementById('syncRecordings').style.display = status ? 'block' : 'none';
        }

        window.addEventListener('online', updateNetworkStatus);
        window.addEventListener('offline', updateNetworkStatus);
        updateNetworkStatus();

        // Timer function
        function updateTimer() {
            const now = new Date();
            const diff = now - startTime;
            const hours = Math.floor(diff / 3600000);
            const minutes = Math.floor((diff % 3600000) / 60000);
            const seconds = Math.floor((diff % 60000) / 1000);
            
            document.getElementById('recordingTimer').textContent = 
                `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        }

        // Initialize recording
        async function initializeRecording() {
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ 
                    audio: {
                        channelCount: 1,
                        sampleRate: 44100
                    }
                });
                mediaRecorder = new MediaRecorder(stream, {
                    mimeType: 'audio/webm',
                    audioBitsPerSecond: 128000
                });
                
                mediaRecorder.ondataavailable = (event) => {
                    if (event.data.size > 0) {
                        audioChunks.push(event.data);
                    }
                };

                document.getElementById('startRecord').disabled = false;
            } catch (error) {
                console.error('Error accessing microphone:', error);
                alert('Please ensure microphone access is granted and try again.');
            }
        }

        // Clean up old recordings (older than 30 days)
        async function cleanupOldRecordings() {
            const thirtyDaysAgo = new Date();
            thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);

            const transaction = db.transaction([STORE_NAME], 'readwrite');
            const store = transaction.objectStore(STORE_NAME);
            const index = store.index('timestamp');
            const range = IDBKeyRange.upperBound(thirtyDaysAgo);

            const request = index.openCursor(range);
            
            request.onsuccess = (event) => {
                const cursor = event.target.result;
                if (cursor) {
                    if (cursor.value.synced) {  // Only delete if synced to server
                        cursor.delete();
                    }
                    cursor.continue();
                }
            };
        }

        // Save recording to IndexedDB
        async function saveRecordingLocally(blob, metadata) {
            const transaction = db.transaction([STORE_NAME], 'readwrite');
            const store = transaction.objectStore(STORE_NAME);
            
            const recording = {
                blob: blob,
                metadata: metadata,
                timestamp: new Date(),
                synced: false
            };
            
            await store.add(recording);
            loadLocalRecordings();
            updateSyncButton();
        }

        // Load recordings from IndexedDB
        async function loadLocalRecordings() {
            const transaction = db.transaction([STORE_NAME], 'readonly');
            const store = transaction.objectStore(STORE_NAME);
            const request = store.getAll();

            request.onsuccess = () => {
                const recordings = request.result;
                displayLocalRecordings(recordings);
            };
        }

        // Display local recordings
        function displayLocalRecordings(recordings) {
            const container = document.getElementById('localRecordings');
            container.innerHTML = '';

            recordings.sort((a, b) => b.timestamp - a.timestamp).forEach(recording => {
                const div = document.createElement('div');
                div.className = 'recording-item';
                
                const url = URL.createObjectURL(recording.blob);
                const date = new Date(recording.timestamp).toLocaleString();
                
                div.innerHTML = `
                    <div class="recording-info">
                        <strong>${recording.metadata.subject_name}</strong> - ${recording.metadata.class_name}
                        <br>
                        <small>${date}</small>
                        <span class="sync-status ${recording.synced ? 'sync-success' : 'sync-pending'}">
                            ${recording.synced ? 'Synced' : 'Pending Sync'}
                        </span>
                    </div>
                    <div class="recording-actions">
                        <audio controls src="${url}"></audio>
                        ${!recording.synced ? `
                            <button onclick="syncRecording(${recording.id})" class="btn">
                                <i class="fas fa-cloud-upload-alt"></i>
                            </button>
                        ` : ''}
                    </div>
                `;
                
                container.appendChild(div);
            });
        }

        // Sync single recording
        async function syncRecording(id) {
            if (!navigator.onLine) {
                alert('You are offline. Please check your internet connection and try again.');
                return;
            }

            try {
                const transaction = db.transaction([STORE_NAME], 'readwrite');
                const store = transaction.objectStore(STORE_NAME);
                const recording = await new Promise((resolve, reject) => {
                    const request = store.get(id);
                    request.onsuccess = () => resolve(request.result);
                    request.onerror = () => reject(request.error);
                });

                if (recording && !recording.synced) {
                    const formData = new FormData();
                    formData.append('audio', new Blob([recording.blob], { type: 'audio/webm' }), 'recording.webm');
                    formData.append('class_id', recording.metadata.class_id);
                    formData.append('subject_id', recording.metadata.subject_id);

                    const response = await fetch('save_recordings.php', {
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();
                    console.log('Server response:', result);

                    if (result.status === 'success') {
                        // Start new transaction for update
                        const updateTransaction = db.transaction([STORE_NAME], 'readwrite');
                        const updateStore = updateTransaction.objectStore(STORE_NAME);
                        
                        recording.synced = true;
                        const updateRequest = updateStore.put(recording);
                        
                        await new Promise((resolve, reject) => {
                            updateRequest.onsuccess = () => resolve();
                            updateRequest.onerror = () => reject(updateRequest.error);
                            updateTransaction.oncomplete = () => {
                                loadLocalRecordings();
                                updateSyncButton();
                            };
                        });
                    } else {
                        throw new Error(result.message || 'Failed to sync recording');
                    }
                }
            } catch (error) {
                console.error('Sync error:', error);
                alert('Failed to sync recording. Please try again.');
            }
        }

        // Update sync button status
        async function updateSyncButton() {
            const transaction = db.transaction([STORE_NAME], 'readonly');
            const store = transaction.objectStore(STORE_NAME);
            const request = store.openCursor();
            let count = 0;

            request.onsuccess = (event) => {
                const cursor = event.target.result;
                if (cursor) {
                    if (cursor.value.synced === false) {
                        count++;
                    }
                    cursor.continue();
                } else {
                    const syncButton = document.getElementById('syncRecordings');
                    document.getElementById('pendingCount').textContent = count;
                    syncButton.style.display = count > 0 && navigator.onLine ? 'block' : 'none';
                }
            };
        }

        // Sync all pending recordings
        document.getElementById('syncRecordings').addEventListener('click', async () => {
            if (!navigator.onLine) {
                alert('You are offline. Please check your internet connection and try again.');
                return;
            }

            try {
                const transaction = db.transaction([STORE_NAME], 'readonly');
                const store = transaction.objectStore(STORE_NAME);
                const request = store.getAll();

                request.onsuccess = () => {
                    const recordings = request.result;
                    const pendingRecordings = recordings.filter(rec => !rec.synced);
                    pendingRecordings.forEach(recording => {
                        syncRecording(recording.id);
                    });
                };
            } catch (error) {
                console.error('Error syncing recordings:', error);
                alert('Failed to sync recordings. Please try again.');
            }
        });

        // Start Recording
        document.getElementById('startRecord').addEventListener('click', () => {
            audioChunks = [];
            startTime = new Date();
            timerInterval = setInterval(updateTimer, 1000);
            
            mediaRecorder.start(1000);
            document.getElementById('startRecord').disabled = true;
            document.getElementById('stopRecord').disabled = false;
            document.getElementById('recordingIndicator').style.display = 'block';
            document.getElementById('recordingTimer').style.display = 'block';
        });

        // Stop Recording
        document.getElementById('stopRecord').addEventListener('click', async () => {
            clearInterval(timerInterval);
            const finalTime = document.getElementById('recordingTimer').textContent;
            
            mediaRecorder.stop();
            document.getElementById('recordingIndicator').textContent = 'Processing recording...';
            
            await new Promise(resolve => {
                mediaRecorder.onstop = resolve;
            });

            const audioBlob = new Blob(audioChunks, { type: 'audio/webm' });
            const metadata = {
                class_id: document.querySelector('select[name="class_id"]').value,
                subject_id: document.querySelector('select[name="subject_id"]').value,
                class_name: document.querySelector('select[name="class_id"] option:checked').text,
                subject_name: document.querySelector('select[name="subject_id"] option:checked').text,
                duration: finalTime
            };

            await saveRecordingLocally(audioBlob, metadata);

            // Reset UI
            document.getElementById('recordingIndicator').style.display = 'none';
            document.getElementById('startRecord').disabled = false;
            document.getElementById('stopRecord').disabled = true;
            document.getElementById('recordingTimer').textContent = '00:00:00';
        });

        // Initialize recording when page loads
        initializeRecording();
    </script>
</body>
</html>
