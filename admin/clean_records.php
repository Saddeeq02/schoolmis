<?php
include '../includes/auth.php';
include '../includes/db.php';

// Verify admin role
if ($_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        $action = $_POST['action'];
        $confirmation = $_POST['confirmation'] ?? '';
        
        if ($confirmation !== 'CONFIRM') {
            throw new Exception('Please type CONFIRM to proceed with the deletion.');
        }
        
        switch ($action) {
            case 'archived_exams':
                // Delete archived exams and their scores
                $stmt = $pdo->prepare("DELETE e, s FROM exams e 
                                     LEFT JOIN student_scores s ON e.id = s.exam_id 
                                     WHERE e.archived = 1 AND e.school_id = ?");
                $stmt->execute([$_SESSION['school_id']]);
                $message = "Archived exams and their scores deleted successfully.";
                break;
                
            case 'exam_scores':
                // Delete scores for archived exams
                $stmt = $pdo->prepare("DELETE s FROM student_scores s 
                                     INNER JOIN exams e ON s.exam_id = e.id 
                                     WHERE e.archived = 1 AND e.school_id = ?");
                $stmt->execute([$_SESSION['school_id']]);
                $message = "Scores for archived exams deleted successfully.";
                break;
                
            case 'graduated_students':
                // Delete graduated students using class_id
                $class_id = $_POST['class_id'];
                $stmt = $pdo->prepare("DELETE s FROM students s 
                                     WHERE s.class_id = ? AND s.school_id = ?");
                $stmt->execute([$class_id, $_SESSION['school_id']]);
                $message = "Students from selected class deleted successfully.";
                break;
                
            default:
                throw new Exception('Invalid action specified.');
        }
        
        $pdo->commit();
        $success_message = $message;
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = $e->getMessage();
    }
}

// Get classes for graduated students deletion
$stmt = $pdo->prepare("
    SELECT DISTINCT c.id, c.class_name 
    FROM classes c
    WHERE c.school_id = ? 
    ORDER BY c.class_name
");
$stmt->execute([$_SESSION['school_id']]);
$classes = $stmt->fetchAll();

// Get counts for various record types
$archived_exams_count = $pdo->prepare("SELECT COUNT(*) FROM exams WHERE archived = 1 AND school_id = ?");
$archived_exams_count->execute([$_SESSION['school_id']]);
$archived_count = $archived_exams_count->fetchColumn();

$archived_scores_count = $pdo->prepare("SELECT COUNT(s.id) FROM student_scores s 
                                      INNER JOIN exams e ON s.exam_id = e.id 
                                      WHERE e.archived = 1 AND e.school_id = ?");
$archived_scores_count->execute([$_SESSION['school_id']]);
$scores_count = $archived_scores_count->fetchColumn();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Clean Records</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/clean-styles.css">

</head>
<body>
    <div class="container mt-4">
        <h2>Clean School Records</h2>
        <p class="text-muted">Permanently delete old or unnecessary records. This action cannot be undone.</p>
        
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

        <div class="row">
            <!-- Delete Archived Exams -->
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">Delete Archived Exams</h5>
                        <p class="card-text">
                            Delete all archived exams and their associated scores permanently.
                            <br>
                            <strong>Found: <?php echo $archived_count; ?> archived exams</strong>
                        </p>
                        <form method="post" class="cleanup-form" data-action="archived_exams">
                            <input type="hidden" name="action" value="archived_exams">
                            <div class="mb-3">
                                <label class="form-label">Type CONFIRM to proceed:</label>
                                <input type="text" name="confirmation" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-trash"></i> Delete Archived Exams
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Delete Archived Exam Scores -->
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">Delete Archived Exam Scores</h5>
                        <p class="card-text">
                            Delete only the scores from archived exams, keeping the exam records.
                            <br>
                            <strong>Found: <?php echo $scores_count; ?> scores from archived exams</strong>
                        </p>
                        <form method="post" class="cleanup-form" data-action="exam_scores">
                            <input type="hidden" name="action" value="exam_scores">
                            <div class="mb-3">
                                <label class="form-label">Type CONFIRM to proceed:</label>
                                <input type="text" name="confirmation" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-trash"></i> Delete Archived Scores
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Delete Graduated Students -->
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">Delete Class Records</h5>
                        <p class="card-text">
                            Delete all student records from a specific class.
                            <br>
                            <strong>Warning: This will permanently delete all selected student records!</strong>
                        </p>
                        <form method="post" class="cleanup-form" data-action="graduated_students">
                            <input type="hidden" name="action" value="graduated_students">
                            <div class="mb-3">
                                <label class="form-label">Select Class:</label>
                                <select name="class_id" class="form-select" required>
                                    <option value="">Choose class...</option>
                                    <?php foreach ($classes as $class): ?>
                                        <option value="<?php echo htmlspecialchars($class['id']); ?>">
                                            <?php echo htmlspecialchars($class['class_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Type CONFIRM to proceed:</label>
                                <input type="text" name="confirmation" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-trash"></i> Delete Class Records
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Form submission confirmation
        document.querySelectorAll('.cleanup-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const action = this.dataset.action;
                let message = 'Are you sure you want to proceed? This action cannot be undone!';
                
                if (action === 'graduated_students') {
                    const className = this.querySelector('select[name="class_id"] option:checked').textContent;
                    message = `Are you sure you want to delete ALL student records from class ${className}? This action cannot be undone!`;
                }
                
                if (!confirm(message)) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>