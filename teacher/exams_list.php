<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';

// Check if user is logged in and is a teacher
if (!isLoggedIn() || !isTeacher()) {
    header('Location: ../login.php');
    exit;
}

$teacherId = $_SESSION['user_id'];
$schoolId = $_SESSION['school_id'] ?? 1;

// Pagination settings
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

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

// Get total count for pagination
$countQuery = str_replace("SELECT DISTINCT e.*, c.class_name", "SELECT COUNT(DISTINCT e.id) as total", $query);
$countStmt = $pdo->prepare($countQuery);
$countStmt->execute($params);
$totalRecords = $countStmt->fetchColumn();
$totalPages = ceil($totalRecords / $perPage);

// Add pagination to the main query
$query .= " LIMIT :offset, :per_page";
$params[':offset'] = $offset;
$params[':per_page'] = $perPage;

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
            <div class="card stat-card">
                <div class="d-flex align-items-center">
                    <i class="fas fa-file-alt text-primary fa-2x"></i>
                    <div class="ml-3">
                        <h3 class="mb-0"><?= count($exams) ?></h3>
                        <p class="stats-label mb-0">Active Exams</p>
                    </div>
                </div>
            </div>

            <div class="card stat-card">
                <div class="d-flex align-items-center">
                    <i class="fas fa-users text-success" style="font-size: 2rem;"></i>
                    <div class="ml-3">
                        <h3 class="mb-0"><?= $stats['total_students'] ?></h3>
                        <p class="stats-label mb-0">Total Students</p>
                    </div>
                </div>
            </div>

            <div class="card stat-card">
                <div class="d-flex align-items-center">
                    <i class="fas fa-book text-warning" style="font-size: 2rem;"></i>
                    <div class="ml-3">
                        <h3 class="mb-0"><?= $stats['total_subjects'] ?></h3>
                        <p class="stats-label mb-0">Subjects Taught</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-4">
            <form method="get" action="" class="filters-form">
                <div class="filters-grid">
                    <div class="form-group">
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

                    <div class="form-group">
                        <label for="term">Term:</label>
                        <select name="term" id="term" class="form-select" onchange="this.form.submit()">
                            <option value="">All Terms</option>
                            <option value="1" <?= $term == '1' ? 'selected' : '' ?>>First Term</option>
                            <option value="2" <?= $term == '2' ? 'selected' : '' ?>>Second Term</option>
                            <option value="3" <?= $term == '3' ? 'selected' : '' ?>>Third Term</option>
                        </select>
                    </div>

                    <div class="form-group">
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

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="pagination-container mt-4">
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?page=1<?= !empty($session) ? '&session='.$session : '' ?><?= !empty($term) ? '&term='.$term : '' ?><?= !empty($classId) ? '&class_id='.$classId : '' ?>" class="pagination-btn">
                                    <i class="fas fa-angle-double-left"></i>
                                </a>
                                <a href="?page=<?= $page-1 ?><?= !empty($session) ? '&session='.$session : '' ?><?= !empty($term) ? '&term='.$term : '' ?><?= !empty($classId) ? '&class_id='.$classId : '' ?>" class="pagination-btn">
                                    <i class="fas fa-angle-left"></i>
                                </a>
                            <?php endif; ?>

                            <?php
                            $start = max(1, $page - 2);
                            $end = min($totalPages, $page + 2);
                            
                            if ($start > 1) {
                                echo '<span class="pagination-ellipsis">...</span>';
                            }
                            
                            for ($i = $start; $i <= $end; $i++): ?>
                                <a href="?page=<?= $i ?><?= !empty($session) ? '&session='.$session : '' ?><?= !empty($term) ? '&term='.$term : '' ?><?= !empty($classId) ? '&class_id='.$classId : '' ?>" 
                                   class="pagination-btn <?= $i === $page ? 'active' : '' ?>">
                                    <?= $i ?>
                                </a>
                            <?php endfor;

                            if ($end < $totalPages) {
                                echo '<span class="pagination-ellipsis">...</span>';
                            }
                            ?>

                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?= $page+1 ?><?= !empty($session) ? '&session='.$session : '' ?><?= !empty($term) ? '&term='.$term : '' ?><?= !empty($classId) ? '&class_id='.$classId : '' ?>" class="pagination-btn">
                                    <i class="fas fa-angle-right"></i>
                                </a>
                                <a href="?page=<?= $totalPages ?><?= !empty($session) ? '&session='.$session : '' ?><?= !empty($term) ? '&term='.$term : '' ?><?= !empty($classId) ? '&class_id='.$classId : '' ?>" class="pagination-btn">
                                    <i class="fas fa-angle-double-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                        <div class="pagination-info">
                            Page <?= $page ?> of <?= $totalPages ?> (<?= $totalRecords ?> total records)
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="card">
                <p class="text-center mb-0">No exams found matching your criteria.</p>
            </div>
        <?php endif; ?>
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
    <style>
        /* Mobile-first responsive styles */
        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }

            .form-group {
                margin-bottom: 1rem;
            }

            table {
                border: 0;
            }

            table thead {
                display: none;
            }

            table tr {
                margin-bottom: 1rem;
                display: block;
                border: 1px solid #ddd;
                border-radius: 4px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            }

            table td {
                display: block;
                text-align: right;
                padding: 0.75rem;
                position: relative;
                border-bottom: 1px solid #eee;
            }

            table td:last-child {
                border-bottom: 0;
            }

            table td::before {
                content: attr(data-label);
                float: left;
                font-weight: bold;
            }

            .d-flex.gap-2 {
                justify-content: flex-end;
            }

            .pagination-container {
                flex-direction: column;
                align-items: center;
                gap: 1rem;
            }

            .pagination {
                flex-wrap: wrap;
                justify-content: center;
                gap: 0.5rem;
            }

            .pagination-btn {
                min-width: 40px;
                height: 40px;
                display: flex;
                align-items: center;
                justify-content: center;
            }
        }

        /* Pagination styles */
        .pagination-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            border-top: 1px solid #eee;
        }

        .pagination {
            display: flex;
            gap: 0.25rem;
            align-items: center;
        }

        .pagination-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 35px;
            height: 35px;
            padding: 0.25rem 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-decoration: none;
            color: #333;
            transition: all 0.2s;
        }

        .pagination-btn:hover {
            background-color: #f8f9fa;
            border-color: #ddd;
            text-decoration: none;
        }

        .pagination-btn.active {
            background-color: #007bff;
            border-color: #007bff;
            color: white;
        }

        .pagination-ellipsis {
            padding: 0 0.5rem;
        }

        .pagination-info {
            color: #666;
            font-size: 0.9rem;
        }

        /* Additional responsive improvements */
        .card {
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .gap-2 {
            gap: 0.5rem !important;
        }

        .gap-3 {
            gap: 1rem !important;
        }

        .filters-form {
            width: 100%;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            width: 100%;
        }

        .form-group {
            margin: 0;
        }

        .form-select {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            background-color: white;
        }

        .stat-card {
            background: white;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .stats-label {
            color: #666;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .fa-2x {
            font-size: 2em;
            margin-right: 1rem;
        }

        @media (max-width: 768px) {
            .filters-grid {
                grid-template-columns: 1fr;
            }

            .form-group {
                margin-bottom: 1rem;
            }

            .form-group:last-child {
                margin-bottom: 0;
            }

            .stat-card {
                padding: 1rem;
            }

            .stats-label {
                font-size: 0.85rem;
            }
        }
    </style>
</body>
</html>
