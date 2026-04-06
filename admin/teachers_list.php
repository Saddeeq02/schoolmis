<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/config.php';
require_once '../components/school_selector.php';

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

requireAdmin();

$schoolId = $_SESSION['school_id'] ?? 1;
$success = '';
$error = '';

// Handle teacher deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_teacher'])) {
    $teacherId = $_POST['teacher_id'];
    
    try {
        // First check if teacher has any assignments or scores
        $checkStmt = $pdo->prepare("
            SELECT 
                (SELECT COUNT(*) FROM teacher_subjects WHERE teacher_id = ?) as assignments_count,
                (SELECT COUNT(*) FROM student_scores WHERE created_by = ?) as scores_count
        ");
        $checkStmt->execute([$teacherId, $teacherId]);
        $counts = $checkStmt->fetch();
        
        if ($counts['assignments_count'] > 0 || $counts['scores_count'] > 0) {
            $error = "Cannot delete teacher: They have existing assignments or scores. Please remove all assignments and scores first.";
        } else {
            // Proceed with deletion
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'teacher'");
            if ($stmt->execute([$teacherId])) {
                $success = "Teacher deleted successfully";
            } else {
                $error = "Failed to delete teacher";
            }
        }
    } catch (PDOException $e) {
        $error = "Error deleting teacher";
        error_log($e->getMessage());
    }
}

// Handle school assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_school'])) {
    $teacherId = $_POST['teacher_id'];
    $newSchoolId = $_POST['new_school_id'];
    
    try {
        $stmt = $pdo->prepare("UPDATE users SET school_id = ? WHERE id = ? AND role = 'teacher'");
        if ($stmt->execute([$newSchoolId, $teacherId])) {
            $success = "Teacher's school updated successfully";
        } else {
            $error = "Failed to update teacher's school";
        }
    } catch (PDOException $e) {
        $error = "Error updating teacher's school";
        error_log($e->getMessage());
    }
}

// Get all schools for the dropdown
try {
    $schoolsStmt = $pdo->query("SELECT id, name FROM schools ORDER BY name");
    $schools = $schoolsStmt->fetchAll();
} catch (PDOException $e) {
    $error = "Error fetching schools";
    error_log($e->getMessage());
    $schools = [];
}

// Fetch teachers with school information and additional counts
try {
    $query = "
        SELECT u.*, 
               s.name as school_name,
               (SELECT COUNT(*) FROM teacher_subjects WHERE teacher_id = u.id) as assignments_count,
               (SELECT COUNT(*) FROM student_scores WHERE created_by = u.id) as scores_count
        FROM users u 
        LEFT JOIN schools s ON u.school_id = s.id 
        WHERE u.role = 'teacher'";
    
    $params = [];
    
    if (isset($_GET['school_id']) && $_GET['school_id'] > 0) {
        $query .= " AND u.school_id = ?";
        $params[] = $_GET['school_id'];
    }
    
    $query .= " ORDER BY u.name";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $teachers = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Error fetching teachers";
    error_log($e->getMessage());
    $teachers = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teachers List - School MIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/clean-styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .teacher-card {
            background: white;
            border-radius: var(--radius);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 1rem;
            padding: 1rem;
        }
        .teacher-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            align-items: center;
            justify-content: center;
            padding: 1rem;
            z-index: 1000;
        }
        .modal.active {
            display: flex;
        }
        .modal-content {
            background: white;
            border-radius: var(--radius);
            padding: 1.5rem;
            width: 100%;
            max-width: 500px;
        }
        .delete-btn {
            background-color: #dc3545;
            color: white;
            border: none;
        }
        .delete-btn:hover {
            background-color: #bb2d3b;
        }
        .delete-btn:disabled {
            background-color: #e9ecef;
            color: #6c757d;
            cursor: not-allowed;
        }
        .teacher-stats {
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: 0.25rem;
        }
        .alert-stack {
            position: fixed;
            top: 1rem;
            right: 1rem;
            z-index: 1100;
            max-width: 400px;
        }
        .alert-stack .alert {
            margin-bottom: 0.5rem;
            animation: slideInRight 0.3s ease-out;
        }
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
    </style>
