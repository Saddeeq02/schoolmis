<?php
session_start();
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

// Get result data
$result = getStudentExamResult($pdo, $studentId, $examId);

if (!$result) {
    $_SESSION['message'] = 'Failed to generate result';
    $_SESSION['message_type'] = 'alert-error';
    header("Location: exams_list.php");
    exit();
}

// Get school details
$schoolName = $_SESSION['school_name'] ?? 'default';
$schoolDetails = getSchoolDetails($pdo, $schoolName);
$borderColor = $schoolDetails['border_color'] ?? '#3366cc';

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
        body {
            font-family: Arial, sans-serif;
            line-height: 1.4;
            color: #333;
            background-color: #f9f9f9;
            padding: 0;
            margin: 0;
            font-size: 11px; /* Smaller base font size to fit more content */
        }
        .report-card {
            max-width: 800px;
            margin: 20px auto;
            border: 10px solid <?= $borderColor ?>;
            background-color: white;
            padding: 15px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .school-header {
            text-align: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid <?= $borderColor ?>;
        }
        .school-logo {
            max-width: 80px;
            max-height: 80px;
            margin-bottom: 5px;
        }
        .student-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        .student-info div {
            margin-bottom: 5px;
        }
        .results-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            font-size: 10px;
        }
        .results-table th, .results-table td {
            padding: 5px;
            border: 1px solid #ddd;
            text-align: left;
        }
        .results-table th {
            background-color: <?= $borderColor ?> !important;
            color: white !important;
            font-weight: bold;
        }
        .results-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .grade {
            font-weight: bold;
        }
        .summary {
            margin-top: 15px;
            padding-top: 5px;
            border-top: 1px solid #ddd;
        }
        .comments {
            margin: 15px 0;
        }
        .signatures {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
        }
        .signature-line {
            width: 40%;
            border-top: 1px solid #333;
            padding-top: 5px;
            text-align: center;
        }
        .skills-container {
            display: flex;
            justify-content: space-between;
            margin: 15px 0;
            gap: 20px;
        }
        .skills-table {
            width: 48%;
            border-collapse: collapse;
            font-size: 10px;
        }
        .skills-table th, .skills-table td {
            padding: 5px;
            border: 1px solid #ddd;
            text-align: left;
        }
        .skills-table th {
            background-color: <?= $borderColor ?> !important;
            color: white !important;
            font-weight: bold;
        }
        h4, h3, h2 {
            margin: 5px 0;
        }
        @media print {
            body {
                background: none;
                font-size: 11px;
            }
            .report-card {
                margin: 0;
                border: 10px solid <?= $borderColor ?>;
                box-shadow: none;
                page-break-after: always;
            }
            .print-button {
                display: none;
            }
            .results-table th {
                background-color: <?= $borderColor ?> !important;
                color: white !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .skills-table th {
                background-color: <?= $borderColor ?> !important;
                color: white !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>
    <div class="report-card">
        <button onclick="window.print()" class="print-button" style="float:right;">Print Report Card</button>
        
        <div class="school-header">
            <?php if ($schoolDetails && !empty($schoolDetails['logo_path'])): ?>
                <img src="<?= '../' . htmlspecialchars($schoolDetails['logo_path']) ?>" alt="School Logo" class="school-logo">
            <?php endif; ?>
            
            <h2><?= $schoolDetails ? htmlspecialchars($schoolDetails['school_name']) : 'School MIS' ?></h2>
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
                <p><strong>Exam:</strong> <?= htmlspecialchars($exam['title']) ?></p>
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
                        <th><?= htmlspecialchars($component['name']) ?> (<?= $component['max_marks'] ?>)</th>
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
        
        <div class="summary">
            <h4>Position in Class: <?= $result['position'] ?></h4>
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
                <p>_______________________________________________________________________</p>
            </div>
            
            <div>
                <h4>Principal's Comment:</h4>
                <p>_______________________________________________________________________</p>
            </div>
        </div>
        
        <div class="signatures">
            <div class="signature-line">
                <p>Class Teacher</p>
            </div>
            <div class="signature-line">
                <p>Principal</p>
            </div>
        </div>
    </div>
</body>
</html>
