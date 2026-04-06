<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Check if user is logged in and is an admin
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php');
    exit;
}

// Check if exam ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['message'] = 'Exam ID is required';
    $_SESSION['message_type'] = 'error';
    header('Location: exams_list.php');
    exit;
}

$examId = (int)$_GET['id'];
$exam = getExamById($pdo, $examId);

// If exam not found, redirect back to list
if (!$exam) {
    $_SESSION['message'] = 'Exam not found';
    $_SESSION['message_type'] = 'error';
    header('Location: exams_list.php');
    exit;
}

// Get all classes for the dropdown
$classesStmt = $pdo->query("SELECT id, class_name FROM classes ORDER BY class_name");
$classes = $classesStmt->fetchAll();

$errors = [];
$success = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate inputs
    $title = trim($_POST['title'] ?? '');
    $session = trim($_POST['session'] ?? '');
    $term = (int)($_POST['term'] ?? 0);
    $classId = (int)($_POST['class_id'] ?? 0);
    
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
    
    if ($classId <= 0) {
        $errors[] = 'Please select a valid class';
    }
    
    // If no errors, update the exam
    if (empty($errors)) {
        $result = updateExam($pdo, $examId, $title, $session, $term, $classId);
        
        if ($result['success']) {
            $_SESSION['message'] = $result['message'];
            $_SESSION['message_type'] = 'success';
            header('Location: exams_list.php');
            exit;
        } else {
            $errors[] = $result['message'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Exam - School MIS</title>
    
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
                    <h1 class="h2">Edit Exam</h1>
                    <a href="exams_list.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Exams
                    </a>
                </div>

                <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                        <li><?php echo $error; ?></li>
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
                                               value="<?php echo htmlspecialchars($exam['title']); ?>" required>
                                        <small class="text-muted">Example: First Term Examination</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="session" class="form-label">Academic Session</label>
                                        <input type="text" class="form-control" id="session" name="session" 
                                               value="<?php echo htmlspecialchars($exam['session']); ?>" required>
                                        <small class="text-muted">Example: 2024/2025</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="term" class="form-label">Term</label>
                                        <select class="form-select" id="term" name="term" required>
                                            <option value="">Select Term</option>
                                            <option value="1" <?php echo ($exam['term'] == 1) ? 'selected' : ''; ?>>First Term</option>
                                            <option value="2" <?php echo ($exam['term'] == 2) ? 'selected' : ''; ?>>Second Term</option>
                                            <option value="3" <?php echo ($exam['term'] == 3) ? 'selected' : ''; ?>>Third Term</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="class_id" class="form-label">Class</label>
                                        <select class="form-select" id="class_id" name="class_id" required>
                                            <option value="">Select Class</option>
                                            <?php foreach ($classes as $class): ?>
                                            <option value="<?php echo $class['id']; ?>" <?php echo ($exam['class_id'] == $class['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($class['class_name']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-primary">Update Exam</button>
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
