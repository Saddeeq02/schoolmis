<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/db.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php');
    exit;
}

$examId = $_GET['id'] ?? ''; // Changed from exam_id to id to match URL parameter
$error = '';
$success = '';

if (empty($examId)) {
    header('Location: exams_list.php');
    exit;
}

// Get exam details
$stmt = $pdo->prepare("SELECT * FROM exams WHERE id = ?");
$stmt->execute([$examId]);
$exam = $stmt->fetch();

if (!$exam) {
    header('Location: exams_list.php');
    exit;
}

// Handle component deletion
if (isset($_POST['delete_component'])) {
    $componentId = $_POST['component_id'];
    
    // Check if scores exist for this component
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM student_scores WHERE component_id = ?");
    $stmt->execute([$componentId]);
    $hasScores = $stmt->fetchColumn() > 0;
    
    if ($hasScores) {
        $error = 'Cannot delete component. Scores have already been recorded.';
    } else {
        $stmt = $pdo->prepare("DELETE FROM exam_components WHERE id = ? AND exam_id = ?");
        $stmt->execute([$componentId, $examId]);
        $success = 'Component deleted successfully.';
    }
}

// Handle component addition/update
if (isset($_POST['save_component'])) {
    $componentId = $_POST['component_id'] ?? null;
    $name = trim($_POST['name']);
    $maxMarks = floatval($_POST['max_marks']);
    $displayOrder = intval($_POST['display_order']);
    $isEnabled = 1; // Default to enabled
    
    if (empty($name)) {
        $error = 'Component name is required.';
    } elseif ($maxMarks <= 0) {
        $error = 'Maximum marks must be greater than zero.';
    } else {
        try {
            if ($componentId) {
                // Update existing component
                $stmt = $pdo->prepare("
                    UPDATE exam_components 
                    SET name = ?, max_marks = ?, display_order = ?, is_enabled = ?
                    WHERE id = ? AND exam_id = ?
                ");
                $stmt->execute([$name, $maxMarks, $displayOrder, $isEnabled, $componentId, $examId]);
            } else {
                // Add new component
                $stmt = $pdo->prepare("
                    INSERT INTO exam_components (exam_id, name, max_marks, display_order, is_enabled) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$examId, $name, $maxMarks, $displayOrder, $isEnabled]);
            }
            $success = 'Component saved successfully.';
        } catch (PDOException $e) {
            $error = 'Error saving component. Please try again.';
        }
    }
}

