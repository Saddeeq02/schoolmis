<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php');
    exit;
}

$schoolId = $_SESSION['school_id'];
$error = '';
$success = '';

// Handle class creation/update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_class'])) {
        $classId = $_POST['class_id'] ?? null;
        $className = trim($_POST['class_name']);
        
        if (empty($className)) {
            $error = 'Class name is required.';
        } else {
            try {
                if ($classId) {
                    // Update existing class
                    $stmt = $pdo->prepare("
                        UPDATE classes 
                        SET class_name = ? 
                        WHERE id = ? AND school_id = ?
                    ");
                    $stmt->execute([$className, $classId, $schoolId]);
                    $success = 'Class updated successfully.';
                } else {
                    // Create new class
                    $stmt = $pdo->prepare("
                        INSERT INTO classes (school_id, class_name) 
                        VALUES (?, ?)
                    ");
                    $stmt->execute([$schoolId, $className]);
                    $success = 'Class created successfully.';
                }
            } catch (PDOException $e) {
                $error = 'Error saving class. Please try again.';
            }
        }
    }
    
    // Handle class deletion
    if (isset($_POST['delete_class'])) {
        $classId = $_POST['class_id'];
        
        // Check if class has students
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE class_id = ?");
        $stmt->execute([$classId]);
        $hasStudents = $stmt->fetchColumn() > 0;
        
        // Check if class has subjects
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM teacher_subjects WHERE class_id = ?");
        $stmt->execute([$classId]);
        $hasSubjects = $stmt->fetchColumn() > 0;
        
        if ($hasStudents || $hasSubjects) {
            $error = 'Cannot delete class. It has associated students or subjects.';
        } else {
            try {
                $stmt = $pdo->prepare("DELETE FROM classes WHERE id = ? AND school_id = ?");
                $stmt->execute([$classId, $schoolId]);
                $success = 'Class deleted successfully.';
            } catch (PDOException $e) {
                $error = 'Error deleting class. Please try again.';
            }
        }
    }
}

// Get existing classes
$stmt = $pdo->prepare("
    SELECT c.*, 
           (SELECT COUNT(*) FROM students s WHERE s.class_id = c.id) as student_count,
           (SELECT COUNT(*) FROM teacher_subjects ts WHERE ts.class_id = c.id) as subject_count
    FROM classes c 
    WHERE c.school_id = ? 
    ORDER BY c.class_name
");
$stmt->execute([$schoolId]);
$classes = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Classes - BI: School MIS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/clean-styles.css">


    
    <style>
        .class-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: var(--spacing-md);
            margin-bottom: var(--spacing-lg);
        }
        
        .class-card {
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: var(--spacing-md);
            background: var(--white);
        }
        
        .class-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--spacing-md);
        }
        
        .class-title {
            font-weight: 600;
            color: var(--text);
            margin: 0;
        }
        
        .class-section {
            color: var(--text-light);
            font-size: 0.875rem;
        }
        
        .class-actions {
            display: flex;
            gap: var(--spacing-sm);
        }
        
        .class-stats {
            display: flex;
            gap: var(--spacing-md);
            margin-top: var(--spacing-md);
            padding-top: var(--spacing-md);
            border-top: 1px solid var(--border);
        }
        
        .stat-item {
            display: flex;
            align-items: center;
            gap: var(--spacing-xs);
            color: var(--text-light);
            font-size: 0.875rem;
        }
        
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            align-items: center;
            justify-content: center;
            padding: var(--spacing-md);
            z-index: 1000;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: var(--white);
            border-radius: var(--radius);
            padding: var(--spacing-lg);
            width: 100%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        @media (max-width: 768px) {
            .class-grid {
                grid-template-columns: 1fr;
            }
            
            .modal-content {
                margin: var(--spacing-sm);
            }
            
            .class-stats {
                flex-direction: column;
                gap: var(--spacing-sm);
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="card mb-4">
            <div class="d-flex justify-content-between align-items-center">
                <h1 class="mb-0">Manage Classes</h1>
                <div class="d-flex gap-2">
                    <button onclick="showAddModal()" class="btn">
                        <i class="fas fa-plus"></i>
                        <span class="d-none d-md-inline ml-2">Add Class</span>
                    </button>
                    <a href="dashboard.php" class="btn">
                        <i class="fas fa-arrow-left"></i>
                        <span class="d-none d-md-inline ml-2">Back</span>
                    </a>
                </div>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger mb-4">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success mb-4">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <!-- Classes Grid -->
        <div class="class-grid">
            <?php foreach ($classes as $class): ?>
                <div class="class-card">
                    <div class="class-header">
                        <div>
                            <h3 class="class-title"><?= htmlspecialchars($class['class_name']) ?></h3>
                        </div>
                        <div class="class-actions">
                            <button onclick="showEditModal(<?= htmlspecialchars(json_encode($class)) ?>)" 
                                    class="btn btn-sm">
                                <i class="fas fa-edit"></i>
                            </button>
                            <?php if ($class['student_count'] == 0 && $class['subject_count'] == 0): ?>
                                <form method="post" action="" class="d-inline" 
                                      onsubmit="return confirm('Are you sure you want to delete this class?')">
                                    <input type="hidden" name="class_id" value="<?= $class['id'] ?>">
                                    <button type="submit" name="delete_class" class="btn btn-sm">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="class-stats">
                        <div class="stat-item">
                            <i class="fas fa-user-graduate"></i>
                            <?= $class['student_count'] ?> Students
                        </div>
                        <div class="stat-item">
                            <i class="fas fa-book"></i>
                            <?= $class['subject_count'] ?> Subjects
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <?php if (empty($classes)): ?>
            <div class="card">
                <p class="text-center mb-0">No classes found. Click "Add Class" to create one.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Add/Edit Modal -->
    <div class="modal" id="classModal">
        <div class="modal-content">
            <h2 class="mb-4" id="modalTitle">Add Class</h2>
            
            <form method="post" action="" id="classForm" class="d-flex flex-column gap-3">
                <input type="hidden" name="class_id" id="classId">
                
                <div class="form-group">
                    <label for="class_name">Class Name</label>
                    <input type="text" 
                           id="class_name" 
                           name="class_name" 
                           required 
                           placeholder="e.g., Grade 1, Form 4, JSS 2">
                </div>
                
                <div class="d-flex gap-2 justify-content-end">
                    <button type="button" onclick="hideModal()" class="btn">Cancel</button>
                    <button type="submit" name="save_class" class="btn">Save Class</button>
                </div>
            </form>
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


    <script>
    function showAddModal() {
        document.getElementById('modalTitle').textContent = 'Add Class';
        document.getElementById('classId').value = '';
        document.getElementById('classForm').reset();
        document.getElementById('classModal').classList.add('active');
    }
    
    function showEditModal(classData) {
        document.getElementById('modalTitle').textContent = 'Edit Class';
        document.getElementById('classId').value = classData.id;
        document.getElementById('class_name').value = classData.class_name;
        document.getElementById('classModal').classList.add('active');
    }
    
    function hideModal() {
        document.getElementById('classModal').classList.remove('active');
    }
    
    // Close modal when clicking outside
    document.getElementById('classModal').addEventListener('click', function(e) {
        if (e.target === this) {
            hideModal();
        }
    });
    </script>
            <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="../assets/clean-styles.css">


</body>
</html>