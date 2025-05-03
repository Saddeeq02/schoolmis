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

$borderColor = $schoolDetails['border_color'] ?? '#3366cc';

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
            height: 297mm; /* Fixed height to A4 */
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
        }

        .results-table th, .results-table td {
            padding: 1mm;
            border: 0.5pt solid #ddd;
            text-align: left;
        }

        .results-table th {
            background-color: <?= $borderColor ?> !important;
            color: white !important;
            font-weight: bold;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
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
        }

        .skills-table th, .skills-table td {
            padding: 1mm;
            border: 0.5pt solid #ddd;
            text-align: left;
        }

        .skills-table th {
            background-color: <?= $borderColor ?> !important;
            color: white !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .comments {
            margin: 3mm 0;
            font-size: 9pt;
            margin-bottom: 25mm; /* Reduced space before signatures */
        }

        .comments h4 {
            margin: 1mm 0;
            font-size: 9pt;
            font-weight: bold;
        }

        .comment-line {
            border-bottom: 0.5pt solid #ddd;
            height: 6mm;
            margin-bottom: 3mm;
        }

        .signatures {
            position: absolute;
            bottom: 6mm;
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
            border-bottom: 0.5pt solid #333;
            margin-bottom: 1mm;
            height: 10mm;
        }

        .signature-line p {
            margin: 0;
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
            .print-button {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="report-card">
        <button onclick="window.print()" class="print-button" style="position: fixed; top: 10px; right: 10px; font-size: 8pt; padding: 2px 5px;">Print</button>
        
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
</body>
</html>