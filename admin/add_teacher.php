<?php
// Add output buffering at the very start
ob_start();

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Ensure session is started properly
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is an admin
if (!isLoggedIn() || !isAdmin()) {
    ob_end_clean();
    header('Location: ../login.php');
    exit;
}

$currentSchoolId = $_SESSION['school_id'] ?? 1;
$errors = [];
$success = false;

// Get all schools
$schoolsStmt = $pdo->query("SELECT id, name FROM schools ORDER BY name");
$schools = $schoolsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get classes for current school
$classesStmt = $pdo->prepare("SELECT id, class_name FROM classes WHERE school_id = ? ORDER BY class_name");
$classesStmt->execute([$currentSchoolId]);
$classes = $classesStmt->fetchAll(PDO::FETCH_ASSOC);

// Get subjects for current school
$subjectsStmt = $pdo->prepare("SELECT id, subject_name FROM subjects WHERE school_id = ? ORDER BY subject_name");
$subjectsStmt->execute([$currentSchoolId]);
$subjects = $subjectsStmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate inputs
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $schoolId = (int)($_POST['school_id'] ?? 0);
    $assignments = $_POST['assignments'] ?? [];
    
    // Validation
    if (empty($name)) {
        $errors[] = 'Teacher name is required';
    }
    
    if (empty($email)) {
        $errors[] = 'Email address is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address';
    }
    
    if (empty($password)) {
        $errors[] = 'Password is required';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters long';
    }
    
    if ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match';
    }
    
    if ($schoolId <= 0) {
        $errors[] = 'Please select a school';
    }
    
    // Check if email already exists
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = 'Email address is already registered';
        }
    }
    
    // If no errors, create the teacher
    if (empty($errors)) {
        try {
            // Start transaction
            $pdo->beginTransaction();
            
            // Insert teacher
            $stmt = $pdo->prepare("
                INSERT INTO users (name, email, password, role, school_id) 
                VALUES (?, ?, ?, 'teacher', ?)
            ");
            $stmt->execute([
                $name,
                $email,
                password_hash($password, PASSWORD_DEFAULT),
                $schoolId
            ]);
            
            $teacherId = $pdo->lastInsertId();
            
            // Process assignments
            if (!empty($assignments)) {
                $stmt = $pdo->prepare("INSERT INTO teacher_subjects (teacher_id, subject_id, class_id) VALUES (?, ?, ?)");
                
                foreach ($assignments as $assignment) {
                    $parts = explode('_', $assignment);
                    if (count($parts) == 2) {
                        $subjectId = $parts[0];
                        $classId = $parts[1];
                        $stmt->execute([$teacherId, $subjectId, $classId]);
                    }
                }
            }
            
            $pdo->commit();
            
            $_SESSION['message'] = 'Teacher created successfully';
            $_SESSION['message_type'] = 'success';
            
            // Clear form data
            $_POST = [];
            $success = true;
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = 'Error creating teacher: ' . $e->getMessage();
            error_log('Database error: ' . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Teacher - School MIS</title>
    <link rel="stylesheet" href="../assets/clean-styles.css">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .password-field {
            position: relative;
        }
        .toggle-password {
            position: absolute;
            right: 10px;
            top: 38%;
            background: none;
            border: none;
            color: #6c757d;
            cursor: pointer;
            padding: 5px;
        }
        .toggle-password:hover {
            color: #0d6efd;
        }
        .assignment-table {
            max-height: 400px;
            overflow-y: auto;
        }
        .assignment-table thead {
            position: sticky;
            top: 0;
            background: white;
            z-index: 10;
        }
        .subject-header {
            background-color: #f8f9fa;
            font-weight: bold;
            padding: 10px;
            margin-top: 10px;
            margin-bottom: 5px;
            border-radius: 5px;
        }
        .class-row {
            padding: 5px 10px;
        }
        .select-all-btn {
            font-size: 0.875rem;
            padding: 0.25rem 0.5rem;
        }
    </style>
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
                            <a class="nav-link active" href="teachers_list.php">
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
                    <h1 class="h2">Add New Teacher</h1>
                    <a href="teachers_list.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Teachers
                    </a>
                </div>

                <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible fade show" role="alert">
                    <?php 
                    echo $_SESSION['message']; 
                    unset($_SESSION['message']);
                    unset($_SESSION['message_type']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    Teacher account created successfully!
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <form method="post" action="">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">Teacher Information</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="name" class="form-label">Full Name</label>
                                            <input type="text" class="form-control" id="name" name="name" 
                                                   value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="email" class="form-label">Email Address</label>
                                            <input type="email" class="form-control" id="email" name="email" 
                                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="password" class="form-label">Password</label>
                                            <div class="password-field">
                                                <input type="password" class="form-control" id="password" name="password" 
                                                       required minlength="6">
                                                <button type="button" class="toggle-password" onclick="togglePassword('password')">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </div>
                                            <small class="text-muted">Must be at least 6 characters long</small>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="confirm_password" class="form-label">Confirm Password</label>
                                            <div class="password-field">
                                                <input type="password" class="form-control" id="confirm_password" 
                                                       name="confirm_password" required minlength="6">
                                                <button type="button" class="toggle-password" onclick="togglePassword('confirm_password')">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="school_id" class="form-label">School</label>
                                        <select class="form-select" id="school_id" name="school_id" required>
                                            <option value="">Select School</option>
                                            <?php foreach ($schools as $school): ?>
                                            <option value="<?php echo $school['id']; ?>" 
                                                    <?php echo ($school['id'] == $currentSchoolId) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($school['name']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">Subject & Class Assignments (Optional)</h5>
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="selectAll()">
                                        Select All
                                    </button>
                                </div>
                                <div class="card-body">
                                    <div class="assignment-table">
                                        <?php foreach ($subjects as $subject): ?>
                                            <div class="subject-header">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span><?php echo htmlspecialchars($subject['subject_name']); ?></span>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary select-all-btn" 
                                                            onclick="selectAllForSubject(<?php echo $subject['id']; ?>)">
                                                        Select All Classes
                                                    </button>
                                                </div>
                                            </div>
                                            <?php foreach ($classes as $class): ?>
                                                <div class="class-row">
                                                    <div class="form-check">
                                                        <input class="form-check-input assignment-checkbox" 
                                                               type="checkbox" 
                                                               name="assignments[]" 
                                                               value="<?php echo $subject['id'] . '_' . $class['id']; ?>"
                                                               id="assignment_<?php echo $subject['id'] . '_' . $class['id']; ?>"
                                                               data-subject="<?php echo $subject['id']; ?>"
                                                               data-class="<?php echo $class['id']; ?>">
                                                        <label class="form-check-label" for="assignment_<?php echo $subject['id'] . '_' . $class['id']; ?>">
                                                            <?php echo htmlspecialchars($class['class_name']); ?>
                                                        </label>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-user-plus"></i> Create Teacher Account
                                </button>
                                <a href="teachers_list.php" class="btn btn-secondary">Cancel</a>
                            </div>
                        </div>
                    </div>
                </form>
            </main>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    function togglePassword(inputId) {
        const passwordInput = document.getElementById(inputId);
        const toggleBtn = passwordInput.nextElementSibling.querySelector('i');
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            toggleBtn.classList.remove('fa-eye');
            toggleBtn.classList.add('fa-eye-slash');
        } else {
            passwordInput.type = 'password';
            toggleBtn.classList.remove('fa-eye-slash');
            toggleBtn.classList.add('fa-eye');
        }
    }

    function selectAll() {
        const checkboxes = document.querySelectorAll('.assignment-checkbox');
        const allChecked = Array.from(checkboxes).every(cb => cb.checked);
        
        checkboxes.forEach(checkbox => {
            checkbox.checked = !allChecked;
        });
    }

    function selectAllForSubject(subjectId) {
        const checkboxes = document.querySelectorAll(`.assignment-checkbox[data-subject="${subjectId}"]`);
        const allChecked = Array.from(checkboxes).every(cb => cb.checked);
        
        checkboxes.forEach(checkbox => {
            checkbox.checked = !allChecked;
        });
    }

    // When school changes, update the assignments
    document.getElementById('school_id').addEventListener('change', function() {
        const schoolId = this.value;
        if (schoolId) {
            // In a real implementation, you would fetch classes and subjects for the selected school
            // For now, show an alert
            alert('Note: Classes and subjects shown are for the current school. Please reload the page after changing school.');
        }
    });
    </script>
</body>
</html>
<?php ob_end_flush(); ?>