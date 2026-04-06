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

// Handle form submission for adding/editing a school
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            // Add new school
            $name = trim($_POST['name'] ?? '');
            $code = trim($_POST['code'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $borderColor = trim($_POST['border_color'] ?? '#3366cc');
            
            // Handle logo upload
            $logoPath = null;
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = '../uploads/logos/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                $filename = $code . '_logo_' . time() . '_' . basename($_FILES['logo']['name']);
                $uploadPath = $uploadDir . $filename;
                
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $uploadPath)) {
                    $logoPath = 'uploads/logos/' . $filename;
                }
            }
            
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO schools (name, code, address, logo_path, border_color, created_by)
                    VALUES (:name, :code, :address, :logo_path, :border_color, :created_by)
                ");
                
                $stmt->bindParam(':name', $name);
                $stmt->bindParam(':code', $code);
                $stmt->bindParam(':address', $address);
                $stmt->bindParam(':logo_path', $logoPath);
                $stmt->bindParam(':border_color', $borderColor);
                $stmt->bindParam(':created_by', $_SESSION['user_id']);
                
                $stmt->execute();
                
                $_SESSION['message'] = 'School added successfully';
                $_SESSION['message_type'] = 'success';
            } catch (PDOException $e) {
                $_SESSION['message'] = 'Error adding school: ' . $e->getMessage();
                $_SESSION['message_type'] = 'error';
            }
        } elseif ($_POST['action'] === 'edit') {
            // Edit existing school
            $id = (int)$_POST['id'];
            $name = trim($_POST['name'] ?? '');
            $code = trim($_POST['code'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $borderColor = trim($_POST['border_color'] ?? '#3366cc');
            
            // Handle logo upload
            $logoPath = $_POST['current_logo'] ?? null;
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = '../uploads/logos/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                $filename = $code . '_logo_' . time() . '_' . basename($_FILES['logo']['name']);
                $uploadPath = $uploadDir . $filename;
                
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $uploadPath)) {
                    $logoPath = 'uploads/logos/' . $filename;
                }
            }
            
            try {
                $stmt = $pdo->prepare("
                    UPDATE schools 
                    SET name = :name, 
                        code = :code, 
                        address = :address, 
                        logo_path = :logo_path, 
                        border_color = :border_color
                    WHERE id = :id
                ");
                
                $stmt->bindParam(':name', $name);
                $stmt->bindParam(':code', $code);
                $stmt->bindParam(':address', $address);
                $stmt->bindParam(':logo_path', $logoPath);
                $stmt->bindParam(':border_color', $borderColor);
                $stmt->bindParam(':id', $id);
                
                $stmt->execute();
                
                $_SESSION['message'] = 'School updated successfully';
                $_SESSION['message_type'] = 'success';
            } catch (PDOException $e) {
                $_SESSION['message'] = 'Error updating school: ' . $e->getMessage();
                $_SESSION['message_type'] = 'error';
            }
        } elseif ($_POST['action'] === 'delete') {
            // Delete school
            $id = (int)$_POST['id'];
            
            try {
                $stmt = $pdo->prepare("DELETE FROM schools WHERE id = :id");
                $stmt->bindParam(':id', $id);
                $stmt->execute();
                
                $_SESSION['message'] = 'School deleted successfully';
                $_SESSION['message_type'] = 'success';
            } catch (PDOException $e) {
                $_SESSION['message'] = 'Error deleting school: ' . $e->getMessage();
                $_SESSION['message_type'] = 'error';
            }
        }
        
        // Redirect to avoid form resubmission
        header('Location: schools.php');
        exit;
    }
}

// Get all schools
$stmt = $pdo->query("SELECT * FROM schools ORDER BY name ASC");
$schools = $stmt->fetchAll();

