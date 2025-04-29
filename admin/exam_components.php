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

// Check if exam ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['message'] = 'Exam ID is required';
    $_SESSION['message_type'] = 'error';
    header('Location: exams_list.php');
    exit;
}

$examId = (int)$_GET['id'];
$exam = getExamById($pdo, $examId);

// If exam not found, redirect back to list
if (!$exam) {
    $_SESSION['message'] = 'Exam not found';
    $_SESSION['message_type'] = 'error';
    header('Location: exams_list.php');
    exit;
}

// Get the class name
$classStmt = $pdo->prepare("SELECT class_name FROM classes WHERE id = :id");
$classStmt->bindParam(':id', $exam['class_id'], PDO::PARAM_INT);
$classStmt->execute();
$className = $classStmt->fetchColumn();

// Get exam components
$components = getExamComponents($pdo, $examId);

$errors = [];
$success = '';

// Handle form submission for adding/updating components
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'add_component') {
        // Add new component
        $name = trim($_POST['name'] ?? '');
        $maxMarks = (int)($_POST['max_marks'] ?? 0);
        $isEnabled = isset($_POST['is_enabled']) ? 1 : 0;
        
        // Validation
        if (empty($name)) {
            $errors[] = 'Component name is required';
        }
        
        if ($maxMarks <= 0) {
            $errors[] = 'Maximum marks must be greater than zero';
        }
        
        // Get the next display order
        $displayOrder = 1;
        if ($components && count($components) > 0) {
            $displayOrder = count($components) + 1;
        }
        
        // If no errors, add the component
        if (empty($errors)) {
            $result = addExamComponent($pdo, $examId, $name, $maxMarks, $isEnabled, $displayOrder);
            
            if ($result['success']) {
                $success = $result['message'];
                // Refresh components
                $components = getExamComponents($pdo, $examId);
            } else {
                $errors[] = $result['message'];
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'update_component') {
        // Update existing component
        $componentId = (int)($_POST['component_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $maxMarks = (int)($_POST['max_marks'] ?? 0);
        $isEnabled = isset($_POST['is_enabled']) ? 1 : 0;
        
        // Validation
        if (empty($name)) {
            $errors[] = 'Component name is required';
        }
        
        if ($maxMarks <= 0) {
            $errors[] = 'Maximum marks must be greater than zero';
        }
        
        // If no errors, update the component
        if (empty($errors)) {
            $result = updateExamComponent($pdo, $componentId, $name, $maxMarks, $isEnabled);
            
            if ($result['success']) {
                $success = $result['message'];
                // Refresh components
                $components = getExamComponents($pdo, $examId);
            } else {
                $errors[] = $result['message'];
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'delete_component') {
        // Delete component
        $componentId = (int)($_POST['component_id'] ?? 0);
        
        $result = deleteExamComponent($pdo, $componentId);
        
        if ($result['success']) {
            $success = $result['message'];
            // Refresh components
            $components = getExamComponents($pdo, $examId);
        } else {
            $errors[] = $result['message'];
        }
    }
}

// Calculate total marks
$totalMarks = 0;
$enabledMarks = 0;
if ($components && count($components) > 0) {
    foreach ($components as $component) {
        $totalMarks += $component['max_marks'];
        if ($component['is_enabled']) {
            $enabledMarks += $component['max_marks'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Components - School MIS</title>
    <link rel="stylesheet" href="../assets/styles.css">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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
                            <a class="nav-link" href="teachers_list.php">
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
                            <a class="nav-link active" href="exams_list.php">
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
                    <h1 class="h2">Exam Components</h1>
                    <a href="exams_list.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Exams
                    </a>
                </div>

                <div class="alert alert-info">
                    <strong>Exam:</strong> <?php echo htmlspecialchars($exam['title']); ?> |
                    <strong>Session:</strong> <?php echo htmlspecialchars($exam['session']); ?> |
                    <strong>Term:</strong> <?php echo htmlspecialchars($exam['term']); ?> |
                    <strong>Class:</strong> <?php echo htmlspecialchars($className); ?>
                </div>

                <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                        <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-6">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Add New Component</h5>
                            </div>
                            <div class="card-body">
                                <form method="post" action="">
                                    <input type="hidden" name="action" value="add_component">
                                    
                                    <div class="mb-3">
                                        <label for="name" class="form-label">Component Name</label>
                                        <input type="text" class="form-control" id="name" name="name" required>
                                        <small class="text-muted">Example: 1st CA, 2nd CA, Exam</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="max_marks" class="form-label">Maximum Marks</label>
                                        <input type="number" class="form-control" id="max_marks" name="max_marks" min="1" required>
                                        <small class="text-muted">Example: 15 for 1st CA, 60 for Exam</small>
                                    </div>
                                    
                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" id="is_enabled" name="is_enabled" checked>
                                        <label class="form-check-label" for="is_enabled">Enable this component</label>
                                        <small class="d-block text-muted">Uncheck to disable this component in the exam</small>
                                    </div>
                                    
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-primary">Add Component</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <div class="card">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0">Exam Structure Summary</h5>
                            </div>
                            <div class="card-body">
                                <p><strong>Total Components:</strong> <?php echo count($components); ?></p>
                                <p><strong>Total Maximum Marks:</strong> <?php echo $totalMarks; ?></p>
                                <p><strong>Enabled Components Marks:</strong> <?php echo $enabledMarks; ?></p>
                                
                                <?php if ($enabledMarks != 100): ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle"></i> 
                                    The total marks for enabled components should ideally be 100. 
                                    Current total: <?php echo $enabledMarks; ?>
                                </div>
                                <?php else: ?>
                                <div class="alert alert-success">
                                    <i class="fas fa-check-circle"></i> 
                                    The total marks for enabled components is 100, which is ideal.
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Exam Components</h5>
                            </div>
                            <div class="card-body">
                                <?php if ($components && count($components) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Max Marks</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($components as $component): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($component['name']); ?></td>
                                                <td><?php echo $component['max_marks']; ?></td>
                                                <td>
                                                    <?php if ($component['is_enabled']): ?>
                                                    <span class="badge bg-success">Enabled</span>
                                                    <?php else: ?>
                                                    <span class="badge bg-danger">Disabled</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-primary" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#editComponentModal" 
                                                            data-component-id="<?php echo $component['id']; ?>"
                                                            data-component-name="<?php echo htmlspecialchars($component['name']); ?>"
                                                            data-component-marks="<?php echo $component['max_marks']; ?>"
                                                            data-component-enabled="<?php echo $component['is_enabled']; ?>">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-danger" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#deleteComponentModal"
                                                            data-component-id="<?php echo $component['id']; ?>"
                                                            data-component-name="<?php echo htmlspecialchars($component['name']); ?>">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <div class="alert alert-warning">
                                    No components have been added to this exam yet. Please add components using the form on the left.
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Edit Component Modal -->
    <div class="modal fade" id="editComponentModal" tabindex="-1" aria-labelledby="editComponentModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editComponentModalLabel">Edit Component</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_component">
                        <input type="hidden" name="component_id" id="edit_component_id">
                        
                        <div class="mb-3">
                            <label for="edit_name" class="form-label">Component Name</label>
                            <input type="text" class="form-control" id="edit_name" name="name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_max_marks" class="form-label">Maximum Marks</label>
                            <input type="number" class="form-control" id="edit_max_marks" name="max_marks" min="1" required>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="edit_is_enabled" name="is_enabled">
                            <label class="form-check-label" for="edit_is_enabled">Enable this component</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Component</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Component Modal -->
    <div class="modal fade" id="deleteComponentModal" tabindex="-1" aria-labelledby="deleteComponentModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteComponentModalLabel">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to delete the component "<span id="delete_component_name"></span>"?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="post" action="">
                        <input type="hidden" name="action" value="delete_component">
                        <input type="hidden" name="component_id" id="delete_component_id">
                        <button type="submit" class="btn btn-danger">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Handle edit component modal
        document.addEventListener('DOMContentLoaded', function() {
            const editComponentModal = document.getElementById('editComponentModal');
            if (editComponentModal) {
                editComponentModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    const componentId = button.getAttribute('data-component-id');
                    const componentName = button.getAttribute('data-component-name');
                    const componentMarks = button.getAttribute('data-component-marks');
                    const componentEnabled = button.getAttribute('data-component-enabled') === '1';
                    
                    const modalComponentId = document.getElementById('edit_component_id');
                    const modalComponentName = document.getElementById('edit_name');
                    const modalComponentMarks = document.getElementById('edit_max_marks');
                    const modalComponentEnabled = document.getElementById('edit_is_enabled');
                    
                    modalComponentId.value = componentId;
                    modalComponentName.value = componentName;
                    modalComponentMarks.value = componentMarks;
                    modalComponentEnabled.checked = componentEnabled;
                });
            }
            
            // Handle delete component modal
            const deleteComponentModal = document.getElementById('deleteComponentModal');
            if (deleteComponentModal) {
                deleteComponentModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    const componentId = button.getAttribute('data-component-id');
                    const componentName = button.getAttribute('data-component-name');
                    
                    const modalComponentId = document.getElementById('delete_component_id');
                    const modalComponentName = document.getElementById('delete_component_name');
                    
                    modalComponentId.value = componentId;
                    modalComponentName.textContent = componentName;
                });
            }
        });
    </script>
</body>
</html>
