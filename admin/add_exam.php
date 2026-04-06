<?php
// Add output buffering at the very start
ob_start();

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Ensure session is started properly
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is an admin
if (!isLoggedIn() || !isAdmin()) {
    ob_end_clean();
    header('Location: ../login.php');
    exit;
}

$schoolId = $_SESSION['school_id'] ?? 1;

// Get all classes for the current school
$classesStmt = $pdo->prepare("SELECT id, class_name FROM classes WHERE school_id = ? ORDER BY class_name");
$classesStmt->execute([$schoolId]);
$classes = $classesStmt->fetchAll(PDO::FETCH_ASSOC);

$errors = [];
$success = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate inputs
    $title = trim($_POST['title'] ?? '');
    $session = trim($_POST['session'] ?? '');
    $term = (int)($_POST['term'] ?? 0);
    $classId = (int)($_POST['class_id'] ?? 0);
    
    // Additional validation to ensure class belongs to current school
    $stmt = $pdo->prepare("SELECT id FROM classes WHERE id = ? AND school_id = ?");
    $stmt->execute([$classId, $schoolId]);
    $validClass = $stmt->fetchColumn();
    
    // Validation
    if (empty($title)) {
        $errors[] = 'Exam title is required';
    }
    
    if (empty($session)) {
        $errors[] = 'Academic session is required';
    }
    
    if ($term <= 0 || $term > 3) {
        $errors[] = 'Please select a valid term (1, 2, or 3)';
    }
    
    if (!$validClass) {
        $errors[] = 'Please select a valid class';
    }
    
    // If no errors, add the exam
    if (empty($errors)) {
        try {
            // Start transaction
            $pdo->beginTransaction();
            
            // Insert exam with school_id
            $stmt = $pdo->prepare("
                INSERT INTO exams (school_id, title, session, term, class_id, created_by) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$schoolId, $title, $session, $term, $classId, $_SESSION['user_id']]);
            $examId = $pdo->lastInsertId();
            
            // Default exam components
            $components = [
                ['1st CA', 15, 1],
                ['2nd CA', 15, 2],
                ['Assessment', 10, 3],
                ['Exam', 60, 4]
            ];
            
            $stmt = $pdo->prepare("
                INSERT INTO exam_components (exam_id, name, max_marks, is_enabled, display_order) 
                VALUES (?, ?, ?, 1, ?)
            ");
            
            foreach ($components as $component) {
                $stmt->execute([$examId, $component[0], $component[1], $component[2]]);
            }
            
            $pdo->commit();
            
            // Set session message
            $_SESSION['message'] = 'Exam created successfully';
            $_SESSION['message_type'] = 'success';
            
            // Clear output buffer and redirect
            ob_end_clean();
            header('Location: exam_components.php?id=' . $examId);
            exit();
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = 'Error creating exam: ' . $e->getMessage();
            // For debugging only - remove in production
            error_log('Database error: ' . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Exam - School MIS</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/clean-styles.css">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-home"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="teachers_list.php">
                                <i class="fas fa-chalkboard-teacher"></i> Teachers
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="students_list.php">
                                <i class="fas fa-user-graduate"></i> Students
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="create_class.php">
                                <i class="fas fa-school"></i> Classes
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="create_subject.php">
                                <i class="fas fa-book"></i> Subjects
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="exams_list.php">
                                <i class="fas fa-file-alt"></i> Exams
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="admin_qr_generator.php">
                                <i class="fas fa-qrcode"></i> QR Generator
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="view_recordings.php">
                                <i class="fas fa-video"></i> Recordings
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="report.php">
                                <i class="fas fa-chart-bar"></i> Reports
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../logout.php">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Add New Exam</h1>
                    <a href="exams_list.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Exams
                    </a>
                </div>

                <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <form method="post" action="">
                                    <div class="mb-3">
                                        <label for="title" class="form-label">Exam Title</label>
                                        <input type="text" class="form-control" id="title" name="title" 
                                               value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" required>
                                        <small class="text-muted">Example: First Term Examination</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="session" class="form-label">Academic Session</label>
                                        <input type="text" class="form-control" id="session" name="session" 
                                               value="<?php echo htmlspecialchars($_POST['session'] ?? ''); ?>" required>
                                        <small class="text-muted">Example: 2024/2025</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="term" class="form-label">Term</label>
                                        <select class="form-select" id="term" name="term" required>
                                            <option value="">Select Term</option>
                                            <option value="1" <?php echo (isset($_POST['term']) && $_POST['term'] == 1) ? 'selected' : ''; ?>>First Term</option>
                                            <option value="2" <?php echo (isset($_POST['term']) && $_POST['term'] == 2) ? 'selected' : ''; ?>>Second Term</option>
                                            <option value="3" <?php echo (isset($_POST['term']) && $_POST['term'] == 3) ? 'selected' : ''; ?>>Third Term</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="class_id" class="form-label">Class</label>
                                        <select class="form-select" id="class_id" name="class_id" required>
                                            <option value="">Select Class</option>
                                            <?php foreach ($classes as $class): ?>
                                            <option value="<?php echo $class['id']; ?>" <?php echo (isset($_POST['class_id']) && $_POST['class_id'] == $class['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($class['class_name']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-primary">Create Exam</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="../assets/clean-styles.css">

</body>
</html>
<?php ob_end_flush(); ?>