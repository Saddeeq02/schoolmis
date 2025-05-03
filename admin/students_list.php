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

$schoolId = $_SESSION['school_id'] ?? 1;

// Pagination settings
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Get all classes for the dropdown for the current school
$classesStmt = $pdo->prepare("SELECT id, class_name FROM classes WHERE school_id = ? ORDER BY class_name");
$classesStmt->execute([$schoolId]);
$classes = $classesStmt->fetchAll();

// Handle class filter
$selectedClassId = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;

// Get total students count for pagination based on filters
if ($selectedClassId > 0) {
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE school_id = ? AND class_id = ?");
    $countStmt->execute([$schoolId, $selectedClassId]);
} else {
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE school_id = ?");
    $countStmt->execute([$schoolId]);
}
$totalStudents = $countStmt->fetchColumn();
$totalPages = ceil($totalStudents / $perPage);

// Get students based on filter with pagination
if ($selectedClassId > 0) {
    $stmt = $pdo->prepare("
        SELECT s.*, c.class_name 
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.id
        WHERE s.school_id = ? AND s.class_id = ?
        ORDER BY s.name
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$schoolId, $selectedClassId, $perPage, $offset]);
} else {
    $stmt = $pdo->prepare("
        SELECT s.*, c.class_name 
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.id
        WHERE s.school_id = ?
        ORDER BY s.name
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$schoolId, $perPage, $offset]);
}
$students = $stmt->fetchAll();

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
    <title>Students List - School MIS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/clean-styles.css">
    <style>
        .students-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: var(--spacing-md);
            margin-bottom: var(--spacing-lg);
        }
        
        .student-card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: var(--spacing-md);
        }
        
        .student-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--spacing-md);
        }
        
        .student-info {
            margin-bottom: var(--spacing-sm);
        }
        
        .student-actions {
            display: flex;
            gap: var(--spacing-sm);
        }
        
        .filter-bar {
            display: flex;
            gap: var(--spacing-md);
            margin-bottom: var(--spacing-lg);
            flex-wrap: wrap;
        }
        
        @media (max-width: 768px) {
            .filter-bar {
                flex-direction: column;
            }
            
            .student-header {
                flex-direction: column;
                align-items: flex-start;
                gap: var(--spacing-sm);
            }
            
            .student-actions {
                width: 100%;
                justify-content: flex-end;
            }
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: var(--spacing-sm);
            margin-top: var(--spacing-lg);
        }
        
        .page-link {
            padding: var(--spacing-sm) var(--spacing-md);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            color: var(--text);
            text-decoration: none;
        }
        
        .page-link.active {
            background: var(--primary);
            color: var(--white);
            border-color: var(--primary);
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
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
            .modal-content {
                margin: var(--spacing-sm);
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="card mb-4">
            <div class="d-flex justify-content-between align-items-center">
                <h1 class="mb-0">Students</h1>
                <div class="d-flex gap-2">
                    <a href="add_student.php" class="btn">
                        <i class="fas fa-plus"></i>
                        <span class="d-none d-md-inline ml-2">Add Student</span>
                    </a>
                    <a href="dashboard.php" class="btn">
                        <i class="fas fa-arrow-left"></i>
                        <span class="d-none d-md-inline ml-2">Back</span>
                    </a>
                </div>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType === 'error' ? 'danger' : 'success'; ?> mb-4">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <form method="get" class="d-flex gap-2 flex-grow-1">
                <select name="class_id" class="flex-grow-1">
                    <option value="0">All Classes</option>
                    <?php foreach ($classes as $class): ?>
                        <option value="<?php echo $class['id']; ?>" <?php echo $selectedClassId == $class['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($class['class_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn">Filter</button>
            </form>
        </div>

        <!-- Students Grid -->
        <div class="students-grid">
            <?php if ($students && count($students) > 0): ?>
                <?php foreach ($students as $student): ?>
                    <div class="student-card">
                        <div class="student-header">
                            <div>
                                <h3 class="mb-0"><?php echo htmlspecialchars($student['name']); ?></h3>
                                <small class="text-light"><?php echo htmlspecialchars($student['admission_number']); ?></small>
                            </div>
                            <div class="student-actions">
                                <a href="edit_student.php?id=<?php echo $student['id']; ?>" class="btn btn-sm">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button onclick="confirmDelete(<?php echo $student['id']; ?>, '<?php echo htmlspecialchars($student['name']); ?>')" 
                                        class="btn btn-sm">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                        <div class="student-info">
                            <div class="text-light">
                                <i class="fas fa-graduation-cap"></i>
                                Class: <?php echo htmlspecialchars($student['class_name'] ?? 'Not Assigned'); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="card text-center">
                    <p class="mb-0">No students found</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo ($page - 1); ?>&class_id=<?php echo $selectedClassId; ?>" class="page-link">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>&class_id=<?php echo $selectedClassId; ?>" 
                       class="page-link <?php echo $page === $i ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?php echo ($page + 1); ?>&class_id=<?php echo $selectedClassId; ?>" class="page-link">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Delete Confirmation Modal -->
        <div class="modal" id="deleteModal" aria-hidden="true">
            <div class="modal-content">
                <h2 class="mb-4">Confirm Delete</h2>
                <p>Are you sure you want to delete <strong id="studentName"></strong>?</p>
                <div class="d-flex gap-2 justify-content-end">
                    <button type="button" onclick="hideModal()" class="btn">Cancel</button>
                    <form id="deleteForm" method="post" action="delete_student.php" class="d-inline">
                        <input type="hidden" name="student_id" id="studentIdInput">
                        <button type="submit" class="btn btn-danger">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function confirmDelete(id, name) {
            document.getElementById('studentName').textContent = name;
            document.getElementById('studentIdInput').value = id;
            document.getElementById('deleteModal').classList.add('active');
        }
        
        function hideModal() {
            document.getElementById('deleteModal').classList.remove('active');
        }
        
        // Close modal when clicking outside
        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideModal();
            }
        });

        // Prevent modal from closing when clicking inside modal content
        document.querySelector('.modal-content').addEventListener('click', function(e) {
            e.stopPropagation();
        });
    </script>
</body>
</html>
