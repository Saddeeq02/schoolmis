<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../vendor/autoload.php';

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Label\Label;
use Endroid\QrCode\Writer\ValidationException;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh;
use BaconQrCode\Writer;

// Verify admin role
if ($_SESSION['role'] !== 'admin') {
    header("Location: ../unauthorized.php");
    exit;
}

// Get all schools
$stmt = $pdo->query("SELECT id, name, border_color, logo_path FROM schools ORDER BY name");
$schools = $stmt->fetchAll();

// Get selected school details
$selectedSchoolId = $_POST['school_id'] ?? ($_SESSION['school_id'] ?? null);
$selectedSchool = null;

if ($selectedSchoolId) {
    foreach ($schools as $school) {
        if ($school['id'] == $selectedSchoolId) {
            $selectedSchool = $school;
            break;
        }
    }
}

// Handle QR generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['school_id'])) {
    try {
        if (!$selectedSchool) {
            throw new Exception("Please select a school first");
        }

        // Generate unique QR data
        $qr_data = json_encode([
            'school_name' => $selectedSchool['name'],
            'created_at' => date('Y-m-d H:i:s'),
            'type' => 'attendance',
            'unique_id' => uniqid('qr_', true)
        ]);

        // Create QR code with basic configuration
        $qrCode = new QrCode($qr_data);
        $writer = new PngWriter();
        $result = $writer->write($qrCode);

        // Save QR data to database first
        $stmt = $pdo->prepare("INSERT INTO qr_codes (school_name, qr_data, created_by) VALUES (?, ?, ?)");
        if (!$stmt->execute([$selectedSchool['name'], $qr_data, $_SESSION['user_id']])) {
            throw new Exception("Failed to save QR code data to database");
        }
        
        $qr_id = $pdo->lastInsertId();
        
        // Create qr_codes directory if it doesn't exist
        $qr_dir = dirname(__DIR__) . "/qr_codes";
        if (!file_exists($qr_dir)) {
            if (!mkdir($qr_dir, 0755, true)) {
                throw new Exception("Failed to create QR codes directory");
            }
        }
        
        // Use school name in filename
        $safeName = preg_replace('/[^a-z0-9]+/', '_', strtolower($selectedSchool['name']));
        $filename = $qr_dir . "/attendance_" . $safeName . "_" . $qr_id . ".png";
        
        // Save using dataUrl
        $dataUri = $result->getDataUri();
        $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $dataUri));
        
        if (file_put_contents($filename, $imageData) === false) {
            throw new Exception("Failed to save QR code image");
        }
        
        chmod($filename, 0644);
        
        $_SESSION['qr_generated'] = true;
        $_SESSION['qr_filename'] = "qr_codes/attendance_" . $safeName . "_" . $qr_id . ".png";
        
        header("Location: admin_qr_generator.php");
        exit;
    } catch (Exception $e) {
        error_log("QR Generation Error: " . $e->getMessage());
        $_SESSION['error'] = "Error generating QR code: " . $e->getMessage();
        header("Location: admin_qr_generator.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Generate QR Code - School Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/clean-styles.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        .qr-container {
            display: flex;
            gap: 2rem;
            align-items: start;
        }
        .qr-code-section {
            flex: 1;
            text-align: center;
        }
        .instructions-section {
            flex: 1;
            background: #f8f9fa;
            padding: 2rem;
            border-radius: 8px;
        }
        .school-header {
            text-align: center;
            margin-bottom: 2rem;
            padding: 2rem;
            background: <?= $selectedSchool['border_color'] ?? '#3366cc' ?>;
            color: white;
            border-radius: 8px;
        }
        .school-logo {
            max-width: 100px;
            margin-bottom: 1rem;
        }
        .download-btn {
            margin-top: 1rem;
            padding: 0.75rem 2rem;
            font-size: 1.1rem;
        }
        .step {
            margin-bottom: 1.5rem;
            display: flex;
            gap: 1rem;
            align-items: start;
        }
        .step-number {
            background: <?= $selectedSchool['border_color'] ?? '#3366cc' ?>;
            color: white;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            flex-shrink: 0;
        }
        .select-school {
            max-width: 400px;
            margin: 0 auto 2rem;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="school-header">
            <h1>School Attendance QR Code Generator</h1>
            <p class="mb-0">Select a school and generate attendance QR code</p>
        </div>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <?= $_SESSION['error'] ?>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div class="select-school">
            <form method="POST" class="card" id="schoolForm">
                <div class="card-body">
                    <div class="mb-3">
                        <label for="school_id" class="form-label">Select School</label>
                        <select name="school_id" id="school_id" class="form-select" required onchange="this.form.submit()">
                            <option value="">Choose a school...</option>
                            <?php foreach ($schools as $school): ?>
                                <option value="<?= $school['id'] ?>" <?= ($selectedSchoolId == $school['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($school['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if ($selectedSchoolId && !isset($_SESSION['qr_generated'])): ?>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-qrcode"></i> Generate QR Code
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <?php if (isset($_SESSION['qr_generated'])): ?>
            <div class="qr-container">
                <div class="qr-code-section">
                    <div class="card">
                        <div class="card-body">
                            <img src="../<?= $_SESSION['qr_filename'] ?>" alt="QR Code" class="img-fluid mb-3">
                            <div>
                                <a href="../<?= $_SESSION['qr_filename'] ?>" download class="btn btn-primary download-btn">
                                    <i class="fas fa-download"></i> Download QR Code
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="instructions-section">
                    <h3><i class="fas fa-info-circle"></i> Instructions</h3>
                    <div class="step">
                        <div class="step-number">1</div>
                        <div>Download our mobile app from the app store</div>
                    </div>
                    <div class="step">
                        <div class="step-number">2</div>
                        <div>Open the app and log in with your credentials</div>
                    </div>
                    <div class="step">
                        <div class="step-number">3</div>
                        <div>Tap on "Scan QR" in the app</div>
                    </div>
                    <div class="step">
                        <div class="step-number">4</div>
                        <div>Point your camera at this QR code to mark your attendance</div>
                    </div>
                </div>
            </div>
            <?php unset($_SESSION['qr_generated']); ?>
            <?php unset($_SESSION['qr_filename']); ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
