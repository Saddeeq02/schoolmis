<?php
include '../includes/auth.php';
include '../includes/db.php';

// Verify admin role
if ($_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        $student_ids = $_POST['student_ids'] ?? [];
        $target_class = $_POST['target_class'];
        
        // Update each student's class
        $stmt = $pdo->prepare("UPDATE students SET class_id = ? WHERE id = ? AND school_id = ?");
        foreach ($student_ids as $student_id) {
            $stmt->execute([$target_class, $student_id, $_SESSION['school_id']]);
        }
        
        $pdo->commit();
        $success_message = count($student_ids) . " students promoted successfully!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = $e->getMessage();
    }
}

// Get all classes
$stmt = $pdo->prepare("SELECT id, class_name FROM classes WHERE school_id = ? ORDER BY class_name");
$stmt->execute([$_SESSION['school_id']]);
$classes = $stmt->fetchAll();

// Get selected class's students
$current_class = $_GET['class'] ?? ($classes[0]['id'] ?? '');
$stmt = $pdo->prepare("
    SELECT s.*, c.class_name 
    FROM students s
    JOIN classes c ON s.class_id = c.id 
    WHERE s.school_id = ? AND s.class_id = ? 
    ORDER BY s.name
");
$stmt->execute([$_SESSION['school_id'], $current_class]);
$students = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Student Promotion</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/clean-styles.css">

</head>
<body>
    <div class="container mt-4">
        <h2>Student Promotion Management</h2>
        <p class="text-muted">Promote/migrate students from one class to another.</p>
        
        <div class="mb-3">
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <!-- Class selector -->
                <form method="get" class="mb-4">
                    <div class="row align-items-end">
                        <div class="col-md-6">
                            <label class="form-label">Select Current Class:</label>
                            <select name="class" class="form-select" onchange="this.form.submit()">
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?php echo htmlspecialchars($class['id']); ?>" 
                                            <?php echo $class['id'] === $current_class ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($class['class_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </form>

                <!-- Student promotion form -->
                <form method="post" id="promotionForm">
                    <div class="mb-3">
                        <label class="form-label">Select Target Class:</label>
                        <select name="target_class" class="form-select" required>
                            <option value="">Choose target class...</option>
                            <?php foreach ($classes as $class): ?>
                                <?php if ($class['id'] !== $current_class): ?>
                                    <option value="<?php echo htmlspecialchars($class['id']); ?>">
                                        <?php echo htmlspecialchars($class['class_name']); ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>
                                        <input type="checkbox" id="selectAll" class="form-check-input">
                                    </th>
                                    <th>Student Name</th>
                                    <th>Admission Number</th>
                                    <th>Current Class</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $student): ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" name="student_ids[]" 
                                                   value="<?php echo $student['id']; ?>" 
                                                   class="form-check-input student-checkbox">
                                        </td>
                                        <td><?php echo htmlspecialchars($student['name']); ?></td>
                                        <td><?php echo htmlspecialchars($student['admission_number']); ?></td>
                                        <td><?php echo htmlspecialchars($student['class_name']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3">
                        <button type="submit" class="btn btn-primary" id="promoteBtn" disabled>
                            <i class="fas fa-arrow-right"></i> Promote Selected Students
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Select all functionality
        document.getElementById('selectAll').addEventListener('change', function() {
            const checkboxes = document.getElementsByClassName('student-checkbox');
            for (let checkbox of checkboxes) {
                checkbox.checked = this.checked;
            }
            updatePromoteButton();
        });

        // Individual checkbox change handler
        document.querySelectorAll('.student-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', updatePromoteButton);
        });

        // Update promote button state
        function updatePromoteButton() {
            const checkedBoxes = document.querySelectorAll('.student-checkbox:checked');
            document.getElementById('promoteBtn').disabled = checkedBoxes.length === 0;
        }

        // Form submission confirmation
        document.getElementById('promotionForm').addEventListener('submit', function(e) {
            const checkedCount = document.querySelectorAll('.student-checkbox:checked').length;
            const targetClass = document.querySelector('select[name="target_class"]').value;
            
            if (!confirm(`Are you sure you want to promote ${checkedCount} student(s) to ${targetClass}?`)) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>