<?php
session_start();
include '../includes/auth.php';
include '../includes/db.php';

if ($_SESSION['role'] != 'teacher') {
    header("Location: ../index.php");
    exit();
}

// Get user's name from database
$stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
$teacherName = $user['name'] ?? 'Teacher';
?>

<!DOCTYPE html>
<html>
<head>
    <title>Teacher Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/styles.css">
</head>
<body>
    <div class="container">
        <h2 class="text-white mb-4">Teacher Dashboard</h2>
        
        <div class="dashboard-grid">
            <a href="attendance.php" class="card">
                <i class="fas fa-clipboard-check"></i>
                <h3>Attendance</h3>
                <p>Mark and view attendance</p>
            </a>
            
            <a href="record_audio.php" class="card">
                <i class="fas fa-microphone-alt"></i>
                <h3>Record Audio</h3>
                <p>Record your class sessions</p>
            </a>
            
            <a href="view_recordings.php" class="card">
                <i class="fas fa-headphones"></i>
                <h3>View Recordings</h3>
                <p>Access your recorded sessions</p>
            </a>
            
            <a href="exams_list.php" class="card">
                <i class="fas fa-graduation-cap"></i>
                <h3>Exams</h3>
                <p>Enter and manage student scores</p>
            </a>
            
            <a href="print_results.php" class="card">
                <i class="fas fa-print"></i>
                <h3>Print Results</h3>
                <p>Generate and print student report cards</p>
            </a>
        </div>

        <!-- <div class="mt-4">
            <div class="card">
                <h3>Recent Activities</h3>
                <div class="table-responsive">
                    <table class="recordings-table mt-3">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Activity</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (isset($recent_activities)): ?>
                                <?php foreach ($recent_activities as $activity): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($activity['date']) ?></td>
                                        <td><?= htmlspecialchars($activity['description']) ?></td>
                                        <td>
                                            <span class="badge bg-<?= $activity['status'] == 'completed' ? 'success' : 'warning' ?>">
                                                <?= ucfirst(htmlspecialchars($activity['status'])) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div> -->
        
        <div class="mt-4 text-center">
            <a href="../login.php" class="btn">Logout</a>
        </div>
    </div>
</body>
</html>
