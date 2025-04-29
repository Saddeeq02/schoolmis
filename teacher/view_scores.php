<?php
session_start();
include '../includes/auth.php';
include '../includes/db.php';
include '../includes/functions.php';

if ($_SESSION['role'] != 'teacher') {
    header("Location: ../index.php");
    exit();
}

$teacherId = $_SESSION['user_id'];

// Check if exam_id, class_id, and subject_id are provided
if (!isset($_GET['exam_id']) || !isset($_GET['class_id'])) {
    $_SESSION['message'] = 'Missing required parameters';
    $_SESSION['message_type'] = 'alert-error';
    header("Location: exams_list.php");
    exit();
}

$examId = $_GET['exam_id'];
$classId = $_GET['class_id'];
$subjectId = $_GET['subject_id'] ?? null;

// Get exam details
$exam = getExamById($pdo, $examId);
if (!$exam) {
    $_SESSION['message'] = 'Exam not found';
    $_SESSION['message_type'] = 'alert-error';
    header("Location: exams_list.php");
    exit();
}

// Get subjects taught by this teacher for this class
$subjects = getTeacherSubjectsForClass($pdo, $teacherId, $classId);
if (!$subjects || count($subjects) == 0) {
    $_SESSION['message'] = 'You do not teach any subjects for this class';
    $_SESSION['message_type'] = 'alert-error';
    header("Location: exams_list.php");
    exit();
}

// If subject_id is not provided, use the first subject
if (!$subjectId) {
    $subjectId = $subjects[0]['subject_id'];
}

// Verify that the teacher teaches this subject
$validSubject = false;
$subjectName = '';
foreach ($subjects as $subject) {
    if ($subject['subject_id'] == $subjectId) {
        $validSubject = true;
        $subjectName = $subject['subject_name'];
        break;
    }
}

if (!$validSubject) {
    $_SESSION['message'] = 'You are not authorized to view scores for this subject';
    $_SESSION['message_type'] = 'alert-error';
    header("Location: exams_list.php");
    exit();
}

// Get exam components
$components = getExamComponents($pdo, $examId);
if (!$components || count($components) == 0) {
    $_SESSION['message'] = 'No exam components found for this exam';
    $_SESSION['message_type'] = 'alert-error';
    header("Location: exams_list.php");
    exit();
}

// Get students in this class
$students = getStudentsByClass($pdo, $classId);
if (!$students || count($students) == 0) {
    $_SESSION['message'] = 'No students found in this class';
    $_SESSION['message_type'] = 'alert-error';
    header("Location: exams_list.php");
    exit();
}

// Get all scores for this exam, subject, and all components
$allScores = [];
foreach ($students as $student) {
    $studentScores = getStudentScoresByStudent($pdo, $student['id'], $examId, $subjectId);
    $allScores[$student['id']] = [];
    
    if ($studentScores) {
        foreach ($studentScores as $score) {
            $allScores[$student['id']][$score['component_id']] = $score['score'];
        }
    }
}

// Calculate total scores if needed
$totals = [];
$maxTotal = 0;
foreach ($students as $student) {
    $total = 0;
    foreach ($components as $component) {
        if (isset($allScores[$student['id']][$component['id']])) {
            $total += $allScores[$student['id']][$component['id']];
        }
    }
    $totals[$student['id']] = $total;
    
    // Calculate max possible total
    if ($maxTotal == 0) {
        foreach ($components as $component) {
            $maxTotal += $component['max_marks'];
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>View Scores</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/styles.css">
    <style>
        .score-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .score-table th, .score-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: center;
        }
        .score-table th {
            background-color: #f2f2f2;
        }
        .score-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .subject-selector {
            margin-bottom: 20px;
        }
        .total-column {
            font-weight: bold;
            background-color: #e6f7ff;
        }
        .print-button {
            margin-top: 20px;
            margin-bottom: 20px;
        }
        @media print {
            .no-print {
                display: none;
            }
            body {
                font-size: 12pt;
            }
            .container {
                width: 100%;
                margin: 0;
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>View Scores</h2>
        <h3><?= htmlspecialchars($exam['title']) ?> - <?= htmlspecialchars($exam['session']) ?></h3>
        
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert <?= $_SESSION['message_type'] ?> no-print">
                <?= $_SESSION['message'] ?>
            </div>
            <?php unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
        <?php endif; ?>
        
        <div class="subject-selector no-print">
            <form method="get" action="">
                <input type="hidden" name="exam_id" value="<?= $examId ?>">
                <input type="hidden" name="class_id" value="<?= $classId ?>">
                
                <div class="form-group">
                    <label for="subject_id">Subject:</label>
                    <select name="subject_id" id="subject_id" onchange="this.form.submit()">
                        <?php foreach ($subjects as $subject): ?>
                            <option value="<?= $subject['subject_id'] ?>" <?= $subjectId == $subject['subject_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($subject['subject_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>
        
        <div class="print-button no-print">
            <button onclick="window.print()" class="btn"><i class="fas fa-print"></i> Print Scores</button>
        </div>
        
        <div class="table-responsive">
            <table class="score-table">
                <thead>
                    <tr>
                        <th>Admission No.</th>
                        <th>Student Name</th>
                        <?php foreach ($components as $component): ?>
                            <th><?= htmlspecialchars($component['name']) ?> (<?= $component['max_marks'] ?>)</th>
                        <?php endforeach; ?>
                        <th class="total-column">Total (<?= $maxTotal ?>)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $student): ?>
                        <tr>
                            <td><?= htmlspecialchars($student['admission_number']) ?></td>
                            <td><?= htmlspecialchars($student['name']) ?></td>
                            <?php foreach ($components as $component): ?>
                                <td>
                                    <?= isset($allScores[$student['id']][$component['id']]) ? $allScores[$student['id']][$component['id']] : '-' ?>
                                </td>
                            <?php endforeach; ?>
                            <td class="total-column"><?= $totals[$student['id']] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="mt-4 no-print">
            <a href="enter_scores.php?exam_id=<?= $examId ?>&class_id=<?= $classId ?>&subject_id=<?= $subjectId ?>" class="btn">Edit Scores</a>
            <a href="exams_list.php" class="btn">Back to Exams List</a>
        </div>
    </div>
</body>
</html>
