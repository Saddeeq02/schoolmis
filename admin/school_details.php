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

// Get school ID from session
$schoolId = $_SESSION['school_id'];
$school = null;

// Get school details from schools table
$stmt = $pdo->prepare("SELECT * FROM schools WHERE id = ?");
$stmt->execute([$schoolId]);
$school = $stmt->fetch();

$errors = [];
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate inputs
    $address = trim($_POST['address'] ?? '');
    $borderColor = trim($_POST['border_color'] ?? '#3366cc');
    $logoPath = $school['logo_path'] ?? ''; // Default to existing logo path
    
    // Handle logo upload if a file was selected
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $maxSize = 2 * 1024 * 1024; // 2MB
        
        if (!in_array($_FILES['logo']['type'], $allowedTypes)) {
            $errors[] = 'Invalid file type. Only JPG, PNG, and GIF are allowed.';
        } elseif ($_FILES['logo']['size'] > $maxSize) {
            $errors[] = 'File size exceeds the maximum limit of 2MB.';
        } else {
            // Create uploads directory if it doesn't exist
            $uploadDir = '../uploads/logos/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            // Generate a unique filename
            $filename = $school['code'] . '_logo_' . time() . '_' . basename($_FILES['logo']['name']);
            $uploadPath = $uploadDir . $filename;
            
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $uploadPath)) {
                $logoPath = 'uploads/logos/' . $filename;
            } else {
                $errors[] = 'Failed to upload the logo. Please try again.';
            }
        }
    }
    
    // If no errors, save the school details
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE schools 
                SET address = ?, 
                    logo_path = ?,
                    border_color = ?
                WHERE id = ?
            ");
            
            if ($stmt->execute([$address, $logoPath, $borderColor, $schoolId])) {
                $success = 'School details saved successfully';
                // Refresh school details
                $stmt = $pdo->prepare("SELECT * FROM schools WHERE id = ?");
                $stmt->execute([$schoolId]);
                $school = $stmt->fetch();
            } else {
                $errors[] = 'Failed to save school details';
            }
        } catch (PDOException $e) {
            $errors[] = 'Database error occurred: ' . $e->getMessage();
            error_log("School details save error: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>School Details - School MIS</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/clean-styles.css">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h3 class="mb-0">School Details</h3>
                        <a href="dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                <li><?php echo $error; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                        <div class="alert alert-success">
                            <?php echo $success; ?>
                        </div>
                        <?php endif; ?>
                        
                        <form method="post" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="school_name" class="form-label">School Name</label>
                                <input type="text" class="form-control" id="school_name" value="<?php echo htmlspecialchars($school['name'] ?? ''); ?>" readonly>
                                <small class="text-muted">School name is set during registration and cannot be changed here.</small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="address" class="form-label">School Address</label>
                                <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($school['address'] ?? ''); ?></textarea>
                                <small class="text-muted">This address will appear on report cards.</small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="border_color" class="form-label">Report Card Border Color</label>
                                <input type="color" class="form-control form-control-color" id="border_color" name="border_color" value="<?php echo htmlspecialchars($school['border_color'] ?? '#3366cc'); ?>">
                                <small class="text-muted">Select a color for the border of report cards.</small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="logo" class="form-label">School Logo</label>
                                <?php if (!empty($school['logo_path'])): ?>
                                <div class="mb-2">
                                    <img src="<?php echo '../' . htmlspecialchars($school['logo_path']); ?>" alt="School Logo" class="img-thumbnail" style="max-height: 100px;">
                                </div>
                                <?php endif; ?>
                                <input type="file" class="form-control" id="logo" name="logo">
                                <small class="text-muted">Upload a logo image (JPG, PNG, or GIF, max 2MB). This logo will appear on report cards.</small>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">Save School Details</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="../assets/clean-styles.css">

</body>
</html>
