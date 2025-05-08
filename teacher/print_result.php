<?php
include '../includes/auth.php';
include '../includes/db.php';
include '../includes/functions.php';

if ($_SESSION['role'] != 'teacher') {
    header("Location: ../index.php");
    exit();
}

// Check if student_id and exam_id are provided
if (!isset($_GET['student_id']) || !isset($_GET['exam_id'])) {
    $_SESSION['message'] = 'Missing required parameters';
    $_SESSION['message_type'] = 'alert-error';
    header("Location: exams_list.php");
    exit();
}

$studentId = $_GET['student_id'];
$examId = $_GET['exam_id'];

// Get student and exam details
$student = getStudentById($pdo, $studentId);
$exam = getExamById($pdo, $examId);

if (!$student || !$exam) {
    $_SESSION['message'] = 'Student or exam not found';
    $_SESSION['message_type'] = 'alert-error';
    header("Location: exams_list.php");
    exit();
}

// Get school details from schools table
$stmt = $pdo->prepare("SELECT * FROM schools WHERE id = ?");
$stmt->execute([$student['school_id']]);
$schoolDetails = $stmt->fetch();

// Set border color with fallback
$borderColor = $schoolDetails['border_color'] ?? '#3366cc';

// Function to lighten a hex color
function lightenColor($hex, $percent = 0.3) {
    $hex = ltrim($hex, '#');
    $rgb = sscanf($hex, "%02x%02x%02x");
    $r = min(255, $rgb[0] + ($rgb[0] * $percent));
    $g = min(255, $rgb[1] + ($rgb[1] * $percent));
    $b = min(255, $rgb[2] + ($rgb[2] * $percent));
    return sprintf("#%02x%02x%02x", $r, $g, $b);
}

$lightBorderColor = lightenColor($borderColor, 0.6); // Lighter shade for row backgrounds

// Get result data
$result = getStudentExamResult($pdo, $studentId, $examId);

if (!$result) {
    $_SESSION['message'] = 'Failed to generate result';
    $_SESSION['message_type'] = 'alert-error';
    header("Location: exams_list.php");
    exit();
}

