// teacher/dashboard.php - Add school filtering
<?php
session_start();
include '../includes/auth.php';
include '../includes/db.php';

if ($_SESSION['role'] != 'teacher') {
    header("Location: ../index.php");
    exit();
}

$teacherId = $_SESSION['user_id'];
$schoolId = $_SESSION['school_id'] ?? 1;

// Get user's name from database
$stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
$stmt->execute([$teacherId]);
$user = $stmt->fetch();
$teacherName = $user['name'] ?? 'Teacher';

// Get school details
$stmt = $pdo->prepare("SELECT * FROM schools WHERE id = ?");
$stmt->execute([$schoolId]);
$school = $stmt->fetch();

// Get assigned classes for this teacher in this school
$stmt = $pdo->prepare("
    SELECT DISTINCT c.id, c.class_name
    FROM classes c
    JOIN teacher_subjects ts ON c.id = ts.class_id
    WHERE ts.teacher_id = ? AND c.school_id = ?
    ORDER BY c.class_name
");
$stmt->execute([$teacherId, $schoolId]);
$assignedClasses = $stmt->fetchAll();

// Get assigned subjects for this teacher in this school
$stmt = $pdo->prepare("
    SELECT DISTINCT s.id, s.subject_name
    FROM subjects s
    JOIN teacher_subjects ts ON s.id = ts.subject_id
    JOIN classes c ON ts.class_id = c.id
    WHERE ts.teacher_id = ? AND c.school_id = ?
    ORDER BY s.subject_name
");
$stmt->execute([$teacherId, $schoolId]);
$assignedSubjects = $stmt->fetchAll();
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
        
        <?php if ($school): ?>
        <div class="card mb-4">
            <div class="d-flex align-items-center p-3">
                <?php if (!empty($school['logo_path'])): ?>
                    <img src="<?php echo '../' . htmlspecialchars($school['logo_path']); ?>" alt="School Logo" style="height: 50px; margin-right: 15px;">
                <?php endif; ?>
                <div>
                    <h3 class="mb-0"><?php echo htmlspecialchars($school['name']); ?></h3>
                    <p class="mb-0"><?php echo htmlspecialchars($school['address']); ?></p>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
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
        </div>

        <div class="mt-4">
            <div class="card">
                <h3>Your Classes</h3>
                <div class="table-responsive">
                    <table class="recordings-table mt-3">
                        <thead>
                            <tr>
                                <th>Class</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($assignedClasses)): ?>
                                <?php foreach ($assignedClasses as $class): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($class['class_name']) ?></td>
                                        <td>
                                            <a href="class_students.php?class_id=<?= $class['id'] ?>" class="btn">
                                                <i class="fas fa-users"></i> View Students
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="2">No classes assigned to you in this school.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="mt-4">
            <div class="card">
                <h3>Your Subjects</h3>
                <div class="table-responsive">
                    <table class="recordings-table mt-3">
                        <thead>
                            <tr>
                                <th>Subject</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($assignedSubjects)): ?>
                                <?php foreach ($assignedSubjects as $subject): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($subject['subject_name']) ?></td>
                                        <td>
                                            <a href="subject_exams.php?subject_id=<?= $subject['id'] ?>" class="btn">
                                                <i class="fas fa-file-alt"></i> View Exams
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="2">No subjects assigned to you in this school.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="mt-4 text-center">
            <a href="../login.php" class="btn">Logout</a>
        </div>
    </div>
</body>
</html>