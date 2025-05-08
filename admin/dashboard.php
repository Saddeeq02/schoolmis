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
try {
    // Query to fetch recent activities (attendance and recordings)
    $activitiesQuery = "
        SELECT 
            u.name AS teacher_name,
            s.name AS school_name,
            a.clock_in AS activity_time,
            CASE 
                WHEN a.clock_out IS NULL THEN 'Clocked In'
                ELSE 'Clocked Out'
            END AS activity_type
        FROM attendance a
        JOIN users u ON a.user_id = u.id
        JOIN schools s ON a.school_id = s.id
        WHERE a.clock_in IS NOT NULL
        UNION
        SELECT 
            u.name AS teacher_name,
            s.name AS school_name,
            r.created_at AS activity_time,
            'Submitted Recording' AS activity_type
        FROM recordings r
        JOIN users u ON r.teacher_id = u.id
        JOIN schools s ON u.school_id = s.id
        ORDER BY activity_time DESC
        LIMIT 5
    ";

    $stmt = $pdo->prepare($activitiesQuery);
    $stmt->execute();
    $recentActivities = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Recent Activities Error: " . $e->getMessage());
    $recentActivities = []; // Fallback to empty array on error
}

// Function to format relative time (e.g., "5 minutes ago")
// function formatRelativeTime($timestamp) {
//     $now = new DateTime();
//     $activityTime = new DateTime($timestamp);
//     $diff = $now->diff($activityTime);

//     if ($diff->y > 0) {
//         return $diff->y . " year" . ($diff->y > 1 ? "s" : "") . " ago";
//     } elseif ($diff->m > 0) {
//         return $diff->m . " month" . ($diff->m > 1 ? "s" : "") . " ago";
//     } elseif ($diff->d > 0) {
//         return $diff->d . " day" . ($diff->d > 1 ? "s" : "") . " ago";
//     } elseif ($diff->h > 0) {
//         return $diff->h . " hour" . ($diff->h > 1 ? "s" : "") . " ago";
//     } elseif ($diff->i > 0) {
//         return $diff->i . " minute" . ($diff->i > 1 ? "s" : "") . " ago";
//     } else {
//         return "Just now";
//     }
// }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - School MIS</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .navbar {
            background-color: #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,.08);
        }
        .navbar-brand {
            font-weight: 600;
        }
        .stat-card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,.04);
            transition: transform 0.2s;
            height: 100%;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-icon {
            font-size: 2rem;
            opacity: 0.8;
        }
        .menu-card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,.04);
            transition: all 0.3s;
            text-decoration: none;
            color: #212529;
            height: 100%;
        }
        .menu-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 12px rgba(0,0,0,.08);
            color: #0d6efd;
        }
        .menu-icon {
            font-size: 1.8rem;
            margin-bottom: 10px;
        }
        .school-info {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,.04);
        }
        .school-logo {
            max-height: 60px;
            max-width: 100%;
        }
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            box-shadow: 0 -2px 10px rgba(0,0,0,.1);
            z-index: 1000;
            display: none;
        }
        @media (max-width: 768px) {
            .bottom-nav {
                display: flex;
            }
            .container {
                margin-bottom: 70px;
            }
        }
    </style>