// Handle message display
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
    <title>School Management - School MIS</title>
    <link rel="stylesheet" href="../assets/clean-styles.css">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="container-fluid">
        <!-- <div class="row"> -->
            <!-- Sidebar -->
            <!-- <div class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-home"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="schools.php">
                                <i class="fas fa-school"></i> Schools
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
                            <a class="nav-link" href="exams_list.php">
                                <i class="fas fa-file-alt"></i> Exams
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../logout.php">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div> -->

            <!-- Main content -->
            <main class="col-md-20 ms-sm-auto col-lg-20 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">School Management</h1>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSchoolModal">
                        <i class="fas fa-plus"></i> Add New School
                    </button>
                </div>

                <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType === 'error' ? 'danger' : 'success'; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <!-- Schools table -->
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Logo</th>
                                <th>Name</th>
                                <th>Code</th>
                                <th>Address</th>
                                <th>Border Color</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($schools && count($schools) > 0): ?>
                                <?php foreach ($schools as $school): ?>
                                <tr>
                                    <td><?php echo $school['id']; ?></td>
                                    <td>
                                        <?php if (!empty($school['logo_path'])): ?>
                                            <img src="<?php echo '../' . htmlspecialchars($school['logo_path']); ?>" alt="School Logo" style="height: 40px;">
                                        <?php else: ?>
                                            <span class="text-muted">No logo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($school['name']); ?></td>
                                    <td><?php echo htmlspecialchars($school['code']); ?></td>
                                    <td><?php echo htmlspecialchars($school['address']); ?></td>
                                    <td>
                                        <div style="width: 20px; height: 20px; background-color: <?php echo htmlspecialchars($school['border_color']); ?>; border: 1px solid #ddd;"></div>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-primary edit-school" 
                                                data-id="<?php echo $school['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($school['name']); ?>"
                                                data-code="<?php echo htmlspecialchars($school['code']); ?>"
                                                data-address="<?php echo htmlspecialchars($school['address']); ?>"
                                                data-logo="<?php echo htmlspecialchars($school['logo_path'] ?? ''); ?>"
                                                data-color="<?php echo htmlspecialchars($school['border_color']); ?>"
                                                data-bs-toggle="modal" data-bs-target="#editSchoolModal">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger" 
                                                onclick="confirmDelete(<?php echo $school['id']; ?>, '<?php echo htmlspecialchars($school['name']); ?>')">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center">No schools found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </main>
        </div>
    </div>

    <!-- Add School Modal -->
    <div class="modal fade" id="addSchoolModal" tabindex="-1" aria-labelledby="addSchoolModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addSchoolModalLabel">Add New School</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" action="" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="name" class="form-label">School Name</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="code" class="form-label">School Code</label>
                            <input type="text" class="form-control" id="code" name="code" required>
                            <small class="text-muted">A unique identifier for the school</small>
                        </div>
                        <div class="mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="logo" class="form-label">School Logo</label>
                            <input type="file" class="form-control" id="logo" name="logo">
                            <small class="text-muted">Upload a logo image (JPG, PNG, or GIF, max 2MB)</small>
                        </div>
                        <div class="mb-3">
                            <label for="border_color" class="form-label">Report Card Border Color</label>
                            <input type="color" class="form-control form-control-color" id="border_color" name="border_color" value="#3366cc">
                            <small class="text-muted">Select a color for the border of report cards</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add School</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit School Modal -->
    <div class="modal fade" id="editSchoolModal" tabindex="-1" aria-labelledby="editSchoolModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editSchoolModalLabel">Edit School</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" action="" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id">
                    <input type="hidden" name="current_logo" id="current_logo">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="edit_name" class="form-label">School Name</label>
                            <input type="text" class="form-control" id="edit_name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_code" class="form-label">School Code</label>
                            <input type="text" class="form-control" id="edit_code" name="code" required>
                            <small class="text-muted">A unique identifier for the school</small>
                        </div>
                        <div class="mb-3">
                            <label for="edit_address" class="form-label">Address</label>
                            <textarea class="form-control" id="edit_address" name="address" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="edit_logo" class="form-label">School Logo</label>
                            <div id="logo_preview" class="mb-2"></div>
                            <input type="file" class="form-control" id="edit_logo" name="logo">
                            <small class="text-muted">Upload a new logo or leave empty to keep the current one</small>
                        </div>
                        <div class="mb-3">
                            <label for="edit_border_color" class="form-label">Report Card Border Color</label>
                            <input type="color" class="form-control form-control-color" id="edit_border_color" name="border_color">
                            <small class="text-muted">Select a color for the border of report cards</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update School</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to delete the school "<span id="schoolName"></span>"?
                    <p class="text-danger mt-2">This will also delete all associated data (classes, students, exams, etc.) for this school.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form id="deleteForm" method="post" action="">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" id="deleteSchoolId">
                        <button type="submit" class="btn btn-danger">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
 <!-- Bottom Navigation for Mobile -->
 <div class="bottom-nav py-2 px-3 justify-content-around">
        <a href="dashboard.php" class="btn btn-sm btn-light active">
            <i class="fas fa-home d-block text-center mb-1"></i>
            <small>Home</small>
        </a>
        <a href="students_list.php" class="btn btn-sm btn-light">
            <i class="fas fa-user-graduate d-block text-center mb-1"></i>
            <small>Students</small>
        </a>
        <a href="teachers_list.php" class="btn btn-sm btn-light">
            <i class="fas fa-chalkboard-teacher d-block text-center mb-1"></i>
            <small>Teachers</small>
        </a>
        <a href="exams_list.php" class="btn btn-sm btn-light">
            <i class="fas fa-file-alt d-block text-center mb-1"></i>
            <small>Exams</small>
        </a>
        <a href="#" class="btn btn-sm btn-light" data-bs-toggle="offcanvas" data-bs-target="#moreMenu">
            <i class="fas fa-ellipsis-h d-block text-center mb-1"></i>
            <small>More</small>
        </a>
    </div>

    <!-- More Menu Offcanvas for Mobile -->
    <div class="offcanvas offcanvas-bottom" tabindex="-1" id="moreMenu" style="height: 60vh;">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title">More Options</h5>
            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <div class="row g-3">
                <div class="col-4">
                    <a href="schools.php" class="d-flex flex-column align-items-center text-decoration-none">
                        <div class="bg-light p-3 rounded-circle mb-2">
                            <i class="fas fa-school text-primary"></i>
                        </div>
                        <span class="small">Schools</span>
                    </a>
                </div>
                <div class="col-4">
                    <a href="create_class.php" class="d-flex flex-column align-items-center text-decoration-none">
                        <div class="bg-light p-3 rounded-circle mb-2">
                            <i class="fas fa-chalkboard text-warning"></i>
                        </div>
                        <span class="small">Classes</span>
                    </a>
                </div>
                <div class="col-4">
                    <a href="create_subject.php" class="d-flex flex-column align-items-center text-decoration-none">
                        <div class="bg-light p-3 rounded-circle mb-2">
                            <i class="fas fa-book text-danger"></i>
                        </div>
                        <span class="small">Subjects</span>
                    </a>
                </div>
                <div class="col-4">
                    <a href="teacher_subjects.php" class="d-flex flex-column align-items-center text-decoration-none">
                        <div class="bg-light p-3 rounded-circle mb-2">
                            <i class="fas fa-user-tie text-secondary"></i>
                        </div>
                        <span class="small">Teacher Subjects</span>
                    </a>
                </div>
                <div class="col-4">
                    <a href="admin_qr_generator.php" class="d-flex flex-column align-items-center text-decoration-none">
                        <div class="bg-light p-3 rounded-circle mb-2">
                            <i class="fas fa-qrcode text-dark"></i>
                        </div>
                        <span class="small">QR Generator</span>
                    </a>
                </div>
                <div class="col-4">
                    <a href="view_recordings.php" class="d-flex flex-column align-items-center text-decoration-none">
                        <div class="bg-light p-3 rounded-circle mb-2">
                            <i class="fas fa-video text-info"></i>
                        </div>
                        <span class="small">Recordings</span>
                    </a>
                </div>
                <div class="col-4">
                    <a href="report.php" class="d-flex flex-column align-items-center text-decoration-none">
                        <div class="bg-light p-3 rounded-circle mb-2">
                            <i class="fas fa-chart-bar text-success"></i>
                        </div>
                        <span class="small">Reports</span>
                    </a>
                </div>
                <div class="col-4">
                    <a href="school_details.php" class="d-flex flex-column align-items-center text-decoration-none">
                        <div class="bg-light p-3 rounded-circle mb-2">
                            <i class="fas fa-cog text-secondary"></i>
                        </div>
                        <span class="small">Settings</span>
                    </a>
                </div>
                <div class="col-4">
                    <a href="../logout.php" class="d-flex flex-column align-items-center text-decoration-none">
                        <div class="bg-light p-3 rounded-circle mb-2">
                            <i class="fas fa-sign-out-alt text-danger"></i>
                        </div>
                        <span class="small">Logout</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Edit school modal
        document.querySelectorAll('.edit-school').forEach(button => {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name');
                const code = this.getAttribute('data-code');
                const address = this.getAttribute('data-address');
                const logo = this.getAttribute('data-logo');
                const color = this.getAttribute('data-color');
                
                document.getElementById('edit_id').value = id;
                document.getElementById('edit_name').value = name;
                document.getElementById('edit_code').value = code;
                document.getElementById('edit_address').value = address;
                document.getElementById('current_logo').value = logo;
                document.getElementById('edit_border_color').value = color;
                
                // Show logo preview if available
                const logoPreview = document.getElementById('logo_preview');
                if (logo) {
                    logoPreview.innerHTML = `<img src="../${logo}" alt="School Logo" style="max-height: 100px;" class="img-thumbnail">`;
                } else {
                    logoPreview.innerHTML = '<p class="text-muted">No logo available</p>';
                }
            });
        });
        
        // Delete confirmation
        function confirmDelete(id, name) {
            document.getElementById('schoolName').textContent = name;
            document.getElementById('deleteSchoolId').value = id;
            var deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            deleteModal.show();
        }
    </script>
</body>
</html>