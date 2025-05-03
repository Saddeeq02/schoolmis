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

// Get all classes for the dropdown
$classesStmt = $pdo->query("SELECT id, class_name FROM classes ORDER BY class_name");
$classes = $classesStmt->fetchAll();

// Handle class filter
$selectedClassId = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;

// Get exams based on filter
if ($selectedClassId > 0) {
    $exams = getExamsByClass($pdo, $selectedClassId);
} else {
    $exams = getAllExams($pdo);
}

// Handle success/error messages
$message = '';
$messageType = '';

if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $messageType = $_SESSION['message_type'] ?? 'success';
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exams List - School MIS</title>
    <link rel="stylesheet" href="../assets/clean-styles.css">
    <link rel="stylesheet" href="../assets/css/modern.css">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
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
                    <h1 class="h2">Exams Management</h1>
                    <div>
                        <a href="school_details.php" class="btn btn-info me-2">
                            <i class="fas fa-school"></i> School Details
                        </a>
                        <a href="add_exam.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add New Exam
                        </a>
                    </div>
                </div>

                <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType === 'error' ? 'danger' : 'success'; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <!-- Filter by class -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <form method="get" class="d-flex">
                            <select name="class_id" class="form-select me-2">
                                <option value="0">All Classes</option>
                                <?php foreach ($classes as $class): ?>
                                <option value="<?php echo $class['id']; ?>" <?php echo $selectedClassId == $class['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($class['class_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-secondary">Filter</button>
                        </form>
                    </div>
                </div>

                <!-- Exams table -->
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Title</th>
                                <th>Session</th>
                                <th>Term</th>
                                <th>Class</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($exams && count($exams) > 0): ?>
                                <?php foreach ($exams as $exam): ?>
                                <tr>
                                    <td><?php echo $exam['id']; ?></td>
                                    <td><?php echo htmlspecialchars($exam['title']); ?></td>
                                    <td><?php echo htmlspecialchars($exam['session']); ?></td>
                                    <td><?php echo htmlspecialchars($exam['term']); ?></td>
                                    <td><?php echo htmlspecialchars($exam['class_name'] ?? ''); ?></td>
                                    <td><?php echo date('Y-m-d', strtotime($exam['created_at'])); ?></td>
                                    <td>
                                        <a href="exam_components.php?id=<?php echo $exam['id']; ?>" class="btn btn-sm btn-info">
                                            <i class="fas fa-cogs"></i> Components
                                        </a>
                                        <a href="edit_exam.php?id=<?php echo $exam['id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <button type="button" class="btn btn-sm btn-danger" 
                                                onclick="confirmDelete(<?php echo $exam['id']; ?>, '<?php echo htmlspecialchars($exam['title']); ?>')">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center">No exams found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </main>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to delete the exam "<span id="examTitle"></span>"?
                    <p class="text-danger mt-2">This will also delete all components and scores associated with this exam.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form id="deleteForm" method="post" action="delete_exam.php">
                        <input type="hidden" name="exam_id" id="examIdInput">
                        <button type="submit" class="btn btn-danger">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmDelete(id, title) {
            document.getElementById('examTitle').textContent = title;
            document.getElementById('examIdInput').value = id;
            var deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            deleteModal.show();
        }
    </script>
</body>
</html>
