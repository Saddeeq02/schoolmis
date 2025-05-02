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
        
        // Check if there are any scores recorded
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM student_scores s
            JOIN teacher_subjects ts ON s.created_by = ts.teacher_id 
                AND s.subject_id = ts.subject_id 
                AND s.class_id = ts.class_id
            WHERE ts.id = ?
        ");
        $stmt->execute([$assignmentId]);
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
              AND st.class_id = ts.class_id
              AND st.school_id = u.school_id) as score_count
    FROM teacher_subjects ts
    JOIN users u ON ts.teacher_id = u.id AND u.role = 'teacher'
    JOIN subjects s ON ts.subject_id = s.id AND s.school_id = u.school_id
    JOIN classes c ON ts.class_id = c.id AND c.school_id = u.school_id
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
    <link rel="stylesheet" href="../assets/clean-styles.css">
    <style>
        .teacher-section {
            margin-bottom: var(--spacing-lg);
        }
        
        .teacher-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: var(--spacing-md);
            background: var(--light);
            border-radius: var(--radius);
            margin-bottom: var(--spacing-md);
        }
        
        .teacher-name {
            font-weight: 600;
            color: var(--text);
            margin: 0;
        }
        
        .assignments-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: var(--spacing-md);
        }
        
        .assignment-card {
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: var(--spacing-md);
            background: var(--white);
        }
        
        .subject-info {
            margin-bottom: var(--spacing-sm);
        }
        
        .subject-name {
            font-weight: 600;
            color: var(--text);
            margin: 0;
        }
        
        .class-info {
            display: flex;
            align-items: center;
            gap: var(--spacing-xs);
            color: var(--text);
            font-size: 0.875rem;
            margin-top: var(--spacing-xs);
        }
        
        .score-count {
            margin-top: var(--spacing-sm);
            padding-top: var(--spacing-sm);
            border-top: 1px solid var(--border);
            color: var(--text-light);
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: var(--spacing-xs);
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
            .assignments-grid {
                grid-template-columns: 1fr;
            }
            
            .modal-content {
                margin: var(--spacing-sm);
            }
            
            .teacher-header {
                flex-direction: column;
                gap: var(--spacing-sm);
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="card mb-4">
            <div class="d-flex justify-content-between align-items-center">
                <h1 class="mb-0">Teacher Subject Assignments</h1>
                <div class="d-flex gap-2">
                    <button onclick="showAssignModal()" class="btn">
                        <i class="fas fa-plus"></i>
                        <span class="d-none d-md-inline ml-2">Add Assignment</span>
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

        <!-- Teacher Sections -->
        <?php foreach ($groupedAssignments as $teacherId => $data): ?>
            <div class="teacher-section">
                <div class="teacher-header">
                    <h2 class="teacher-name"><?= htmlspecialchars($data['teacher_name']) ?></h2>
                    <button onclick="showAssignModal('<?= $teacherId ?>')" class="btn btn-sm">
                        <i class="fas fa-plus"></i>
                        <span class="d-none d-md-inline ml-2">Add Subject</span>
                    </button>
                </div>
                
                <div class="assignments-grid">
                    <?php foreach ($data['assignments'] as $assignment): ?>
                        <div class="assignment-card">
                            <div class="subject-info">
                                <h3 class="subject-name">
                                    <?= htmlspecialchars($assignment['subject_name']) ?>
                                </h3>
                            </div>
                            
                            <div class="class-info">
                                <i class="fas fa-graduation-cap"></i>
                                <?= htmlspecialchars($assignment['class_name']) ?>
                            </div>
                            
                            <div class="score-count">
                                <i class="fas fa-chart-bar"></i>
                                <?= $assignment['score_count'] ?> Scores Recorded
                                
                                <?php if ($assignment['score_count'] == 0): ?>
                                    <form method="post" action="" class="d-inline ml-auto" 
                                          onsubmit="return confirm('Are you sure you want to remove this assignment?')">
                                        <input type="hidden" name="assignment_id" value="<?= $assignment['id'] ?>">
                                        <button type="submit" name="remove_assignment" class="btn btn-sm">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
        
        <?php if (empty($groupedAssignments)): ?>
            <div class="card">
                <p class="text-center mb-0">No assignments found. Click "Add Assignment" to create one.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Assignment Modal -->
    <div class="modal" id="assignmentModal">
        <div class="modal-content">
            <h2 class="mb-4">Add Subject Assignment</h2>
            
            <form method="post" action="" id="assignmentForm" class="d-flex flex-column gap-3">
                <div class="form-group">
                    <label for="teacher_id">Teacher</label>
                    <select id="teacher_id" name="teacher_id" required>
                        <option value="">Select Teacher</option>
                        <?php foreach ($teachers as $teacher): ?>
                            <option value="<?= $teacher['id'] ?>">
                                <?= htmlspecialchars($teacher['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="subject_id">Subject</label>
                    <select id="subject_id" name="subject_id" required>
                        <option value="">Select Subject</option>
                        <?php foreach ($subjects as $subject): ?>
                            <option value="<?= $subject['id'] ?>">
                                <?= htmlspecialchars($subject['subject_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="class_id">Class</label>
                    <select id="class_id" name="class_id" required>
                        <option value="">Select Class</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?= $class['id'] ?>">
                                <?= htmlspecialchars($class['class_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="d-flex gap-2 justify-content-end">
                    <button type="button" onclick="hideModal()" class="btn">Cancel</button>
                    <button type="submit" name="assign_subject" class="btn">Assign Subject</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function showAssignModal(teacherId = '') {
        document.getElementById('teacher_id').value = teacherId;
        if (teacherId) {
            document.getElementById('teacher_id').disabled = true;
        } else {
            document.getElementById('teacher_id').disabled = false;
        }
        document.getElementById('assignmentModal').classList.add('active');
    }
    
    function hideModal() {
        document.getElementById('assignmentModal').classList.remove('active');
        document.getElementById('teacher_id').disabled = false;
        document.getElementById('assignmentForm').reset();
    }
    
    // Close modal when clicking outside
    document.getElementById('assignmentModal').addEventListener('click', function(e) {
        if (e.target === this) {
            hideModal();
        }
    });
    </script>
</body>
</html>