</head>
<body>
    <!-- Top Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light sticky-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-school me-2"></i>School MIS
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <?php renderSchoolSelector($pdo, 'dashboard.php'); ?>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="school_details.php">
                            <i class="fas fa-cog me-1"></i>Settings
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../logout.php">
                            <i class="fas fa-sign-out-alt me-1"></i>Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- School Info -->
        <?php if ($school): ?>
        <div class="school-info p-3 mb-4">
            <div class="d-flex align-items-center">
                <?php if (!empty($school['logo_path'])): ?>
                    <img src="<?php echo '../' . htmlspecialchars($school['logo_path']); ?>" alt="School Logo" class="school-logo me-3">
                <?php endif; ?>
                <div>
                    <h4 class="mb-0"><?php echo htmlspecialchars($school['name']); ?></h4>
                    <p class="text-muted mb-0"><?php echo htmlspecialchars($school['address']); ?></p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="stat-card bg-primary text-white p-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="mb-0">Students</h6>
                            <h3 class="mb-0"><?php echo $studentCount; ?></h3>
                        </div>
                        <i class="fas fa-user-graduate stat-icon"></i>
                    </div>
                    <a href="students_list.php" class="text-white small">View all <i class="fas fa-arrow-right ms-1"></i></a>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card bg-success text-white p-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="mb-0">Classes</h6>
                            <h3 class="mb-0"><?php echo $classCount; ?></h3>
                        </div>
                        <i class="fas fa-chalkboard stat-icon"></i>
                    </div>
                    <a href="create_class.php" class="text-white small">View all <i class="fas fa-arrow-right ms-1"></i></a>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card bg-warning text-white p-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="mb-0">Teachers</h6>
                            <h3 class="mb-0"><?php echo $teacherCount; ?></h3>
                        </div>
                        <i class="fas fa-chalkboard-teacher stat-icon"></i>
                    </div>
                    <a href="teachers_list.php" class="text-white small">View all <i class="fas fa-arrow-right ms-1"></i></a>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card bg-danger text-white p-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="mb-0">Exams</h6>
                            <h3 class="mb-0"><?php echo $examCount; ?></h3>
                        </div>
                        <i class="fas fa-file-alt stat-icon"></i>
                    </div>
                    <a href="exams_list.php" class="text-white small">View all <i class="fas fa-arrow-right ms-1"></i></a>
                </div>
            </div>
        </div>

        <!-- Menu Cards -->
        <h5 class="mb-3">Management</h5>
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <a href="schools.php" class="menu-card d-flex flex-column align-items-center p-3">
                    <i class="fas fa-school menu-icon text-primary"></i>
                    <span>Schools</span>
                </a>
            </div>
            <div class="col-6 col-md-3">
                <a href="teachers_list.php" class="menu-card d-flex flex-column align-items-center p-3">
                    <i class="fas fa-chalkboard-teacher menu-icon text-success"></i>
                    <span>Teachers</span>
                </a>
            </div>
            <div class="col-6 col-md-3">
                <a href="students_list.php" class="menu-card d-flex flex-column align-items-center p-3">
                    <i class="fas fa-user-graduate menu-icon text-info"></i>
                    <span>Students</span>
                </a>
            </div>
            <div class="col-6 col-md-3">
                <a href="create_class.php" class="menu-card d-flex flex-column align-items-center p-3">
                    <i class="fas fa-chalkboard menu-icon text-warning"></i>
                    <span>Classes</span>
                </a>
            </div>
        </div>

        <h5 class="mb-3">Academic</h5>
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <a href="create_subject.php" class="menu-card d-flex flex-column align-items-center p-3">
                    <i class="fas fa-book menu-icon text-danger"></i>
                    <span>Subjects</span>
                </a>
            </div>
            <div class="col-6 col-md-3">
                <a href="teacher_subjects.php" class="menu-card d-flex flex-column align-items-center p-3">
                    <i class="fas fa-user-tie menu-icon text-secondary"></i>
                    <span>Teacher Subjects</span>
                </a>
            </div>
            <div class="col-6 col-md-3">
                <a href="exams_list.php" class="menu-card d-flex flex-column align-items-center p-3">
                    <i class="fas fa-file-alt menu-icon text-primary"></i>
                    <span>Exams</span>
                </a>
            </div>
            <div class="col-6 col-md-3">
                <a href="report.php" class="menu-card d-flex flex-column align-items-center p-3">
                    <i class="fas fa-chart-bar menu-icon text-success"></i>
                    <span>Attendance Reports</span>
                </a>
            </div>
        </div>

        <h5 class="mb-3">Tools</h5>
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <a href="admin_qr_generator.php" class="menu-card d-flex flex-column align-items-center p-3">
                    <i class="fas fa-qrcode menu-icon text-dark"></i>
                    <span>QR Generator</span>
                </a>
            </div>
            <div class="col-6 col-md-3">
                <a href="view_recordings.php" class="menu-card d-flex flex-column align-items-center p-3">
                    <i class="fas fa-video menu-icon text-info"></i>
                    <span>Recordings</span>
                </a>
            </div>
            <div class="col-6 col-md-3">
                <a href="promote_students.php" class="menu-card d-flex flex-column align-items-center p-3">
                    <i class="fas fa-graduation-cap menu-icon text-success"></i>
                    <span>Student Promotion</span>
                </a>
            </div>
            <div class="col-6 col-md-3">
                <a href="archive_exam.php" class="menu-card d-flex flex-column align-items-center p-3">
                    <i class="fas fa-archive menu-icon text-warning"></i>
                    <span>Archive Exams</span>
                </a>
            </div>
            <div class="col-6 col-md-3">
                <a href="clean_records.php" class="menu-card d-flex flex-column align-items-center p-3">
                    <i class="fas fa-broom menu-icon text-danger"></i>
                    <span>Clean Records</span>
                </a>
            </div>
            <div class="col-6 col-md-3">
                <a href="ai_report.php" class="menu-card d-flex flex-column align-items-center p-3">
                    <i class="fas fa-robot"></i>
                    <span>AI Report</span>
                </a>
            </div>
            
            
            <div class="col-6 col-md-3">
                <a href="school_details.php" class="menu-card d-flex flex-column align-items-center p-3">
                    <i class="fas fa-cog menu-icon text-secondary"></i>
                    <span>Settings</span>
                </a>
            </div>
        </div>

        <!-- Recent Activities -->
        <div class="card mt-4">
    <div class="card-header bg-white">
        <h5 class="mb-0">Recent Activities</h5>
    </div>
    <div class="card-body">
        <ul class="list-group list-group-flush">
            <?php if (empty($recentActivities)): ?>
                <li class="list-group-item text-muted">
                    No recent activities found.
                </li>
            <?php else: ?>
                <?php foreach ($recentActivities as $activity): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span>
                            <i class="fas <?php 
                                echo $activity['activity_type'] === 'Clocked In' ? 'fa-sign-in-alt text-primary' : 
                                     ($activity['activity_type'] === 'Clocked Out' ? 'fa-sign-out-alt text-danger' : 
                                     'fa-microphone text-success'); 
                            ?> me-2"></i>
                            <strong><?= htmlspecialchars($activity['teacher_name']) ?></strong> 
                            (<?= htmlspecialchars($activity['school_name']) ?>) 
                            <?= htmlspecialchars($activity['activity_type']) ?>
                        </span>
                        <span class="badge bg-<?php 
                            echo $activity['activity_type'] === 'Clocked In' ? 'primary' : 
                                 ($activity['activity_type'] === 'Clocked Out' ? 'danger' : 'success'); 
                        ?> rounded-pill">
                            <?= formatRelativeTime($activity['activity_time']) ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>
    </div>
