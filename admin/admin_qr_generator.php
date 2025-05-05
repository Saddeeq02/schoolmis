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

// Get school details for the current school
$schoolId = $_SESSION['school_id'] ?? 1;
$stmt = $pdo->prepare("SELECT * FROM schools WHERE id = ?");
$stmt->execute([$schoolId]);
$school = $stmt->fetch();

if (!$school) {
    $_SESSION['error'] = "School not found";
    header("Location: admin_qr_generator.php");
    exit;
}

// Handle QR generation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Generate unique QR data
        $qr_data = json_encode([
            'school_name' => $school['name'],
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
        if (!$stmt->execute([$school['name'], $qr_data, $_SESSION['user_id']])) {
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
        $safeName = preg_replace('/[^a-z0-9]+/', '_', strtolower($school['name']));
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
    <title>Generate QR Code - <?= htmlspecialchars($school['name']) ?></title>
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
            background: <?= $school['border_color'] ?? '#3366cc' ?>;
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
            background: <?= $school['border_color'] ?? '#3366cc' ?>;
            color: white;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            flex-shrink: 0;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="school-header">
            <?php if (!empty($school['logo_path'])): ?>
                <img src="../<?= htmlspecialchars($school['logo_path']) ?>" alt="School Logo" class="school-logo">
            <?php endif; ?>
            <h1><?= htmlspecialchars($school['name']) ?></h1>
            <p class="mb-0">Attendance QR Code Generator</p>
        </div>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <?= $_SESSION['error'] ?>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

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
        
        <?php if (!isset($_SESSION['qr_generated'])): ?>
            <div class="card">
                <div class="card-body">
                    <form method="POST" class="mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-qrcode"></i> Generate New QR Code
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
