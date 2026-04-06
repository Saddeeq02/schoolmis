<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../vendor/autoload.php';

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

// Handle QR logging (data is now generated on client, but we log the intent/request)
$qrDataForJS = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['school_id']) && isset($_POST['generate'])) {
    try {
        if (!$selectedSchool) {
            throw new Exception("Please select a school first");
        }

        // Prepare unique QR data for JS
        $qrDataArray = [
            'school_name' => $selectedSchool['name'],
            'created_at' => date('Y-m-d H:i:s'),
            'type' => 'attendance',
            'unique_id' => uniqid('qr_', true)
        ];
        $qrDataForJS = json_encode($qrDataArray);

        // Save QR data to database
        $stmt = $pdo->prepare("INSERT INTO qr_codes (school_name, qr_data, created_by) VALUES (?, ?, ?)");
        if (!$stmt->execute([$selectedSchool['name'], $qrDataForJS, $_SESSION['user_id']])) {
            throw new Exception("Failed to save QR code data to database");
        }
        
    } catch (Exception $e) {
        error_log("QR Logging Error: " . $e->getMessage());
        $_SESSION['error'] = "Error preparing QR code: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate QR Code - School Management System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/clean-styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- QRCode.js Library -->
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    
    <style>
        .qr-container {
            display: none;
            gap: 2rem;
            align-items: start;
            margin-top: 2rem;
        }
        .qr-container.active {
            display: flex;
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
            background: var(--primary); /* Use Emerald Green */
            color: white;
            border-radius: 8px;
        }
        #qrcode-canvas {
            display: inline-block;
            padding: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 1rem;
        }
        #qrcode-canvas img {
            margin: 0 auto;
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
            background: var(--primary);
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
            margin: 0 auto;
        }
    </style>
</head>
<body>
    <div class="container mt-5 mb-5">
        <div class="school-header mb-4">
            <h1>School Attendance QR Code Generator</h1>
            <p class="mb-0">Powered by Topspring Gems Comprehensive School Identity</p>
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
                    <?php if ($selectedSchoolId): ?>
                        <input type="hidden" name="generate" value="1">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-qrcode"></i> Generate QR Code
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="qr-container <?= $qrDataForJS ? 'active' : '' ?>" id="qrOutput">
            <div class="qr-code-section">
                <div class="card">
                    <div class="card-body">
                        <div id="qrcode-canvas"></div>
                        <div class="mt-3">
                            <button onclick="downloadQR()" class="btn btn-primary download-btn">
                                <i class="fas fa-download"></i> Download QR Code
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="instructions-section">
                <h3><i class="fas fa-info-circle"></i> Instructions</h3>
                <div class="step">
                    <div class="step-number">1</div>
                    <div>Download the TGCS Attendance App</div>
                </div>
                <div class="step">
                    <div class="step-number">2</div>
                    <div>Log in with your teacher/student credentials</div>
                </div>
                <div class="step">
                    <div class="step-number">3</div>
                    <div>Tap on "Scan QR" and point at this code</div>
                </div>
                <div class="step">
                    <div class="step-number">4</div>
                    <div>Your attendance will be logged instantly in the cloud</div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Generate QR on client side
        <?php if ($qrDataForJS): ?>
        window.addEventListener('load', function() {
            const qrData = '<?= $qrDataForJS ?>';
            const qrcode = new QRCode(document.getElementById("qrcode-canvas"), {
                text: qrData,
                width: 256,
                height: 256,
                colorDark : "#2c3e50",
                colorLight : "#ffffff",
                correctLevel : QRCode.CorrectLevel.H
            });
        });
        <?php endif; ?>

        function downloadQR() {
            const qrImg = document.querySelector("#qrcode-canvas img");
            if (qrImg) {
                const link = document.createElement("a");
                link.href = qrImg.src;
                link.download = "tgcs_attendance_qr.png";
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }
        }
    </script>
</body>
</html>