// Get existing components
$stmt = $pdo->prepare("
    SELECT c.*, 
           (SELECT COUNT(*) FROM student_scores s WHERE s.component_id = c.id) as score_count
    FROM exam_components c 
    WHERE c.exam_id = ? 
    ORDER BY c.display_order
");
$stmt->execute([$examId]);
$components = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Components - School MIS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/clean-styles.css">
    <style>
        .component-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: var(--spacing-md);
            margin-bottom: var(--spacing-lg);
        }
        
        .component-card {
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: var(--spacing-md);
            background: var(--white);
        }
        
        .component-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--spacing-md);
        }
        
        .component-title {
            font-weight: 600;
            color: var(--text);
            margin: 0;
        }
        
        .component-actions {
            display: flex;
            gap: var(--spacing-sm);
        }
        
        .component-details {
            display: flex;
            flex-direction: column;
            gap: var(--spacing-xs);
        }
        
        .detail-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: var(--spacing-xs) 0;
            border-bottom: 1px solid var(--border);
        }
        
        .detail-label {
            color: var(--text-light);
            font-size: 0.875rem;
        }
        
        .detail-value {
            font-weight: 500;
        }
        
        .score-warning {
            color: var(--warning);
            font-size: 0.875rem;
            margin-top: var(--spacing-xs);
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
            .component-grid {
                grid-template-columns: 1fr;
            }
            
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
                <div>
                    <h1 class="mb-0">Exam Components</h1>
                    <p class="text-light mb-0"><?= htmlspecialchars($exam['title']) ?></p>
                </div>
                <div class="d-flex gap-2">
                    <button onclick="showAddModal()" class="btn">
                        <i class="fas fa-plus"></i>
                        <span class="d-none d-md-inline ml-2">Add Component</span>
                    </button>
                    <a href="exams_list.php" class="btn">
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

        <!-- Components Grid -->
        <div class="component-grid">
            <?php foreach ($components as $component): ?>
                <div class="component-card">
                    <div class="component-header">
                        <h3 class="component-title"><?= htmlspecialchars($component['name']) ?></h3>
                        <div class="component-actions">
                            <button onclick="showEditModal(<?= htmlspecialchars(json_encode($component)) ?>)" 
                                    class="btn btn-sm">
                                <i class="fas fa-edit"></i>
                            </button>
                            <?php if ($component['score_count'] == 0): ?>
                                <form method="post" action="" class="d-inline" 
                                      onsubmit="return confirm('Are you sure you want to delete this component?')">
                                    <input type="hidden" name="component_id" value="<?= $component['id'] ?>">
                                    <button type="submit" name="delete_component" class="btn btn-sm">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="component-details">
                        <div class="detail-item">
                            <span class="detail-label">Maximum Marks</span>
                            <span class="detail-value"><?= number_format($component['max_marks'], 2) ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Display Order</span>
                            <span class="detail-value"><?= $component['display_order'] ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Scores Recorded</span>
                            <span class="detail-value"><?= $component['score_count'] ?></span>
                        </div>
                    </div>
                    
                    <?php if ($component['score_count'] > 0): ?>
                        <div class="score-warning">
                            <i class="fas fa-info-circle"></i>
                            Cannot delete - scores exist
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <?php if (empty($components)): ?>
            <div class="card">
                <p class="text-center mb-0">No components found. Click "Add Component" to create one.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Add/Edit Modal -->
    <div class="modal" id="componentModal">
        <div class="modal-content">
            <h2 class="mb-4" id="modalTitle">Add Component</h2>
            
            <form method="post" action="" id="componentForm" class="d-flex flex-column gap-3">
                <input type="hidden" name="component_id" id="componentId">
                
                <div class="form-group">
                    <label for="name">Component Name</label>
                    <input type="text" 
                           id="name" 
                           name="name" 
                           required 
                           placeholder="e.g., Written Test, Practical, Assignment">
                </div>
                
                <div class="form-group">
                    <label for="max_marks">Maximum Marks</label>
                    <input type="number" 
                           id="max_marks" 
                           name="max_marks" 
                           required 
                           min="0.01" 
                           step="0.01"
                           placeholder="Enter maximum marks">
                </div>
                
                <div class="form-group">
                    <label for="display_order">Display Order</label>
                    <input type="number" 
                           id="display_order" 
                           name="display_order" 
                           required 
                           min="1" 
                           step="1"
                           value="<?= count($components) + 1 ?>">
                </div>
                
                <div class="d-flex gap-2 justify-content-end">
                    <button type="button" onclick="hideModal()" class="btn">Cancel</button>
                    <button type="submit" name="save_component" class="btn">Save Component</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function showAddModal() {
        document.getElementById('modalTitle').textContent = 'Add Component';
        document.getElementById('componentId').value = '';
        document.getElementById('componentForm').reset();
        document.getElementById('componentModal').classList.add('active');
    }
    
    function showEditModal(component) {
        document.getElementById('modalTitle').textContent = 'Edit Component';
        document.getElementById('componentId').value = component.id;
        document.getElementById('name').value = component.name;
        document.getElementById('max_marks').value = component.max_marks;
        document.getElementById('display_order').value = component.display_order;
        document.getElementById('componentModal').classList.add('active');
    }
    
    function hideModal() {
        document.getElementById('componentModal').classList.remove('active');
    }
    
    // Close modal when clicking outside
    document.getElementById('componentModal').addEventListener('click', function(e) {
        if (e.target === this) {
            hideModal();
        }
    });
    </script>
</body>
</html>
