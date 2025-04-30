// admin/dashboard.php - Add school selector
<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../components/school_selector.php';

// Check if user is logged in and is an admin
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php');
    exit;
}

// Handle school selection
if (isset($_GET['school_id'])) {
    $_SESSION['school_id'] = (int)$_GET['school_id'];
}

$schoolId = $_SESSION['school_id'] ?? 1;

// Get school details
$stmt = $pdo->prepare("SELECT * FROM schools WHERE id = :id");
$stmt->bindParam(':id', $schoolId, PDO::PARAM_INT);
$stmt->execute();
$school = $stmt->fetch();

// Get counts for the selected school
$studentCount = 0;
$classCount = 0;
$teacherCount = 0;
$examCount = 0;

try {
    // Count students
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE school_id = :school_id");
    $stmt->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
    $stmt->execute();
    $studentCount = $stmt->fetchColumn();
    
    // Count classes
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM classes WHERE school_id = :school_id");
    $stmt->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
    $stmt->execute();
    $classCount = $stmt->fetchColumn();
    
    // Count teachers (users with role 'teacher' and assigned to this school)
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT u.id) 
        FROM users u
        JOIN teacher_subjects ts ON u.id = ts.teacher_id
        JOIN classes c ON ts.class_id = c.id
        WHERE c.school_id = :school_id AND u.role = 'teacher'
    ");
    $stmt->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
    $stmt->execute();
    $teacherCount = $stmt->fetchColumn();
    
    // Count exams
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM exams WHERE school_id = :school_id");
    $stmt->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
    $stmt->execute();
    $examCount = $stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Error fetching dashboard data: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - School MIS</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
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
                            <a class="nav-link active" href="dashboard.php">
                                <i class="fas fa-home"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="schools.php">
                                <i class="fas fa-school"></i> Schools
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
                            <a class="nav-link" href="exams_list.php">
                                <i class="fas fa-file-alt"></i> Exams
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
                    <h1 class="h2">Admin Dashboard</h1>
                    <?php renderSchoolSelector($pdo, 'dashboard.php'); ?>
                </div>

                <?php if ($school): ?>
                <div class="alert alert-info">
                    <div class="d-flex align-items-center">
                        <?php if (!empty($school['logo_path'])): ?>
                            <img src="<?php echo '../' . htmlspecialchars($school['logo_path']); ?>" alt="School Logo" style="height: 50px; margin-right: 15px;">
                        <?php endif; ?>
                        <div>
                            <h4 class="mb-0"><?php echo htmlspecialchars($school['name']); ?></h4>
                            <p class="mb-0"><?php echo htmlspecialchars($school['address']); ?></p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-3 mb-4">
                        <div class="card text-white bg-primary">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="card-title">Students</h5>
                                        <h2 class="mb-0"><?php echo $studentCount; ?></h2>
                                    </div>
                                    <i class="fas fa-user-graduate fa-3x"></i>
                                </div>
                            </div>
                            <div class="card-footer d-flex justify-content-between">
                                <a href="students_list.php" class="text-white">View Details</a>
                                <i class="fas fa-arrow-circle-right"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-4">
                        <div class="card text-white bg-success">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="card-title">Classes</h5>
                                        <h2 class="mb-0"><?php echo $classCount; ?></h2>
                                    </div>
                                    <i class="fas fa-school fa-3x"></i>
                                </div>
                            </div>
                            <div class="card-footer d-flex justify-content-between">
                                <a href="create_class.php" class="text-white">View Details</a>
                                <i class="fas fa-arrow-circle-right"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-4">
                        <div class="card text-white bg-warning">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="card-title">Teachers</h5>
                                        <h2 class="mb-0"><?php echo $teacherCount; ?></h2>
                                    </div>
                                    <i class="fas fa-chalkboard-teacher fa-3x"></i>
                                </div>
                            </div>
                            <div class="card-footer d-flex justify-content-between">
                                <a href="teachers_list.php" class="text-white">View Details</a>
                                <i class="fas fa-arrow-circle-right"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-4">
                        <div class="card text-white bg-danger">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="card-title">Exams</h5>
                                        <h2 class="mb-0"><?php echo $examCount; ?></h2>
                                    </div>
                                    <i class="fas fa-file-alt fa-3x"></i>
                                </div>
                            </div>
                            <div class="card-footer d-flex justify-content-between">
                                <a href="exams_list.php" class="text-white">View Details</a>
                                <i class="fas fa-arrow-circle-right"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activities -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5>Recent Activities</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-group">
                            <!-- You can fetch recent activities from the database here -->
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                New student registered
                                <span class="badge bg-primary rounded-pill">Today</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Exam scores updated
                                <span class="badge bg-primary rounded-pill">Yesterday</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                New teacher added
                                <span class="badge bg-primary rounded-pill">3 days ago</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>