<?php
session_start();
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

// Fetch teachers with school information and filter by school if selected
try {
    $query = "
        SELECT u.*, s.name as school_name 
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
    <link rel="stylesheet" href="../assets/clean-styles.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
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
    </style>
</head>
<body>
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
                </div>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
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
                            </div>
                            <div class="d-flex gap-2">
                                <button onclick="showAssignSchool(<?= htmlspecialchars(json_encode($teacher)) ?>)" 
                                        class="btn btn-sm">
                                    <i class="fas fa-building"></i>
                                    <span class="d-none d-md-inline">Assign School</span>
                                </button>
                                <a href="teacher_subjects.php?teacher_id=<?= $teacher['id'] ?>" class="btn btn-sm">
                                    <i class="fas fa-book"></i>
                                    <span class="d-none d-md-inline">Subjects</span>
                                </a>
                                <a href="view_recordings.php?teacher_id=<?= $teacher['id'] ?>" class="btn btn-sm">
                                    <i class="fas fa-video"></i>
                                    <span class="d-none d-md-inline">Recordings</span>
                                </a>
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
                    <button type="button" onclick="hideModal()" class="btn">Cancel</button>
                    <button type="submit" name="assign_school" class="btn btn-primary">Assign School</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showAssignSchool(teacher) {
            document.getElementById('teacherId').value = teacher.id;
            document.getElementById('teacherName').value = teacher.name;
            document.getElementById('new_school_id').value = teacher.school_id || '';
            document.getElementById('assignSchoolModal').classList.add('active');
        }
        
        function hideModal() {
            document.getElementById('assignSchoolModal').classList.remove('active');
        }
        
        // Close modal when clicking outside
        document.getElementById('assignSchoolModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideModal();
            }
        });
    </script>
</body>
</html>
