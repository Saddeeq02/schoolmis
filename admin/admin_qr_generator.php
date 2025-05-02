<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../vendor/autoload.php';

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\ErrorCorrectionLevel;

// Check if user is logged in and is an admin
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php');
    exit;
}

// Get all classes for the dropdown
$classesStmt = $pdo->query("SELECT id, class_name FROM classes ORDER BY class_name");
$classes = $classesStmt->fetchAll();

// Handle form submission
$qrCodeImage = null;
$qrCodePath = null;
$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['type'] ?? '';
    $classId = isset($_POST['class_id']) ? (int)$_POST['class_id'] : 0;
    $customText = $_POST['custom_text'] ?? '';
    
    // Validate inputs
    if (empty($type)) {
        $errorMessage = 'Please select a QR code type';
    } elseif ($type === 'attendance' && $classId <= 0) {
        $errorMessage = 'Please select a class for attendance QR code';
    } elseif ($type === 'custom' && empty($customText)) {
        $errorMessage = 'Please enter custom text for the QR code';
    } else {
        // Generate QR code content based on type
        $content = '';
        $filename = '';
        
        if ($type === 'attendance') {
            // Get class name for the filename
            $classStmt = $pdo->prepare("SELECT class_name FROM classes WHERE id = ?");
            $classStmt->execute([$classId]);
            $className = $classStmt->fetchColumn();
            
            // Generate attendance QR code content
            $content = json_encode([
                'type' => 'attendance',
                'class_id' => $classId,
                'timestamp' => time()
            ]);
            
            $filename = 'attendance_' . $classId . '.png';
        } elseif ($type === 'custom') {
            // Generate custom QR code content
            $content = $customText;
            $filename = 'custom_' . time() . '.png';
        }
        
        // Create QR code
        try {
            $qrCode = new QrCode($content);
            $qrCode->setSize(300);
            $qrCode->setMargin(10);
            $qrCode->setErrorCorrectionLevel(ErrorCorrectionLevel::HIGH);
            $qrCode->setForegroundColor(new Color(0, 0, 0));
            $qrCode->setBackgroundColor(new Color(255, 255, 255));
            
            // Create writer
            $writer = new PngWriter();
            $result = $writer->write($qrCode);
            
            // Save QR code to file
            $qrCodeDir = '../qr_codes/';
            if (!is_dir($qrCodeDir)) {
                mkdir($qrCodeDir, 0755, true);
            }
            
            $qrCodePath = $qrCodeDir . $filename;
            file_put_contents($qrCodePath, $result->getString());
            
            // Get data URI for display
            $qrCodeImage = $result->getDataUri();
            
            $successMessage = 'QR code generated successfully!';
        } catch (Exception $e) {
            $errorMessage = 'Error generating QR code: ' . $e->getMessage();
        }
    }
}

// Get existing QR codes
$qrCodesDir = '../qr_codes/';
$existingQrCodes = [];

