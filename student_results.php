<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';

$error = '';
$student = null;
$exams = [];
$selectedExam = null;

// Check if admission number is submitted
if (isset($_POST['admission_number']) && !empty($_POST['admission_number'])) {
    $admissionNumber = trim($_POST['admission_number']);
    
    // Get student by admission number
    $student = getStudentByAdmissionNumber($pdo, $admissionNumber);
    
    if (!$student) {
        $error = "No student found with admission number: $admissionNumber";
    } else {
        // Get exams for the student's class
        $exams = getExamsByClass($pdo, $student['class_id']);
        
        // If exam_id is provided, get the selected exam
        if (isset($_POST['exam_id']) && !empty($_POST['exam_id'])) {
            $examId = (int)$_POST['exam_id'];
            $selectedExam = getExamById($pdo, $examId);
        } elseif (!empty($exams)) {
            // Default to the most recent exam
            $selectedExam = $exams[0];
        }
    }
}

// Get school details from schools table
if ($student) {
    $stmt = $pdo->prepare("SELECT name, address, border_color, logo_path FROM schools WHERE id = ?");
    $stmt->execute([$student['school_id']]);
    $schoolDetails = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    $schoolDetails = null;
}

// Fallback values
$borderColor = $schoolDetails['border_color'] ?? '#3366cc';
$logoPath = $schoolDetails['logo_path'] ?? '';

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

