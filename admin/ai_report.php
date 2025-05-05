<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Check if user is logged in and is an admin
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php');
    exit;
}

// Get school ID from session
$schoolId = $_SESSION['school_id'] ?? 1;

// Fetch teachers and their metrics
$query = "
    SELECT 
        u.id AS teacher_id,
        u.name AS teacher_name,
        COALESCE((
            SELECT COUNT(*) * 100.0 / NULLIF(COUNT(DISTINCT DATE(created_at)), 0)
            FROM attendance 
            WHERE user_id = u.id 
            AND WEEK(created_at) = WEEK(CURDATE())
            AND school_id = :school_id
        ), 0) AS attendance_percentage,
        COALESCE((
            SELECT COUNT(DISTINCT r.id) * 100.0 / NULLIF(COUNT(DISTINCT ts.id), 0)
            FROM recordings r
            WHERE r.teacher_id = u.id 
            AND WEEK(r.created_at) = WEEK(CURDATE())
        ), 0) AS recordings_percentage
    FROM users u
    LEFT JOIN teacher_subjects ts ON u.id = ts.teacher_id AND ts.school_id = :school_id
    WHERE u.role = 'teacher' AND u.school_id = :school_id
    GROUP BY u.id, u.name
    ORDER BY u.name";

$stmt = $pdo->prepare($query);
$stmt->execute(['school_id' => $schoolId]);
$teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Teacher Performance Analytics</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/clean-styles.css">
</head>
<body>
    <div class="container mt-4">
        <h1 class="mb-4">Teacher Performance Analytics (This Week)</h1>
        
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Teacher Name</th>
                                <th>Attendance (%)</th>
                                <th>Class Recordings (%)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($teachers as $teacher): ?>
                            <tr>
                                <td><?= htmlspecialchars($teacher['teacher_name']) ?></td>
                                <td><?= number_format($teacher['attendance_percentage'], 2) ?>%</td>
                                <td><?= number_format($teacher['recordings_percentage'], 2) ?>%</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>