// Define psychomotor skills and traits
$psychomotorSkills = ['Handwriting', 'Reading Skills', 'Drawing', 'Crafts', 'Sports'];
$traits = ['Punctuality', 'Neatness', 'Leadership', 'Honesty', 'Cooperation'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Result - <?= htmlspecialchars($student['name']) ?></title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        @page {
            size: A4 portrait;
            margin: 0;
        }
        
        body {
            font-family: Arial, sans-serif;
            line-height: 1.3;
            color: #333;
            background-color: #fff;
            margin: 0;
            font-size: 9pt;
        }

        .report-card {
            width: 210mm;
            height: 297mm; /* Fixed height for A4 */
            margin: 0 auto;
            border: 12px solid <?= $borderColor ?>;
            background-color: white;
            padding: 10mm;
            box-sizing: border-box;
            position: relative;
            overflow: hidden; /* Prevent content overflow */
        }

        .school-header {
            text-align: center;
            margin-bottom: 5mm;
            padding-bottom: 2mm;
            border-bottom: 1px solid <?= $borderColor ?>;
        }

        .school-header h2 {
            margin: 1mm 0;
            font-size: 14pt;
        }

        .school-header h3 {
            margin: 1mm 0;
            font-size: 12pt;
        }

        .school-header p {
            margin: 1mm 0;
            font-size: 9pt;
        }

        .school-logo {
            max-width: 45px;
            max-height: 45px;
            margin-bottom: 2mm;
        }

        .student-info {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 2mm;
            margin-bottom: 4mm;
            font-size: 9pt;
        }

        .student-info p {
            margin: 0.5mm 0;
        }

        .results-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 3mm;
            font-size: 9pt;
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1); /* Enhanced shadow */
        }

        .results-table th, .results-table td {
            padding: 1.8mm; /* Slightly reduced for space */
            border: 0.5pt solid <?= $borderColor ?>; /* Dynamic border color */
            text-align: left;
        }

        .results-table th {
            background: linear-gradient(135deg, <?= $borderColor ?>, <?= lightenColor($borderColor, 0.2) ?>) !important; /* Dynamic gradient */
            color: white !important; /* Default for readability */
            font-weight: 600;
            text-transform: uppercase;
            font-size: 8.5pt;
            letter-spacing: 0.2mm;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .results-table tbody tr:nth-child(even) {
            background-color: <?= $lightBorderColor ?>; /* Dynamic lighter shade */
        }

        .results-table tbody td {
            color: #1A1A1A; /* Default for readability */
        }

        .results-table .grade {
            font-weight: 600;
            color: #8B4513; /* Static for consistency */
        }

        .results-table tfoot tr {
            border-top: 1pt solid <?= $borderColor ?>; /* Dynamic border color */
        }

        .results-table tfoot th {
            background: #FFF8F3 !important; /* Static for aesthetics */
            color: #1A1A1A !important; /* Default for readability */
            font-weight: 600;
        }

        .skills-container {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 3mm;
            margin: 3mm 0;
        }

        .skills-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8.5pt;
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1); /* Enhanced shadow */
        }

        .skills-table th, .skills-table td {
            padding: 1.5mm;
            border: 0.5pt solid <?= $borderColor ?>; /* Dynamic border color */
            text-align: left;
        }

        .skills-table th {
            background: linear-gradient(135deg, <?= $borderColor ?>, <?= lightenColor($borderColor, 0.2) ?>) !important; /* Dynamic gradient */
            color: white !important; /* Default for readability */
            font-weight: 600;
            text-transform: uppercase;
            font-size: 8pt;
            letter-spacing: 0.2mm;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .comments {
            margin: 3mm 0;
            font-size: 9pt;
            margin-bottom: 15mm; /* Kept to fit signatures */
        }

        .comments h4 {
            margin: 1mm 0;
            font-size: 9pt;
            font-weight: bold;
        }

        .comment-line {
            border-bottom: 1pt solid #333;
            width: 100%;
            height: 6mm;
            margin-bottom: 2mm;
        }

        .signatures {
            position: absolute;
            bottom: 8mm;
            left: 10mm;
            right: 10mm;
            display: flex;
            justify-content: space-between;
            font-size: 8.5pt;
        }

        .signature-line {
            width: 50mm;
            text-align: center;
        }

        .signature-line .line {
            width: 100%;
            border-bottom: 1pt solid #333;
            margin-bottom: 1mm;
            height: 5mm;
        }

        .signature-line p {
            margin: 0;
        }

        .download-button {
            position: fixed;
 Milwaukee, WI 53201-0701
            top: 10px;
            right: 10px;
            font-size: 8pt;
            padding: 2px 5px;
            background-color: <?= $borderColor ?>; /* Dynamic button color */
            color: white;
            border: none;
            border-radius: 3px;
            cursor: pointer;
        }

        .download-button:hover {
            background-color: <?= lightenColor($borderColor, -0.2) ?>; /* Darker shade on hover */
        }

        @media print {
            body {
                background: none;
            }
            .report-card {
                margin: 0;
                border: 12px solid <?= $borderColor ?>;
                height: 297mm;
            }
            .download-button {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="report-card" id="report-card">
        <button onclick="downloadPDF()" class="download-button">Download PDF</button>
        
        <div class="school-header">
            <?php if ($schoolDetails && !empty($schoolDetails['logo_path'])): ?>
                <img src="<?= '../' . htmlspecialchars($schoolDetails['logo_path']) ?>" alt="School Logo" class="school-logo">
            <?php endif; ?>
            
            <h2><?= $schoolDetails ? htmlspecialchars($schoolDetails['name']) : 'School MIS' ?></h2>
            <p><?= $schoolDetails ? htmlspecialchars($schoolDetails['address']) : '' ?></p>
            <h3>Student Report Card</h3>
        </div>
        
        <div class="student-info">
            <div>
                <p><strong>Name:</strong> <?= htmlspecialchars($student['name']) ?></p>
                <p><strong>Admission No:</strong> <?= htmlspecialchars($student['admission_number']) ?></p>
            </div>
            <div>
                <p><strong>Class:</strong> <?= htmlspecialchars($result['class']['class_name']) ?></p>
                <p><strong>Term:</strong> <?= getTerm($exam['term']) ?></p>
                <p><strong>Session:</strong> <?= htmlspecialchars($exam['session']) ?></p>
            </div>
        </div>

        <table class="results-table">
            <thead>
                <tr>
                    <th>Subject</th>
                    <?php
                    foreach ($result['components'] as $component):
                        if ($component['is_enabled']):
                    ?>
                        <th><?= htmlspecialchars($component['name']) ?><br>(<?= $component['max_marks'] ?>)</th>
                    <?php 
                        endif;
                    endforeach; 
                    ?>
                    <th>Total</th>
                    <th>Grade</th>
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($result['subjects'] as $subjectId => $subject): ?>
                    <tr>
                        <td><?= htmlspecialchars($subject['name']) ?></td>
                        <?php
                        foreach ($result['components'] as $component):
                            if ($component['is_enabled']):
                                $score = $result['scores'][$subjectId][$component['id']] ?? 0;
                        ?>
                            <td><?= $score ?></td>
                        <?php 
                            endif;
                        endforeach; 
                        ?>
                        <td><strong><?= $subject['total'] ?>/<?= $subject['max_total'] ?></strong></td>
                        <td class="grade"><?= $subject['grade'] ?></td>
                        <td><?= $subject['remarks'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th>Overall Total</th>
                    <?php 
                    foreach ($result['components'] as $component):
                        if ($component['is_enabled']):
                    ?>
                        <th></th>
                    <?php 
                        endif;
                    endforeach; 
                    ?>
                    <th><?= $result['totals']['obtained'] ?>/<?= $result['totals']['maximum'] ?> (<?= $result['totals']['percentage'] ?>%)</th>
                    <th><?= calculateGrade($result['totals']['obtained'], $result['totals']['maximum']) ?></th>
                    <th></th>
                </tr>
            </tfoot>
        </table>
        
        <div class="skills-container">
            <table class="skills-table">
                <thead>
                    <tr>
                        <th colspan="2">Psychomotor Skills</th>
                    </tr>
                    <tr>
                        <th>Skill</th>
                        <th>Rating (out of 5)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($psychomotorSkills as $skill): ?>
                    <tr>
                        <td><?= htmlspecialchars($skill) ?></td>
                        <td>_____/5</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <table class="skills-table">
                <thead>
                    <tr>
                        <th colspan="2">Character Traits</th>
                    </tr>
                    <tr>
                        <th>Trait</th>
                        <th>Rating (out of 5)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($traits as $trait): ?>
                    <tr>
                        <td><?= htmlspecialchars($trait) ?></td>
                        <td>_____/5</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="comments">
            <div>
                <h4>Class Teacher's Comment:</h4>
                <div class="comment-line"></div>
            </div>
            
            <div>
                <h4>Principal's Comment:</h4>
                <div class="comment-line"></div>
            </div>
        </div>
        
        <div class="signatures">
            <div class="signature-line">
                <div class="line"></div>
                <p>Class Teacher's Signature</p>
            </div>
            <div class="signature-line">
                <div class="line"></div>
                <p>Principal's Signature</p>
            </div>
            <div class="signature-line">
                <div class="line"></div>
                <p>Parent's Signature</p>
            </div>
        </div>
    </div>

    <script>
        function downloadPDF() {
            const element = document.getElementById('report-card');
            const opt = {
                margin: 0,
                filename: `Result_${<?= json_encode(htmlspecialchars($student['name'])) ?>}_${<?= json_encode(htmlspecialchars($exam['session'])) ?>}.pdf`,
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 3, useCORS: true },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };

            // Hide the download button during PDF generation
            const downloadButton = document.querySelector('.download-button');
            downloadButton.style.display = 'none';

            // Generate and download PDF
            html2pdf().set(opt).from(element).save().then(() => {
                // Restore the button after generation
                downloadButton.style.display = 'block';
            });
        }
    </script>
</body>
</html>