</div>
    <!-- Bottom Navigation for Mobile -->
    <div class="bottom-nav py-2 px-3 justify-content-around">
        <a href="dashboard.php" class="btn btn-sm btn-light active">
            <i class="fas fa-home d-block text-center mb-1"></i>
            <small>Home</small>
        </a>
        <a href="students_list.php" class="btn btn-sm btn-light">
            <i class="fas fa-user-graduate d-block text-center mb-1"></i>
            <small>Students</small>
        </a>
        <a href="teachers_list.php" class="btn btn-sm btn-light">
            <i class="fas fa-chalkboard-teacher d-block text-center mb-1"></i>
            <small>Teachers</small>
        </a>
        <a href="exams_list.php" class="btn btn-sm btn-light">
            <i class="fas fa-file-alt d-block text-center mb-1"></i>
            <small>Exams</small>
        </a>
        <a href="#" class="btn btn-sm btn-light" data-bs-toggle="offcanvas" data-bs-target="#moreMenu">
            <i class="fas fa-ellipsis-h d-block text-center mb-1"></i>
            <small>More</small>
        </a>
    </div>

    <!-- More Menu Offcanvas for Mobile -->
    <div class="offcanvas offcanvas-bottom" tabindex="-1" id="moreMenu" style="height: 60vh;">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title">More Options</h5>
            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <div class="row g-3">
                <div class="col-4">
                    <a href="schools.php" class="d-flex flex-column align-items-center text-decoration-none">
                        <div class="bg-light p-3 rounded-circle mb-2">
                            <i class="fas fa-school text-primary"></i>
                        </div>
                        <span class="small">Schools</span>
                    </a>
                </div>
                <div class="col-4">
                    <a href="create_class.php" class="d-flex flex-column align-items-center text-decoration-none">
                        <div class="bg-light p-3 rounded-circle mb-2">
                            <i class="fas fa-chalkboard text-warning"></i>
                        </div>
                        <span class="small">Classes</span>
                    </a>
                </div>
                <div class="col-4">
                    <a href="create_subject.php" class="d-flex flex-column align-items-center text-decoration-none">
                        <div class="bg-light p-3 rounded-circle mb-2">
                            <i class="fas fa-book text-danger"></i>
                        </div>
                        <span class="small">Subjects</span>
                    </a>
                </div>
                <div class="col-4">
                    <a href="teacher_subjects.php" class="d-flex flex-column align-items-center text-decoration-none">
                        <div class="bg-light p-3 rounded-circle mb-2">
                            <i class="fas fa-user-tie text-secondary"></i>
                        </div>
                        <span class="small">Teacher Subjects</span>
                    </a>
                </div>
                <div class="col-4">
                    <a href="admin_qr_generator.php" class="d-flex flex-column align-items-center text-decoration-none">
                        <div class="bg-light p-3 rounded-circle mb-2">
                            <i class="fas fa-qrcode text-dark"></i>
                        </div>
                        <span class="small">QR Generator</span>
                    </a>
                </div>
                <div class="col-4">
                    <a href="view_recordings.php" class="d-flex flex-column align-items-center text-decoration-none">
                        <div class="bg-light p-3 rounded-circle mb-2">
                            <i class="fas fa-video text-info"></i>
                        </div>
                        <span class="small">Recordings</span>
                    </a>
                </div>
                <div class="col-4">
                    <a href="report.php" class="d-flex flex-column align-items-center text-decoration-none">
                        <div class="bg-light p-3 rounded-circle mb-2">
                            <i class="fas fa-chart-bar text-success"></i>
                        </div>
                        <span class="small">Reports</span>
                    </a>
                </div>
                <div class="col-4">
                    <a href="school_details.php" class="d-flex flex-column align-items-center text-decoration-none">
                        <div class="bg-light p-3 rounded-circle mb-2">
                            <i class="fas fa-cog text-secondary"></i>
                        </div>
                        <span class="small">Settings</span>
                    </a>
                </div>
                <div class="col-4">
                    <a href="../logout.php" class="d-flex flex-column align-items-center text-decoration-none">
                        <div class="bg-light p-3 rounded-circle mb-2">
                            <i class="fas fa-sign-out-alt text-danger"></i>
                        </div>
                        <span class="small">Logout</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