if (is_dir($qrCodesDir)) {
    $files = scandir($qrCodesDir);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..' && pathinfo($file, PATHINFO_EXTENSION) === 'png') {
            $existingQrCodes[] = [
                'name' => $file,
                'path' => $qrCodesDir . $file,
                'url' => '../qr_codes/' . $file,
                'created' => filemtime($qrCodesDir . $file)
            ];
        }
    }
    
    // Sort by creation time (newest first)
    usort($existingQrCodes, function($a, $b) {
        return $b['created'] - $a['created'];
    });
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Code Generator - School MIS</title>
    <link rel="stylesheet" href="../assets/styles.css">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .qr-code-container {
            text-align: center;
            margin-bottom: 20px;
        }
        .qr-code-container img {
            max-width: 100%;
            height: auto;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 5px;
            background: white;
        }
        .qr-code-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
        }
        .qr-code-item {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            background: white;
            transition: transform 0.2s;
        }
        .qr-code-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .qr-code-item img {
            max-width: 100%;
            height: auto;
            margin-bottom: 10px;
        }
        .qr-code-actions {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 10px;
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
                            <a class="nav-link active" href="admin_qr_generator.php">
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
                    <h1 class="h2">QR Code Generator</h1>
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>

                <?php if ($successMessage): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $successMessage; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <?php if ($errorMessage): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $errorMessage; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-6">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Generate QR Code</h5>
                            </div>
                            <div class="card-body">
                                <form method="post" action="">
                                    <div class="mb-3">
                                        <label for="type" class="form-label">QR Code Type</label>
                                        <select class="form-select" id="type" name="type" onchange="toggleFields()">
                                            <option value="">Select Type</option>
                                            <option value="attendance" <?php echo isset($_POST['type']) && $_POST['type'] === 'attendance' ? 'selected' : ''; ?>>Attendance</option>
                                            <option value="custom" <?php echo isset($_POST['type']) && $_POST['type'] === 'custom' ? 'selected' : ''; ?>>Custom</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3" id="classField" style="display: none;">
                                        <label for="class_id" class="form-label">Class</label>
                                        <select class="form-select" id="class_id" name="class_id">
                                            <option value="">Select Class</option>
                                            <?php foreach ($classes as $class): ?>
                                            <option value="<?php echo $class['id']; ?>" <?php echo isset($_POST['class_id']) && $_POST['class_id'] == $class['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($class['class_name']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3" id="customTextField" style="display: none;">
                                        <label for="custom_text" class="form-label">Custom Text</label>
                                        <textarea class="form-control" id="custom_text" name="custom_text" rows="3"><?php echo htmlspecialchars($_POST['custom_text'] ?? ''); ?></textarea>
                                    </div>
                                    
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-primary">Generate QR Code</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <?php if ($qrCodeImage): ?>
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Generated QR Code</h5>
                            </div>
                            <div class="card-body">
                                <div class="qr-code-container">
                                    <img src="<?php echo $qrCodeImage; ?>" alt="QR Code">
                                </div>
                                <div class="d-grid gap-2">
                                    <a href="<?php echo $qrCodeImage; ?>" download="qrcode.png" class="btn btn-success">
                                        <i class="fas fa-download"></i> Download QR Code
                                    </a>
                                    <a href="<?php echo str_replace('../', '', $qrCodePath); ?>" target="_blank" class="btn btn-info">
                                        <i class="fas fa-external-link-alt"></i> Open in New Tab
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Recent QR Codes</h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($existingQrCodes)): ?>
                                <div class="qr-code-grid">
                                    <?php foreach (array_slice($existingQrCodes, 0, 6) as $qrCode): ?>
                                    <div class="qr-code-item">
                                        <img src="<?php echo $qrCode['url']; ?>" alt="<?php echo htmlspecialchars($qrCode['name']); ?>">
                                        <p class="small text-muted"><?php echo htmlspecialchars($qrCode['name']); ?></p>
                                        <div class="qr-code-actions">
                                            <a href="<?php echo $qrCode['url']; ?>" download class="btn btn-sm btn-success">
                                                <i class="fas fa-download"></i>
                                            </a>
                                            <a href="<?php echo $qrCode['url']; ?>" target="_blank" class="btn btn-sm btn-info">
                                                <i class="fas fa-external-link-alt"></i>
                                            </a>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php else: ?>
                                <div class="alert alert-info">
                                    No QR codes have been generated yet.
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Function to toggle form fields based on QR code type
        function toggleFields() {
            const type = document.getElementById('type').value;
            const classField = document.getElementById('classField');
            const customTextField = document.getElementById('customTextField');
            
            if (type === 'attendance') {
                classField.style.display = 'block';
                customTextField.style.display = 'none';
            } else if (type === 'custom') {
                classField.style.display = 'none';
                customTextField.style.display = 'block';
            } else {
                classField.style.display = 'none';
                customTextField.style.display = 'none';
            }
        }
        
        // Initialize form fields on page load
        document.addEventListener('DOMContentLoaded', function() {
            toggleFields();
        });
    </script>
</body>
</html>
 