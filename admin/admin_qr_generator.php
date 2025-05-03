<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../vendor/autoload.php';

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;

// Verify admin role
if ($_SESSION['role'] !== 'admin') {
    header("Location: ../unauthorized.php");
    exit;
}

// Handle QR generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['school_name'])) {
    $school_name = $_POST['school_name'];
    $admin_id = $_SESSION['user_id'];
    
    try {
        // Generate unique QR data
        $qr_data = json_encode([
            'school_name' => $school_name,
            'created_at' => date('Y-m-d H:i:s'),
            'type' => 'attendance',
            'unique_id' => uniqid('qr_', true)
        ]);
        
        // Generate QR code
        $qrCode = new QrCode($qr_data);
        
        $writer = new PngWriter();
        $result = $writer->write(
            $qrCode,
            null,
            null,
            [
                'size' => 300,
                'margin' => 10
            ]
        );

        // Save QR data to database first
        $stmt = $pdo->prepare("INSERT INTO qr_codes (school_name, qr_data, created_by) VALUES (?, ?, ?)");
        
        if (!$stmt->execute([$school_name, $qr_data, $admin_id])) {
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
        
        // Save QR image with proper permissions
        $filename = $qr_dir . "/attendance_" . $qr_id . ".png";
        
        // Save using dataUrl
        $dataUri = $result->getDataUri();
        $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $dataUri));
        
        if (file_put_contents($filename, $imageData) === false) {
            throw new Exception("Failed to save QR code image");
        }
        
        // Set proper file permissions
        chmod($filename, 0644);
        
        $_SESSION['qr_generated'] = true;
        $_SESSION['qr_filename'] = "qr_codes/attendance_" . $qr_id . ".png";
        
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
    <title>Generate QR Code</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/clean-styles.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
    <div class="container mt-5">
        <h2>Generate Attendance QR Code</h2>
        
        <?php if (isset($_SESSION['qr_generated'])): ?>
            <div class="alert alert-success">
                QR Code generated successfully!
                <div class="mt-3">
                    <img src="../<?= $_SESSION['qr_filename'] ?>" alt="QR Code" class="img-fluid">
                    <a href="../<?= $_SESSION['qr_filename'] ?>" download class="btn btn-primary mt-2">Download QR Code</a>
                </div>
            </div>
            <?php unset($_SESSION['qr_generated']); ?>
            <?php unset($_SESSION['qr_filename']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <?= $_SESSION['error'] ?>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        
        <form method="POST" class="mt-4">
            <div class="mb-3">
                <label for="school_name" class="form-label">School Name</label>
                <input type="text" class="form-control" id="school_name" name="school_name" required>
            </div>
            <button type="submit" class="btn btn-primary">Generate QR Code</button>
        </form>
    </div>
</body>
</html>