// Define psychomotor skills and traits
$psychomotorSkills = ['Handwriting', 'Reading Skills', 'Drawing', 'Crafts', 'Sports'];
$traits = ['Punctuality', 'Neatness', 'Leadership', 'Honesty', 'Cooperation'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Results</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        @page {
            size: A4 portrait;
            margin: 0;
        }

        body {
            font-family: Arial, sans-serif;
            line-height: 1.2; /* Tightened line height */
            color: #333;
            background-color: #fff;
            margin: 0;
            font-size: 8pt; /* Further reduced */
        }

        .container {
            padding: 10mm;
            max-width: 100%;
            box-sizing: border-box;
        }

        h1 {
            font-size: 13pt; /* Smaller */
            text-align: center;
            margin-bottom: 4mm; /* Reduced */
        }

        .form-group {
            margin-bottom: 4mm; /* Reduced */
        }

        .form-group label {
            display: block;
            margin-bottom: 1mm;
            font-size: 8pt;
        }

        .form-group input, .form-group select {
            width: 100%;
            padding: 1.5mm; /* Reduced */
            font-size: 8pt;
            border: 1px solid #ddd;
            border-radius: 3px;
            box-sizing: border-box;
        }

        .btn {
            display: block;
            margin: 4mm auto; /* Reduced */
            padding: 1.5mm 4mm; /* Reduced */
            background-color: <?= $borderColor ?>;
            color: white;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-size: 8pt;
        }

        .btn:hover {
            background-color: <?= lightenColor($borderColor, -0.2) ?>;
        }

        .alert-error {
            color: #721c24;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            padding: 2mm; /* Reduced */
            margin-bottom: 4mm;
            border-radius: 3px;
            font-size: 8pt;
        }

        .report-card {
            width: 210mm;
            height: 297mm; /* Fixed A4 height */
            margin: 0 auto;
            border: 12px solid <?= $borderColor ?>;
            background-color: white;
            padding: 7mm; /* Further reduced */
            box-sizing: border-box;
            position: relative;
            overflow: hidden; /* Prevent overflow */
        }

        .school-header {
            text-align: center;
            margin-bottom: 3mm; /* Reduced */
            padding-bottom: 1.5mm; /* Reduced */
            border-bottom: 1px solid <?= $borderColor ?>;
        }

        .school-header h2 {
            margin: 0.5mm 0; /* Reduced */
            font-size: 12pt; /* Smaller */
        }

        .school-header h3 {
            margin: 0.5mm 0;
            font-size: 10pt; /* Smaller */
        }

        .school-header p {
            margin: 0.5mm 0;
            font-size: 8pt;
        }

        .school-logo {
            max-width: 35px; /* Smaller */
            max-height: 35px;
            margin-bottom: 1.5mm; /* Reduced */
        }

        .student-info {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5mm; /* Reduced */
            margin-bottom: 2mm; /* Reduced */
            font-size: 8pt;
        }

        .student-info p {
            margin: 0.3mm 0; /* Reduced */
        }

        .results-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 1.5mm; /* Reduced */
            font-size: 7.5pt; /* Smaller */
            background: white;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1); /* Lighter shadow */
        }

        .results-table th, .results-table td {
            padding: 1.2mm; /* Further reduced */
            border: 0.5pt solid <?= $borderColor ?>;
            text-align: left;
        }

        .results-table th {
            background: linear-gradient(135deg, <?= $borderColor ?>, <?= lightenColor($borderColor, 0.2) ?>) !important;
            color: white !important;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 7pt; /* Smaller */
            letter-spacing: 0.2mm;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .results-table tbody tr:nth-child(even) {
            background-color: <?= $lightBorderColor ?>;
        }

        .results-table tbody td {
            color: #1A1A1A;
        }

        .results-table .grade {
            font-weight: 600;
            color: #8B4513;
        }

        .results-table tfoot tr {
            border-top: 1pt solid <?= $borderColor ?>;
        }

        .results-table tfoot th {
            background: #FFF8F3 !important;
            color: #1A1A1A !important;
            font-weight: 600;
        }

        .position {
            margin: 1.5mm 0; /* Reduced */
            font-size: 8pt;
        }

        .skills-container {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5mm; /* Reduced */
            margin: 1.5mm 0; /* Reduced */
        }

        .skills-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 7pt; /* Smaller */
            background: white;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .skills-table th, .skills-table td {
            padding: 0.8mm; /* Further reduced */
            border: 0.5pt solid <?= $borderColor ?>;
            text-align: left;
        }

        .skills-table th {
            background: linear-gradient(135deg, <?= $borderColor ?>, <?= lightenColor($borderColor, 0.2) ?>) !important;
            color: white !important;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 6.5pt; /* Smaller */
            letter-spacing: 0.2mm;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .comments {
            margin: 1.5mm 0; /* Reduced */
            font-size: 8pt;
            margin-bottom: 10mm; /* Further reduced */
        }

        .comments h4 {
            margin: 0.3mm 0; /* Reduced */
            font-size: 8pt;
            font-weight: bold;
        }

        .comment-line {
            border-bottom: 1pt solid #333;
            width: 100%;
            height: 4mm; /* Further reduced */
            margin-bottom: 0.5mm; /* Reduced */
        }

        .signatures {
            position: absolute;
            bottom: 7mm; /* Adjusted */
            left: 7mm;
            right: 7mm;
            display: flex;
            justify-content: space-between;
            font-size: 7.5pt; /* Smaller */
        }

        .signature-line {
            width: 50mm;
            text-align: center;
        }

        .signature-line .line {
            width: 100%;
            border-bottom: 1pt solid #333;
            margin-bottom: 0.3mm; /* Reduced */
            height: 3.5mm; /* Further reduced */
        }

        .signature-line p {
            margin: 0;
            font-size: 7.5pt;
        }

        .download-button {
            position: fixed;
            top: 10px;
            right: 10px;
            font-size: 8pt;
            padding: 1.5mm 4mm; /* Reduced */
            background-color: <?= $borderColor ?>;
            color: white;
            border: none;
            border-radius: 3px;
            cursor: pointer;
        }

        .download-button:hover {
            background-color: <?= lightenColor($borderColor, -0.2) ?>;
        }

        /* Mobile Responsiveness */
        @media screen and (max-width: 600px) {
            .container {
                padding: 5mm;
            }

            .report-card {
                width: 100%;
                height: auto; /* Allow height to adjust */
                border-width: 6px;
                padding: 4mm; /* Reduced */
            }

            .student-info {
                grid-template-columns: 1fr; /* Stack vertically */
            }

            .results-table, .skills-table {
                font-size: 6.5pt; /* Smaller */
            }

            .results-table th, .results-table td,
            .skills-table th, .skills-table td {
                padding: 0.6mm; /* Reduced */
            }

            .skills-container {
                grid-template-columns: 1fr; /* Stack tables */
                gap: 1mm;
            }

            .signatures {
                flex-direction: column;
                gap: 1.5mm;
                position: static;
                margin-top: 3mm;
            }

            .signature-line {
                width: 100%;
            }

            .form-group input, .form-group select {
                font-size: 6.5pt;
            }

            .btn, .download-button {
                font-size: 6.5pt;
                padding: 1mm 3mm; /* Reduced */
            }
        }

        @media print {
            body {
                background: none;
            }
            .container, form, .download-button {
                display: none; /* Hide form and button in PDF */
            }
            .report-card {
                display: block !important;
                margin: 0;
                border: 12px solid <?= $borderColor ?>;
                height: 297mm;
                padding: 7mm;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Student Results Portal</h1>
        
        <form method="post" action="">
            <div class="form-group">
                <label for="admission_number">Enter Admission Number:</label>
                <input type="text" id="admission_number" name="admission_number" 
                       value="<?php echo isset($_POST['admission_number']) ? htmlspecialchars($_POST['admission_number']) : ''; ?>" 
                       required>
            </div>
            
            <?php if ($student && !empty($exams)): ?>
                <div class="form-group">
                    <label for="exam_id">Select Exam:</label>
                    <select id="exam_id" name="exam_id">
                        <?php foreach ($exams as $exam): ?>
                            <option value="<?php echo $exam['id']; ?>" 
                                <?php echo (isset($_POST['exam_id']) && $_POST['exam_id'] == $exam['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($exam['title'] . ' - ' . $exam['session'] . ' (' . getTerm($exam['term']) . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            
            <button type="submit" class="btn">View Results</button>
        </form>
        
        <?php if ($error): ?>
            <div class="alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($student && $selectedExam): ?>
            <div class="report-card" id="report-card">
                <button onclick="downloadPDF()" class="download-button">Download PDF</button>
                
                <div class="school-header">
                    <?php if ($schoolDetails && !empty($logoPath)): ?>
                        <img src="<?= '../' . htmlspecialchars($logoPath) ?>" alt="School Logo" class="school-logo">
                    <?php endif; ?>
                    
                    <h2><?= $schoolDetails ? htmlspecialchars($schoolDetails['name']) : 'School MIS' ?></h2>
                    <p><?= $schoolDetails ? htmlspecialchars($schoolDetails['address']) : '' ?></p>
                    <h3>Student Result Sheet</h3>
                </div>
                
                <div class="student-info">
                    <div>
                        <p><strong>Name:</strong> <?php echo htmlspecialchars($student['name']); ?></p>
                        <p><strong>Admission No:</strong> <?php echo htmlspecialchars($student['admission_number']); ?></p>
                    </div>
                    <div>
                        <p><strong>Class:</strong> <?php 
                            $class = getClassById($pdo, $student['class_id']);
                            echo $class ? htmlspecialchars($class['class_name']) : 'Unknown';
                        ?></p>
                        <p><strong>Exam:</strong> <?php echo htmlspecialchars($selectedExam['title']); ?></p>
                        <p><strong>Term:</strong> <?php echo getTerm($selectedExam['term']); ?></p>
                        <p><strong>Session:</strong> <?php echo htmlspecialchars($selectedExam['session']); ?></p>
                    </div>
                </div>
                
                <table class="results-table">
                    <thead>
                        <tr>
                            <th>Subject</th>
                            <?php
                            $components = getExamComponents($pdo, $selectedExam['id']);
                            foreach ($components as $component):
                                if ($component['is_enabled']):
                            ?>
                                <th><?php echo htmlspecialchars($component['name']); ?> (<?php echo $component['max_marks']; ?>)</th>
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
                        <?php
                        $subjects = getSubjectsByClass($pdo, $student['class_id']);
                        $totalMarks = 0;
                        $totalMaxMarks = 0;
                        $subjectCount = 0;
                        
                        foreach ($subjects as $subject):
                            $subjectTotal = 0;
                            $subjectMaxTotal = 0;
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($subject['subject_name']); ?></td>
                                <?php
                                foreach ($components as $component):
                                    if ($component['is_enabled']):
                                        $score = getStudentComponentScore($pdo, $student['id'], $selectedExam['id'], $subject['id'], $component['id']);
                                        $subjectTotal += $score;
                                        $subjectMaxTotal += $component['max_marks'];
                                ?>
                                    <td><?php echo $score; ?></td>
                                <?php 
                                    endif;
                                endforeach; 
                                
                                $grade = calculateGrade($subjectTotal, $subjectMaxTotal);
                                $remarks = getRemarks($grade);
                                
                                $totalMarks += $subjectTotal;
                                $totalMaxMarks += $subjectMaxTotal;
                                $subjectCount++;
                                ?>
                                <td><strong><?php echo $subjectTotal; ?>/<?php echo $subjectMaxTotal; ?></strong></td>
                                <td class="grade"><?php echo $grade; ?></td>
                                <td><?php echo $remarks; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th>Overall Total</th>
                            <?php 
                            foreach ($components as $component):
                                if ($component['is_enabled']):
                            ?>
                                <th></th>
                            <?php 
                                endif;
                            endforeach; 
                            ?>
                            <th><?php echo $totalMarks; ?>/<?php echo $totalMaxMarks; ?> (<?php echo round(($totalMarks / $totalMaxMarks) * 100, 1); ?>%)</th>
                            <th><?php echo calculateGrade($totalMarks, $totalMaxMarks); ?></th>
                            <th></th>
                        </tr>
                    </tfoot>
                </table>
                
                <div class="position">
                    <h4>Position in Class: <?php echo getStudentPosition($pdo, $student['id'], $selectedExam['id'], $student['class_id']); ?></h4>
                </div>
                
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
                                <td><?php echo htmlspecialchars($skill); ?></td>
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
                                <td><?php echo htmlspecialchars($trait); ?></td>
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
        <?php endif; ?>
    </div>

    <script>
        function downloadPDF() {
            const downloadButton = document.querySelector('.download-button');
            downloadButton.innerHTML = 'Generating...';
            downloadButton.disabled = true;
            
            const element = document.getElementById('report-card');
            const opt = {
                margin: 0,
                filename: `Result_${<?php echo json_encode(htmlspecialchars($student['name'] ?? 'Student')); ?>}_${<?php echo json_encode(htmlspecialchars($selectedExam['session'] ?? 'Exam')); ?>}.pdf`,
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 4, useCORS: true },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };

            // Generate and download PDF
            html2pdf().set(opt).from(element).save().then(() => {
                downloadButton.innerHTML = 'Download PDF';
                downloadButton.disabled = false;
                downloadButton.style.display = 'block';
            });
        }
    </script>
</body>
</html>