</head>
<body>
    <!-- Alert Stack -->
    <div class="alert-stack" id="alertStack"></div>

    <div class="container">
        <!-- Header -->
        <div class="card mb-4">
            <div class="d-flex justify-content-between align-items-center">
                <h1 class="mb-0">Teachers List</h1>
                <div class="d-flex gap-2">
                    <a href="dashboard.php" class="btn">
                        <i class="fas fa-arrow-left"></i>
                        <span class="d-none d-md-inline ml-2">Back</span>
                    </a>
                    <button class="btn" onclick="window.location.href='add_teacher.php'">
                       <i class="fas fa-plus"></i>
                       <span class="d-none d-md-inline ml-2">Add Teacher</span>
                   </button>
                </div>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Filter -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="get" class="d-flex gap-2">
                    <select name="school_id" class="form-select">
                        <option value="0">All Schools</option>
                        <?php foreach ($schools as $school): ?>
                            <option value="<?= $school['id'] ?>" 
                                <?= (isset($_GET['school_id']) && $_GET['school_id'] == $school['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($school['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn">Filter</button>
                </form>
            </div>
        </div>

        <!-- Teachers List -->
        <div class="teachers-grid">
            <?php if (!empty($teachers)): ?>
                <?php foreach ($teachers as $teacher): ?>
                    <div class="teacher-card">
                        <div class="teacher-info">
                            <div>
                                <h3><?= htmlspecialchars($teacher['name']) ?></h3>
                                <p class="text-muted mb-0"><?= htmlspecialchars($teacher['email']) ?></p>
                                <p class="text-muted mb-0">
                                    <i class="fas fa-school"></i>
                                    <?= htmlspecialchars($teacher['school_name'] ?? 'Not Assigned') ?>
                                </p>
                                <div class="teacher-stats">
                                    <span><i class="fas fa-book"></i> <?= $teacher['assignments_count'] ?> assignments</span>
                                    <span class="ms-2"><i class="fas fa-chart-bar"></i> <?= $teacher['scores_count'] ?> scores</span>
                                </div>
                            </div>
                            <div class="d-flex gap-2">
                                <button onclick="showAssignSchool(<?= htmlspecialchars(json_encode($teacher)) ?>)" 
                                        class="btn btn-sm">
                                    <i class="fas fa-building"></i>
                                    <span class="d-none d-md-inline">School</span>
                                </button>
                                <a href="teacher_subjects.php?teacher_id=<?= $teacher['id'] ?>" class="btn btn-sm">
                                    <i class="fas fa-book"></i>
                                    <span class="d-none d-md-inline">Subjects</span>
                                </a>
                                <a href="view_recordings.php?teacher_id=<?= $teacher['id'] ?>" class="btn btn-sm">
                                    <i class="fas fa-video"></i>
                                    <span class="d-none d-md-inline">Recordings</span>
                                </a>
                                <button onclick="showDeleteConfirm(<?= htmlspecialchars(json_encode($teacher)) ?>)" 
                                        class="btn btn-sm delete-btn"
                                        <?= ($teacher['assignments_count'] > 0 || $teacher['scores_count'] > 0) ? 'disabled' : '' ?>
                                        title="<?= ($teacher['assignments_count'] > 0 || $teacher['scores_count'] > 0) ? 'Cannot delete: Teacher has assignments or scores' : 'Delete teacher' ?>">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="card">
                    <div class="card-body text-center">
                        <p class="mb-0">No teachers found.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Assign School Modal -->
    <div class="modal" id="assignSchoolModal">
        <div class="modal-content">
            <h2 class="mb-4">Assign School</h2>
            <form method="post" id="assignSchoolForm">
                <input type="hidden" name="teacher_id" id="teacherId">
                
                <div class="mb-3">
                    <label for="teacherName" class="form-label">Teacher</label>
                    <input type="text" id="teacherName" class="form-control" readonly>
                </div>

                <div class="mb-3">
                    <label for="new_school_id" class="form-label">Select School</label>
                    <select name="new_school_id" id="new_school_id" class="form-select" required>
                        <option value="">Select a school</option>
                        <?php foreach ($schools as $school): ?>
                            <option value="<?= $school['id'] ?>">
                                <?= htmlspecialchars($school['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <button type="button" onclick="hideModal('assignSchoolModal')" class="btn">Cancel</button>
                    <button type="submit" name="assign_school" class="btn btn-primary">Assign School</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal" id="deleteConfirmModal">
        <div class="modal-content">
            <h2 class="mb-4">Confirm Delete</h2>
            <form method="post" id="deleteForm">
                <input type="hidden" name="teacher_id" id="deleteTeacherId">
                
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    Are you sure you want to delete this teacher?
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Teacher</label>
                    <p id="deleteTeacherName" class="fw-bold"></p>
                </div>

                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <p id="deleteTeacherEmail"></p>
                </div>

                <div id="deleteWarning" class="alert alert-danger" style="display: none;">
                    <i class="fas fa-ban"></i>
                    This teacher has assignments or scores and cannot be deleted.
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <button type="button" onclick="hideModal('deleteConfirmModal')" class="btn">Cancel</button>
                    <button type="submit" name="delete_teacher" class="btn delete-btn">Delete Teacher</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Bottom Navigation for Mobile -->
    <div class="bottom-nav py-2 px-3 justify-content-around">
        <a href="dashboard.php" class="btn btn-sm btn-light">
            <i class="fas fa-home d-block text-center mb-1"></i>
            <small>Home</small>
        </a>
        <a href="students_list.php" class="btn btn-sm btn-light">
            <i class="fas fa-user-graduate d-block text-center mb-1"></i>
            <small>Students</small>
        </a>
        <a href="teachers_list.php" class="btn btn-sm btn-light active">
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showAssignSchool(teacher) {
            document.getElementById('teacherId').value = teacher.id;
            document.getElementById('teacherName').value = teacher.name;
            document.getElementById('new_school_id').value = teacher.school_id || '';
            document.getElementById('assignSchoolModal').classList.add('active');
        }
        
        function showDeleteConfirm(teacher) {
            document.getElementById('deleteTeacherId').value = teacher.id;
            document.getElementById('deleteTeacherName').textContent = teacher.name;
            document.getElementById('deleteTeacherEmail').textContent = teacher.email;
            
            // Show warning if teacher has assignments or scores
            const hasData = teacher.assignments_count > 0 || teacher.scores_count > 0;
            document.getElementById('deleteWarning').style.display = hasData ? 'block' : 'none';
            document.querySelector('#deleteForm button[type="submit"]').disabled = hasData;
            
            document.getElementById('deleteConfirmModal').classList.add('active');
        }
        
        function hideModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }
        
        // Close modal when clicking outside
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('active');
                }
            });
        });
        
        function showAlert(message, type = 'info') {
            const alertHtml = `
                <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            `;
            
            const alertStack = document.getElementById('alertStack');
            alertStack.insertAdjacentHTML('beforeend', alertHtml);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                const alert = alertStack.lastElementChild;
                if (alert) {
                    alert.classList.remove('show');
                    setTimeout(() => alert.remove(), 300);
                }
            }, 5000);
        }
    </script>
</body>
</html>