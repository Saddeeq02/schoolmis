<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/db.php';

// Check if user is logged in and is a teacher
if (!isLoggedIn() || !isTeacher()) {
    header('Location: ../login.php');
    exit;
}

$teacherId = $_SESSION['user_id'];
$schoolId = $_SESSION['school_id'] ?? 1;

// Get filter values
$session = $_GET['session'] ?? '';
$term = $_GET['term'] ?? '';
$classId = $_GET['class_id'] ?? '';

// Get available sessions
$sessionQuery = $pdo->query("SELECT DISTINCT session FROM exams ORDER BY session DESC");
$sessions = $sessionQuery->fetchAll(PDO::FETCH_COLUMN);

// Get classes taught by this teacher
$classStmt = $pdo->prepare("
    SELECT DISTINCT c.id, c.class_name 
    FROM classes c 
    INNER JOIN teacher_subjects ts ON c.id = ts.class_id 
    WHERE ts.teacher_id = ? AND c.school_id = ?
    ORDER BY c.class_name
");
$classStmt->execute([$teacherId, $schoolId]);
$classes = $classStmt->fetchAll();

// Build the query with filters
$query = "
    SELECT DISTINCT e.*, c.class_name
    FROM exams e
    INNER JOIN classes c ON e.class_id = c.id
    INNER JOIN teacher_subjects ts ON c.id = ts.class_id
    WHERE ts.teacher_id = :teacher_id AND c.school_id = :school_id
";

$params = [':teacher_id' => $teacherId, ':school_id' => $schoolId];

if (!empty($session)) {
    $query .= " AND e.session = :session";
    $params[':session'] = $session;
}
if (!empty($term)) {
    $query .= " AND e.term = :term";
    $params[':term'] = $term;
}
if (!empty($classId)) {
    $query .= " AND e.class_id = :class_id";
    $params[':class_id'] = $classId;
}

$query .= " ORDER BY e.session DESC, e.term DESC, e.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$exams = $stmt->fetchAll();

// Count total students and subjects
$statsQuery = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT s.id) as total_students,
        COUNT(DISTINCT ts.subject_id) as total_subjects
    FROM students s
    INNER JOIN classes c ON s.class_id = c.id
    INNER JOIN teacher_subjects ts ON c.id = ts.class_id
    WHERE ts.teacher_id = ? AND c.school_id = ?
");
$statsQuery->execute([$teacherId, $schoolId]);
$stats = $statsQuery->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exams List - Teacher Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/clean-styles.css">
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="card mb-3">
            <div class="d-flex justify-content-between align-items-center">
                <h1 class="mb-0">Exams List</h1>
                <a href="dashboard.php" class="btn btn-sm">
                    <i class="fas fa-th-large"></i>
                    <span class="d-none d-md-inline ml-2">Dashboard</span>
                </a>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="dashboard-grid mb-4">
            <div class="card">
                <div class="d-flex align-items-center">
                    <i class="fas fa-file-alt text-primary" style="font-size: 2rem;"></i>
                    <div class="ml-3">
                        <h3 class="mb-0"><?= count($exams) ?></h3>
                        <p class="text-light mb-0">Active Exams</p>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="d-flex align-items-center">
                    <i class="fas fa-users text-success" style="font-size: 2rem;"></i>
                    <div class="ml-3">
                        <h3 class="mb-0"><?= $stats['total_students'] ?></h3>
                        <p class="text-light mb-0">Total Students</p>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="d-flex align-items-center">
                    <i class="fas fa-book text-warning" style="font-size: 2rem;"></i>
                    <div class="ml-3">
                        <h3 class="mb-0"><?= $stats['total_subjects'] ?></h3>
                        <p class="text-light mb-0">Subjects Taught</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-4">
            <form method="get" action="" class="d-flex flex-wrap gap-3">
                <div class="form-group mb-0" style="flex: 1; min-width: 200px;">
                    <label for="session">Session:</label>
                    <select name="session" id="session" class="form-select" onchange="this.form.submit()">
                        <option value="">All Sessions</option>
                        <?php foreach ($sessions as $s): ?>
                            <option value="<?= $s ?>" <?= $session == $s ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group mb-0" style="flex: 1; min-width: 200px;">
                    <label for="term">Term:</label>
                    <select name="term" id="term" class="form-select" onchange="this.form.submit()">
                        <option value="">All Terms</option>
                        <option value="1" <?= $term == '1' ? 'selected' : '' ?>>First Term</option>
                        <option value="2" <?= $term == '2' ? 'selected' : '' ?>>Second Term</option>
                        <option value="3" <?= $term == '3' ? 'selected' : '' ?>>Third Term</option>
                    </select>
                </div>

                <div class="form-group mb-0" style="flex: 1; min-width: 200px;">
                    <label for="class_id">Class:</label>
                    <select name="class_id" id="class_id" class="form-select" onchange="this.form.submit()">
                        <option value="">All Classes</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?= $class['id'] ?>" <?= $classId == $class['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($class['class_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>

        <!-- Exams List -->
        <?php if (count($exams) > 0): ?>
            <div class="card">
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Class</th>
                                <th>Session</th>
                                <th>Term</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($exams as $exam): ?>
                                <tr>
                                    <td data-label="Title">
                                        <?= htmlspecialchars($exam['title']) ?>
                                    </td>
                                    <td data-label="Class">
                                        <?= htmlspecialchars($exam['class_name']) ?>
                                    </td>
                                    <td data-label="Session">
                                        <?= htmlspecialchars($exam['session']) ?>
                                    </td>
                                    <td data-label="Term">
                                        <?php
                                            switch($exam['term']) {
                                                case 1: echo "First Term"; break;
                                                case 2: echo "Second Term"; break;
                                                case 3: echo "Third Term"; break;
                                                default: echo "Unknown";
                                            }
                                        ?>
                                    </td>
                                    <td data-label="Actions">
                                        <div class="d-flex gap-2 flex-wrap">
                                            <a href="enter_scores.php?exam_id=<?= $exam['id'] ?>&class_id=<?= $exam['class_id'] ?>" 
                                               class="btn btn-sm">
                                                <i class="fas fa-edit"></i>
                                                <span class="d-none d-md-inline ml-1">Enter Scores</span>
                                            </a>
                                            <a href="view_scores.php?exam_id=<?= $exam['id'] ?>&class_id=<?= $exam['class_id'] ?>" 
                                               class="btn btn-sm">
                                                <i class="fas fa-eye"></i>
                                                <span class="d-none d-md-inline ml-1">View Scores</span>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <div class="card">
                <p class="text-center mb-0">No exams found matching your criteria.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
