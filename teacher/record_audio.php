<?php
include '../includes/auth.php';
if ($_SESSION['role'] != 'teacher') {
    header("Location: ../index.php");
    exit();
}

include '../includes/db.php';

// Fetch classes and subjects
$classes = $pdo->query("SELECT * FROM classes")->fetchAll();
$subjects = $pdo->query("SELECT * FROM subjects")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $class_id = $_POST['class_id'];
    $subject_id = $_POST['subject_id'];
    $start_time = date('Y-m-d H:i:s');
    $end_time = date('Y-m-d H:i:s'); // Placeholder, replace with actual end time
    $recording_path = $_POST['recording_path']; // Get the recording path from the form

    // Save recording details to the database
    $stmt = $pdo->prepare("INSERT INTO recordings (teacher_id, class_id, subject_id, recording_path, start_time, end_time) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$_SESSION['user_id'], $class_id, $subject_id, $recording_path, $start_time, $end_time]);

    header("Location: dashboard.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Record Audio</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body>
    <div class="container">
        <h1>Record Audio</h1>
        <form id="recordingForm">
            <label for="class_id">Class:</label>
            <select name="class_id" required>
                <?php foreach ($classes as $class): ?>
                    <option value="<?= $class['id'] ?>"><?= $class['class_name'] ?></option>
                <?php endforeach; ?>
            </select>

            <label for="subject_id">Subject:</label>
            <select name="subject_id" required>
                <?php foreach ($subjects as $subject): ?>
                    <option value="<?= $subject['id'] ?>"><?= $subject['subject_name'] ?></option>
                <?php endforeach; ?>
            </select>

            <div class="recording-status">
                <div id="recordingTimer" class="timer">00:00:00</div>
                <div id="recordingIndicator" style="display: none;">Recording in progress...</div>
            </div>
            
            <button type="button" id="startRecord">Start Recording</button>
            <button type="button" id="stopRecord" disabled>Stop & Save Recording</button>
        </form>
        <div id="audioPreviewContainer"></div>
    </div>

    <style>
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
            color: #fff;
            font-family: monospace;
            text-shadow: 0 2px 4px rgba(0,0,0,0.2);
            margin-bottom: 10px;
        }

        #recordingIndicator {
            color: #ff4444;
            font-weight: 500;
            animation: pulse 1.5s infinite;
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
    </style>

    <script>
        let mediaRecorder;
        let audioChunks = [];
        let startTime;
        let timerInterval;

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

        // Start Recording Button
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

        // Stop Recording Button
        document.getElementById('stopRecord').addEventListener('click', async () => {
            try {
                clearInterval(timerInterval);
                const finalTime = document.getElementById('recordingTimer').textContent;
                
                mediaRecorder.stop();
                document.getElementById('recordingIndicator').textContent = 'Saving recording...';
                
                await new Promise(resolve => {
                    mediaRecorder.onstop = resolve;
                });

                const audioBlob = new Blob(audioChunks, { type: 'audio/webm' });
                const formData = new FormData();

                formData.append('audio', audioBlob, 'recording.webm');
                formData.append('teacher_id', '<?= $_SESSION['user_id'] ?>');
                formData.append('class_id', document.querySelector('select[name="class_id"]').value);
                formData.append('subject_id', document.querySelector('select[name="subject_id"]').value);

                const response = await fetch('save_recordings.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();
                console.log('Server response:', data); // Debug response

                if (data.status === 'success') {
                    // Update UI with recording duration
                    const container = document.getElementById('audioPreviewContainer');
                    container.innerHTML = `
                        <div class="alert alert-success">
                            Recording saved successfully!<br>
                            Duration: ${finalTime}
                        </div>
                        <audio controls src="../${data.file}"></audio>
                    `;
                    
                    // Redirect after preview
                    setTimeout(() => {
                        window.location.href = 'dashboard.php';
                    }, 2000);
                } else {
                    throw new Error(data.message || 'Failed to save recording');
                }
                
                // Reset timer
                document.getElementById('recordingTimer').textContent = '00:00:00';
                
            } catch (error) {
                console.error('Error details:', error);
                document.getElementById('recordingIndicator').style.display = 'none';
                document.getElementById('startRecord').disabled = false;
                document.getElementById('stopRecord').disabled = true;
                
                document.getElementById('audioPreviewContainer').innerHTML = 
                    `<div class="alert alert-error">Error: ${error.message}</div>`;
            }
        });

        // Initialize recording when page loads
        initializeRecording();
    </script>
</body>
</html>
