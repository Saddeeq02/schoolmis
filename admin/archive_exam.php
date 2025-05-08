<?php
include '../includes/auth.php';
include '../includes/db.php';

// Verify admin role
if ($_SESSION['role'] != 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json'); // Set content type before any output
    
    try {
        if (!isset($_POST['exam_id']) || !isset($_POST['action'])) {
            throw new Exception('Missing required parameters');
        }
        
        $exam_id = (int)$_POST['exam_id'];
        $action = $_POST['action']; // 'archive' or 'unarchive'
        
        if (!in_array($action, ['archive', 'unarchive'])) {
            throw new Exception('Invalid action specified');
        }
        
        // Start transaction
        $pdo->beginTransaction();
        
        // Check if exam exists
        $stmt = $pdo->prepare("SELECT title, class_id FROM exams WHERE id = ? AND school_id = ?");
        $stmt->execute([$exam_id, $_SESSION['school_id']]);
        $exam = $stmt->fetch();
        
        if (!$exam) {
            throw new Exception('Exam not found');
        }
        
        // Check if there's already an active exam with same title when unarchiving
        if ($action === 'unarchive') {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM exams 
                WHERE title = ? 
                AND class_id = ? 
                AND archived = 0 
                AND id != ? 
                AND school_id = ?
            ");
            $stmt->execute([$exam['title'], $exam['class_id'], $exam_id, $_SESSION['school_id']]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('An active exam with the same title already exists for this class');
            }
        }
        
        // Update exam archived status
        $stmt = $pdo->prepare("UPDATE exams SET archived = ?, archived_at = ? WHERE id = ? AND school_id = ?");
        $isArchived = ($action === 'archive') ? 1 : 0;
        $archivedAt = ($action === 'archive') ? date('Y-m-d H:i:s') : null;
        $stmt->execute([$isArchived, $archivedAt, $exam_id, $_SESSION['school_id']]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception('Failed to update exam status');
        }
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Exam ' . ($isArchived ? 'archived' : 'unarchived') . ' successfully']);
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

// Get all exams with their archive status
$stmt = $pdo->prepare("
    SELECT e.*, c.class_name, COUNT(DISTINCT s.id) as student_count 
    FROM exams e 
    LEFT JOIN student_scores s ON e.id = s.exam_id 
    JOIN classes c ON e.class_id = c.id
    WHERE e.school_id = ? 
    GROUP BY e.id, e.title, e.session, e.term, e.class_id, e.archived, e.created_at, c.class_name
    ORDER BY e.archived ASC, e.created_at DESC
");
$stmt->execute([$_SESSION['school_id']]);
$exams = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Archive Exams</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/clean-styles.css">
</head>
<body>
    <div class="container mt-4">
        <h2>Manage Exam Archives</h2>
        <p class="text-muted">Archive old exams to keep your active exam list clean. Archived exams can be unarchived at any time.</p>
        
        <div class="mb-3">
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <div class="card">
            <div class="card-body">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Exam Name</th>
                            <th>Date</th>
                            <th>Class</th>
                            <th>Subject</th>
                            <th>Students</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($exams as $exam): ?>
                        <tr class="<?php echo $exam['archived'] ? 'table-secondary' : ''; ?>">
                            <td><?php echo htmlspecialchars($exam['title']); ?></td>
                            <td><?php echo date('Y-m-d', strtotime($exam['created_at'])); ?></td>
                            <td><?php echo htmlspecialchars($exam['class_name']); ?></td>
                            <td>-</td>
                            <td><?php echo $exam['student_count']; ?></td>
                            <td>
                                <span class="badge <?php echo $exam['archived'] ? 'bg-secondary' : 'bg-success'; ?>">
                                    <?php echo $exam['archived'] ? 'Archived' : 'Active'; ?>
                                </span>
                            </td>
                            <td>
                                <button onclick="toggleArchive(<?php echo $exam['id']; ?>, <?php echo $exam['archived']; ?>)"
                                        class="btn btn-sm <?php echo $exam['archived'] ? 'btn-success' : 'btn-secondary'; ?>">
                                    <i class="fas fa-<?php echo $exam['archived'] ? 'box-open' : 'archive'; ?>"></i>
                                    <?php echo $exam['archived'] ? 'Unarchive' : 'Archive'; ?>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
    function toggleArchive(examId, currentlyArchived) {
        const action = currentlyArchived ? 'unarchive' : 'archive';
        
        fetch('archive_exam.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `exam_id=${examId}&action=${action}`
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while processing your request. Please try again.');
        });
    }
    </script>
</body>
</html>