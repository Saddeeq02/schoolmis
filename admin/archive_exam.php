<?php
ob_start(); // Start output buffering at the very beginning

include '../includes/auth.php';
include '../includes/db.php';

// Verify admin role
if ($_SESSION['role'] != 'admin') {
    header('Location: ../login.php');
    exit();
}

// Handle AJAX archive/unarchive request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Clean any output buffers
    ob_clean();
    
    header('Content-Type: application/json');
    
    try {
        if (!isset($_POST['exam_id']) || !isset($_POST['action'])) {
            throw new Exception('Missing required parameters');
        }
        
        $exam_id = (int)$_POST['exam_id'];
        $action = $_POST['action'];
        
        if (!in_array($action, ['archive', 'unarchive'])) {
            throw new Exception('Invalid action specified');
        }
        
        // Start transaction
        $pdo->beginTransaction();
        
        // Check if exam exists and belongs to admin's school
        $stmt = $pdo->prepare("SELECT title, class_id FROM exams WHERE id = ? AND school_id = ?");
        $stmt->execute([$exam_id, $_SESSION['school_id']]);
        $exam = $stmt->fetch();
        
        if (!$exam) {
            throw new Exception('Exam not found or unauthorized access');
        }
        
        // Check if there's already an active exam with same title when unarchiving
        if ($action === 'unarchive') {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM exams 
                WHERE title = ? 
                AND class_id = ? 
                AND (archived = 0 OR archived IS NULL)
                AND id != ? 
                AND school_id = ?
            ");
            $stmt->execute([$exam['title'], $exam['class_id'], $exam_id, $_SESSION['school_id']]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('An active exam with the same title already exists for this class');
            }
        }
        
        // Update exam archived status
        $isArchived = ($action === 'archive') ? 1 : 0;
        $archivedAt = ($action === 'archive') ? date('Y-m-d H:i:s') : null;
        
        $stmt = $pdo->prepare("UPDATE exams SET archived = ?, archived_at = ? WHERE id = ? AND school_id = ?");
        $result = $stmt->execute([$isArchived, $archivedAt, $exam_id, $_SESSION['school_id']]);
        
        if (!$result || $stmt->rowCount() === 0) {
            throw new Exception('Failed to update exam status');
        }
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Exam ' . ($isArchived ? 'archived' : 'unarchived') . ' successfully']);
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Archive exam error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

// Get all exams with their archive status
try {
    $stmt = $pdo->prepare("
        SELECT e.*, 
               c.class_name, 
               COUNT(DISTINCT ss.id) as student_count,
               (SELECT COUNT(*) FROM exam_components WHERE exam_id = e.id) as component_count
        FROM exams e 
        LEFT JOIN classes c ON e.class_id = c.id
        LEFT JOIN students s ON s.class_id = c.id AND s.school_id = e.school_id
        LEFT JOIN student_scores ss ON ss.exam_id = e.id AND ss.student_id = s.id
        WHERE e.school_id = ? 
        GROUP BY e.id
        ORDER BY COALESCE(e.archived, 0) ASC, e.created_at DESC
    ");
    $stmt->execute([$_SESSION['school_id']]);
    $exams = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching exams: " . $e->getMessage());
    $exams = [];
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archive Exams - School MIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/clean-styles.css">

    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        .archived-row {
            background-color: #f8f9fa;
            opacity: 0.8;
        }
        .archive-btn {
            transition: all 0.3s ease;
        }
        .archive-btn:hover {
            transform: scale(1.05);
        }
        .status-badge {
            font-size: 0.875rem;
            padding: 0.25rem 0.75rem;
        }
        .loading {
            display: none;
            margin-left: 10px;
        }
        .alert-stack {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
            max-width: 400px;
        }
    </style>
</head>
<body>
    <!-- Alert Stack -->
    <div class="alert-stack" id="alertStack"></div>

    <div class="container mt-4">
        <div class="card mb-4">
            <div class="card-body">
                <h2 class="mb-0">Manage Exam Archives</h2>
                <p class="text-muted mb-0">Archive old exams to keep your active exam list clean. Archived exams can be unarchived at any time.</p>
            </div>
        </div>
        
        <div class="mb-3">
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
            <a href="exams_list.php" class="btn btn-primary ms-2">
                <i class="fas fa-list"></i> View Exams
            </a>
        </div>

        <div class="card">
            <div class="card-body">
                <?php if (empty($exams)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-folder-open fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No exams found</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Exam</th>
                                    <th>Session</th>
                                    <th>Term</th>
                                    <th>Class</th>
                                    <th>Components</th>
                                    <th>Scores</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($exams as $exam): ?>
                                <tr class="<?php echo ($exam['archived'] ?? 0) ? 'archived-row' : ''; ?>" id="exam-row-<?php echo $exam['id']; ?>">
                                    <td>
                                        <strong><?php echo htmlspecialchars($exam['title']); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($exam['session']); ?></td>
                                    <td>
                                        <?php 
                                        $terms = ['', 'First', 'Second', 'Third'];
                                        echo $terms[$exam['term']] ?? $exam['term'];
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($exam['class_name']); ?></td>
                                    <td>
                                        <span class="badge bg-info"><?php echo $exam['component_count']; ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary"><?php echo $exam['student_count']; ?></span>
                                    </td>
                                    <td>
                                        <span class="badge status-badge <?php echo ($exam['archived'] ?? 0) ? 'bg-secondary' : 'bg-success'; ?>">
                                            <?php echo ($exam['archived'] ?? 0) ? 'Archived' : 'Active'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo date('M d, Y', strtotime($exam['created_at'])); ?>
                                        <?php if ($exam['archived_at']): ?>
                                            <br><small class="text-muted">Archived: <?php echo date('M d, Y', strtotime($exam['archived_at'])); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button onclick="toggleArchive(<?php echo $exam['id']; ?>, <?php echo ($exam['archived'] ?? 0) ? 'true' : 'false'; ?>)"
                                                class="btn btn-sm archive-btn <?php echo ($exam['archived'] ?? 0) ? 'btn-success' : 'btn-secondary'; ?>"
                                                id="btn-<?php echo $exam['id']; ?>">
                                            <i class="fas fa-<?php echo ($exam['archived'] ?? 0) ? 'box-open' : 'archive'; ?>"></i>
                                            <?php echo ($exam['archived'] ?? 0) ? 'Unarchive' : 'Archive'; ?>
                                        </button>
                                        <span class="loading spinner-border spinner-border-sm" id="loading-<?php echo $exam['id']; ?>"></span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="../assets/clean-styles.css">

    <script>
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

    function toggleArchive(examId, currentlyArchived) {
        const action = currentlyArchived ? 'unarchive' : 'archive';
        const confirmMessage = currentlyArchived ? 
            'Are you sure you want to unarchive this exam?' : 
            'Are you sure you want to archive this exam?';
        
        if (!confirm(confirmMessage)) {
            return;
        }
        
        // Show loading
        const button = document.getElementById(`btn-${examId}`);
        const loading = document.getElementById(`loading-${examId}`);
        button.disabled = true;
        loading.style.display = 'inline-block';
        
        // Create form data
        const formData = new FormData();
        formData.append('exam_id', examId);
        formData.append('action', action);
        
        fetch('archive_exam.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                showAlert(data.message, 'success');
                // Reload the page after a short delay
                setTimeout(() => {
                    location.reload();
                }, 1000);
            } else {
                showAlert('Error: ' + data.message, 'danger');
                button.disabled = false;
                loading.style.display = 'none';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('An error occurred while processing your request. Please try again.', 'danger');
            button.disabled = false;
            loading.style.display = 'none';
        });
    }
    </script>
</body>
</html>
<?php ob_end_flush(); ?>