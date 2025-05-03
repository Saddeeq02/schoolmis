<?php
include '../includes/auth.php';
include '../includes/db.php';

try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception("No user_id in session");
    }

    $teacher_id = $_SESSION['user_id'];

    $query = "SELECT r.id, r.start_time, r.recording_path, s.subject_name, c.class_name 
              FROM recordings r
              LEFT JOIN subjects s ON r.subject_id = s.id
              LEFT JOIN classes c ON r.class_id = c.id
              WHERE r.teacher_id = ?
              ORDER BY r.start_time DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$teacher_id]);
    $results = $stmt->fetchAll();

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>View Recordings</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/clean-styles.css">
</head>
<body>
    <div class="container">
        <h2 class="text-dark mb-4">Class Recordings</h2>
        
        <div class="filters">
            <div class="filter-group">
                <div class="form-group">
                    <label for="dateFilter">Filter by Date</label>
                    <input type="date" id="dateFilter" class="form-control">
                </div>
                <div class="form-group">
                    <label for="subjectFilter">Filter by Subject</label>
                    <select id="subjectFilter" class="form-control">
                        <option value="">All Subjects</option>
                        <?php foreach ($results as $row): ?>
                            <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['subject_name'] ?? 'N/A') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table class="recordings-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Subject</th>
                            <th>Duration</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="recordingsList">
                        <?php foreach ($results as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars(date('Y-m-d', strtotime($row['start_time']))) ?></td>
                                <td><?= htmlspecialchars($row['subject_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars('N/A') ?></td>
                                <td>
                                    <?php 
                                    $cleanPath = preg_replace('/^recordings\//', '', $row['recording_path']);
                                    $audioPath = '../recordings/' . $cleanPath;
                                    $fullPath = dirname(__DIR__) . '/recordings/' . $cleanPath;
                                    ?>
                                    <?php if (file_exists($fullPath)): ?>
                                        <button class="btn" onclick="playRecording('<?= htmlspecialchars($audioPath) ?>')">
                                            <i class="fas fa-play"></i>
                                        </button>
                                        <a href="<?= htmlspecialchars($audioPath) ?>" download class="btn">
                                            <i class="fas fa-download"></i>
                                        </a>
                                        <!-- <?php if ($_SESSION['role'] === 'admin' || $row['teacher_id'] === $_SESSION['user_id']): ?>
                                            <button class="btn" onclick="deleteRecording(<?= $row['id'] ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?> -->
                                    <?php else: ?>
                                        File not found
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Audio Player Modal -->
        <div id="audioPlayerModal" class="modal" style="display: none;">
            <div class="modal-content card">
                <h3>Playing Recording</h3>
                <audio id="audioPlayer" controls class="w-100 mt-3"></audio>
                <button class="btn mt-3" onclick="closeAudioPlayer()">Close</button>
            </div>
        </div>
        
        <div class="mt-4 text-center">
            <a href="dashboard.php" class="btn">Back to Dashboard</a>
        </div>
    </div>

    <script>
        function playRecording(path) {
            const modal = document.getElementById('audioPlayerModal');
            const player = document.getElementById('audioPlayer');
            player.src = path;
            modal.style.display = 'flex';
            player.play();
        }

        function closeAudioPlayer() {
            const modal = document.getElementById('audioPlayerModal');
            const player = document.getElementById('audioPlayer');
            player.pause();
            modal.style.display = 'none';
        }

        // Filter functionality
        document.getElementById('dateFilter').addEventListener('change', filterRecordings);
        document.getElementById('subjectFilter').addEventListener('change', filterRecordings);

        function filterRecordings() {
            const dateFilter = document.getElementById('dateFilter').value;
            const subjectFilter = document.getElementById('subjectFilter').value;
            const rows = document.querySelectorAll('#recordingsList tr');

            rows.forEach(row => {
                const date = row.children[0].textContent;
                const subject = row.children[1].textContent;
                const dateMatch = !dateFilter || date.includes(dateFilter);
                const subjectMatch = !subjectFilter || subject.includes(subjectFilter);
                row.style.display = dateMatch && subjectMatch ? '' : 'none';
            });
        }

        // Handle modal close on outside click
        window.onclick = function(event) {
            const modal = document.getElementById('audioPlayerModal');
            if (event.target === modal) {
                closeAudioPlayer();
            }
        }
    </script>
</body>
</html>
