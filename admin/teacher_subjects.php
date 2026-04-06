<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/db.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php');
    exit;
}

$schoolId = $_SESSION['school_id'];
$error = '';
$success = '';

// Handle assignment creation/deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['assign_subject'])) {
        // Validate required fields
        if (!isset($_POST['teacher_id']) || empty($_POST['teacher_id'])) {
            $error = 'Teacher selection is required.';
        } elseif (!isset($_POST['subject_id']) || empty($_POST['subject_id'])) {
            $error = 'Subject selection is required.';
        } elseif (!isset($_POST['class_id']) || empty($_POST['class_id'])) {
            $error = 'Class selection is required.';
        } else {
            $teacherId = intval($_POST['teacher_id']);
            $subjectId = intval($_POST['subject_id']);
            $classId = intval($_POST['class_id']);
            
            // Check if assignment already exists
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM teacher_subjects 
                WHERE teacher_id = ? AND subject_id = ? AND class_id = ?
            ");
            $stmt->execute([$teacherId, $subjectId, $classId]);
            $exists = $stmt->fetchColumn() > 0;
            
            if ($exists) {
                $error = 'This subject is already assigned to the teacher for this class.';
            } else {
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO teacher_subjects (teacher_id, subject_id, class_id) 
                        VALUES (?, ?, ?)
                    ");
                    $stmt->execute([$teacherId, $subjectId, $classId]);
                    $success = 'Subject assigned successfully.';
                } catch (PDOException $e) {
                    $error = 'Error assigning subject. Please try again.';
                }
            }
        }
    }
    
    if (isset($_POST['remove_assignment'])) {
        $assignmentId = $_POST['assignment_id'];
        
        // First, get the assignment details
        $stmt = $pdo->prepare("
            SELECT teacher_id, subject_id, class_id 
            FROM teacher_subjects 
            WHERE id = ?
        ");
        $stmt->execute([$assignmentId]);
        $assignment = $stmt->fetch();
        
        if ($assignment) {
            // Check if there are any scores recorded
            // student_scores doesn't have class_id, so we need to join through students table
            $stmt = $pdo->prepare("
                SELECT COUNT(*) 
                FROM student_scores sc
                JOIN students st ON sc.student_id = st.id
                WHERE sc.created_by = ? 
                AND sc.subject_id = ? 
                AND st.class_id = ?
            ");
            $stmt->execute([$assignment['teacher_id'], $assignment['subject_id'], $assignment['class_id']]);
            $hasScores = $stmt->fetchColumn() > 0;
            
            if ($hasScores) {
                $error = 'Cannot remove assignment. Scores have been recorded.';
            } else {
                try {
                    $stmt = $pdo->prepare("DELETE FROM teacher_subjects WHERE id = ?");
                    $stmt->execute([$assignmentId]);
                    $success = 'Assignment removed successfully.';
                } catch (PDOException $e) {
                    $error = 'Error removing assignment. Please try again.';
                }
            }
        } else {
            $error = 'Assignment not found.';
        }
    }
}

// Get teachers (fix: use users table with role = 'teacher')
$stmt = $pdo->prepare("
    SELECT * FROM users 
    WHERE role = 'teacher' AND school_id = ? 
    ORDER BY name
");
$stmt->execute([$schoolId]);
$teachers = $stmt->fetchAll();

// Get subjects
$stmt = $pdo->prepare("
    SELECT * FROM subjects 
    WHERE school_id = ? 
    ORDER BY subject_name
");
$stmt->execute([$schoolId]);
$subjects = $stmt->fetchAll();

// Get classes
$stmt = $pdo->prepare("
    SELECT * FROM classes 
    WHERE school_id = ? 
    ORDER BY class_name
");
$stmt->execute([$schoolId]);
$classes = $stmt->fetchAll();

// Get existing assignments with additional info
$stmt = $pdo->prepare("
    SELECT ts.*, 
           u.name as teacher_name,
           s.subject_name,
           c.class_name,
           (SELECT COUNT(*) 
            FROM student_scores sc 
            JOIN students st ON sc.student_id = st.id
            WHERE sc.created_by = ts.teacher_id 
              AND sc.subject_id = ts.subject_id 
              AND st.class_id = ts.class_id) as score_count
    FROM teacher_subjects ts
    JOIN users u ON ts.teacher_id = u.id AND u.role = 'teacher'
    JOIN subjects s ON ts.subject_id = s.id
    JOIN classes c ON ts.class_id = c.id
    WHERE u.school_id = ?
    ORDER BY u.name, s.subject_name, c.class_name
");
$stmt->execute([$schoolId]);
$assignments = $stmt->fetchAll();

// Group assignments by teacher for better organization
$groupedAssignments = [];
foreach ($assignments as $assignment) {
    $teacherId = $assignment['teacher_id'];
    if (!isset($groupedAssignments[$teacherId])) {
        $groupedAssignments[$teacherId] = [
            'teacher_name' => $assignment['teacher_name'],
            'assignments' => []
        ];
    }
    $groupedAssignments[$teacherId]['assignments'][] = $assignment;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Subject Assignments - School MIS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/clean-styles.css">

    
    <style>
        .teacher-section {
            margin-bottom: 1.5rem;
        }
        
        .teacher-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .teacher-name {
            font-weight: 600;
            color: #333;
            margin: 0;
            font-size: 1.2rem;
        }
        
        .assignments-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1rem;
        }
        
        .assignment-card {
            border: 1px solid #dee2e6;
            border-radius: 0.5rem;
            padding: 1rem;
            background: #fff;
            transition: box-shadow 0.2s;
        }
        
        .assignment-card:hover {
            box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
        }
        
        .subject-info {
            margin-bottom: 0.5rem;
        }
        
        .subject-name {
            font-weight: 600;
            color: #333;
            margin: 0;
            font-size: 1.1rem;
        }
        
        .class-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #6c757d;
            font-size: 0.9rem;
            margin-top: 0.25rem;
        }
        
        .score-count {
            margin-top: 0.75rem;
            padding-top: 0.75rem;
            border-top: 1px solid #dee2e6;
            color: #6c757d;
            font-size: 0.875rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .score-info {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .delete-btn {
            background: none;
            border: none;
            color: #dc3545;
            cursor: pointer;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            transition: background-color 0.2s;
        }
        
        .delete-btn:hover {
            background-color: #f8d7da;
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
            z-index: 1050;
        }
        
        .modal.show {
            display: flex;
            animation: fadeIn 0.3s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .modal-content {
            background: #fff;
            border-radius: 0.5rem;
            padding: 1.5rem;
            width: 100%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            animation: slideIn 0.3s ease-out;
        }
        
        @keyframes slideIn {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
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
        
        @media (max-width: 768px) {
            .assignments-grid {
                grid-template-columns: 1fr;
            }
            
            .teacher-header {
                flex-direction: column;
                gap: 0.5rem;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <!-- Alert Stack -->
    <div class="alert-stack" id="alertStack"></div>

    <div class="container">
        <!-- Header -->
        <div class="card mb-4">
            <div class="card-body d-flex justify-content-between align-items-center">
                <h1 class="mb-0">Teacher Subject Assignments</h1>
                <div class="d-flex gap-2">
                    <button onclick="showAssignModal()" class="btn btn-primary">
                        <i class="fas fa-plus"></i>
                        <span class="d-none d-md-inline ms-2">Add Assignment</span>
                    </button>
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        <span class="d-none d-md-inline ms-2">Back</span>
                    </a>
                </div>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Teacher Sections -->
        <?php foreach ($groupedAssignments as $teacherId => $data): ?>
            <div class="teacher-section">
                <div class="teacher-header">
                    <h2 class="teacher-name">
                        <i class="fas fa-user-tie"></i> <?= htmlspecialchars($data['teacher_name']) ?>
                    </h2>
                    <button onclick="showAssignModal('<?= $teacherId ?>')" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus"></i>
                        <span class="d-none d-md-inline ms-2">Add Subject</span>
                    </button>
                </div>
                
                <div class="assignments-grid">
                    <?php foreach ($data['assignments'] as $assignment): ?>
                        <div class="assignment-card">
                            <div class="subject-info">
                                <h3 class="subject-name">
                                    <i class="fas fa-book"></i> <?= htmlspecialchars($assignment['subject_name']) ?>
                                </h3>
                            </div>
                            
                            <div class="class-info">
                                <i class="fas fa-graduation-cap"></i>
                                <?= htmlspecialchars($assignment['class_name']) ?>
                            </div>
                            
                            <div class="score-count">
                                <div class="score-info">
                                    <i class="fas fa-chart-bar"></i>
                                    <?= $assignment['score_count'] ?> Scores Recorded
                                </div>
                                
                                <?php if ($assignment['score_count'] == 0): ?>
                                    <form method="post" action="" class="d-inline" 
                                          onsubmit="return confirmDelete()">
                                        <input type="hidden" name="assignment_id" value="<?= $assignment['id'] ?>">
                                        <button type="submit" name="remove_assignment" class="delete-btn" 
                                                title="Remove Assignment">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-muted" title="Cannot delete - scores recorded">
                                        <i class="fas fa-lock"></i>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
        
        <?php if (empty($groupedAssignments)): ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No assignments found. Click "Add Assignment" to create one.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Assignment Modal -->
    <div class="modal" id="assignmentModal">
        <div class="modal-content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0">Add Subject Assignment</h2>
                <button type="button" class="btn-close" onclick="hideModal()"></button>
            </div>
            
            <form method="post" action="" id="assignmentForm">
                <div class="mb-3">
                    <label for="teacher_id" class="form-label">Teacher <span class="text-danger">*</span></label>
                    <select id="teacher_id" name="teacher_id" class="form-select" required>
                        <option value="">Select Teacher</option>
                        <?php foreach ($teachers as $teacher): ?>
                            <option value="<?= $teacher['id'] ?>">
                                <?= htmlspecialchars($teacher['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label for="subject_id" class="form-label">Subject <span class="text-danger">*</span></label>
                    <select id="subject_id" name="subject_id" class="form-select" required>
                        <option value="">Select Subject</option>
                        <?php foreach ($subjects as $subject): ?>
                            <option value="<?= $subject['id'] ?>">
                                <?= htmlspecialchars($subject['subject_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label for="class_id" class="form-label">Class <span class="text-danger">*</span></label>
                    <select id="class_id" name="class_id" class="form-select" required>
                        <option value="">Select Class</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?= $class['id'] ?>">
                                <?= htmlspecialchars($class['class_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="d-flex gap-2 justify-content-end">
                    <button type="button" onclick="hideModal()" class="btn btn-secondary">Cancel</button>
                    <button type="submit" name="assign_subject" class="btn btn-primary">Assign Subject</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="../assets/clean-styles.css">

    
    <script>
    function showAssignModal(teacherId = '') {
        const modal = document.getElementById('assignmentModal');
        const teacherSelect = document.getElementById('teacher_id');
        
        teacherSelect.value = teacherId;
        teacherSelect.disabled = !!teacherId;
        
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
    }
    
    function hideModal() {
        const modal = document.getElementById('assignmentModal');
        const teacherSelect = document.getElementById('teacher_id');
        
        modal.classList.remove('show');
        document.body.style.overflow = '';
        teacherSelect.disabled = false;
        document.getElementById('assignmentForm').reset();
    }
    
    function confirmDelete() {
        return confirm('Are you sure you want to remove this assignment?');
    }
    
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
    
    // Close modal when clicking outside
    document.getElementById('assignmentModal').addEventListener('click', function(e) {
        if (e.target === this) {
            hideModal();
        }
    });
    
    // Show alerts for success/error messages
    <?php if ($success): ?>
    showAlert('<?= addslashes($success) ?>', 'success');
    <?php endif; ?>
    
    <?php if ($error): ?>
    showAlert('<?= addslashes($error) ?>', 'danger');
    <?php endif; ?>
    </script>
</body>
</html>