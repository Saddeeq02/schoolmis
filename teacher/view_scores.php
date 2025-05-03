<?php
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
    <link rel="stylesheet" href="../assets/clean-styles.css">
    <style>
        .exam-header {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .exam-header h1 {
            margin: 0;
            font-size: 1.5rem;
            color: #333;
        }

        .exam-header .exam-meta {
            margin-top: 0.5rem;
            display: flex;
            gap: 1.5rem;
            flex-wrap: wrap;
            color: #666;
        }

        .exam-meta span {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .subject-selector {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .form-select {
            width: 100%;
            max-width: 300px;
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            background-color: white;
        }

        .scores-card {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            overflow-x: auto;
        }

        .score-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin: 0;
        }

        .score-table th, 
        .score-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .score-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #333;
            position: sticky;
            top: 0;
            z-index: 1;
        }

        .score-table td {
            vertical-align: middle;
        }

        .score-value {
            font-family: monospace;
            font-size: 1.1em;
            text-align: center;
            min-width: 60px;
            display: inline-block;
        }

        .total-column {
            font-weight: 600;
            background-color: #f0f7ff;
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
            justify-content: flex-end;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn-primary {
            background: #007bff;
            color: white;
        }

        .btn-outline {
            border: 1px solid #ddd;
        }

        .sticky-header {
            position: sticky;
            top: 0;
            background: #f8f9fa;
            z-index: 10;
            padding: 1rem;
            margin: -1.5rem -1.5rem 1rem;
            border-bottom: 1px solid #eee;
            border-radius: 8px 8px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
        }

        @media screen and (max-width: 768px) {
            .score-table {
                display: block;
            }

            .score-table thead {
                display: none;
            }

            .score-table tbody {
                display: block;
            }

            .score-table tr {
                display: block;
                margin-bottom: 1rem;
                padding: 1rem;
                background: #f8f9fa;
                border-radius: 4px;
            }

            .score-table td {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 0.5rem 0;
                border: none;
            }

            .score-table td::before {
                content: attr(data-label);
                font-weight: 600;
                margin-right: 1rem;
            }

            .score-value {
                text-align: right;
            }

            .action-buttons {
                justify-content: flex-start;
                margin-top: 1rem;
            }

            .btn span {
                display: none;
            }

            .exam-meta {
                flex-direction: column;
                gap: 0.5rem;
            }
        }

        @media print {
            .no-print {
                display: none !important;
            }

            .scores-card {
                box-shadow: none;
                padding: 0;
            }

            .score-table th {
                background-color: white !important;
                color: black !important;
            }

            .total-column {
                background-color: white !important;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="exam-header">
            <h1><?= htmlspecialchars($exam['title']) ?></h1>
            <div class="exam-meta">
                <span><i class="fas fa-calendar"></i> <?= htmlspecialchars($exam['session']) ?></span>
                <span><i class="fas fa-book"></i> <?= htmlspecialchars($subjectName) ?></span>
                <span><i class="fas fa-clock"></i> Term <?= htmlspecialchars($exam['term']) ?></span>
            </div>
        </div>

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
                    <label for="subject_id">Select Subject:</label>
                    <select name="subject_id" id="subject_id" class="form-select" onchange="this.form.submit()">
                        <?php foreach ($subjects as $subject): ?>
                            <option value="<?= $subject['subject_id'] ?>" <?= $subjectId == $subject['subject_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($subject['subject_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>

        <div class="scores-card">
            <div class="sticky-header no-print">
                <div class="action-buttons">
                    <a href="exams_list.php" class="btn btn-outline">
                        <i class="fas fa-arrow-left"></i>
                        <span>Back</span>
                    </a>
                    <a href="enter_scores.php?exam_id=<?= $examId ?>&class_id=<?= $classId ?>&subject_id=<?= $subjectId ?>" class="btn btn-outline">
                        <i class="fas fa-edit"></i>
                        <span>Edit Scores</span>
                    </a>
                    <button onclick="window.print()" class="btn btn-primary">
                        <i class="fas fa-print"></i>
                        <span>Print Scores</span>
                    </button>
                </div>
            </div>

            <table class="score-table">
                <thead>
                    <tr>
                        <th>Student Details</th>
                        <?php foreach ($components as $component): ?>
                            <th><?= htmlspecialchars($component['name']) ?> <small>(<?= $component['max_marks'] ?>)</small></th>
                        <?php endforeach; ?>
                        <th class="total-column">Total <small>(<?= $maxTotal ?>)</small></th>
                        <th class="no-print">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $student): ?>
                        <tr>
                            <td data-label="Student">
                                <strong><?= htmlspecialchars($student['name']) ?></strong>
                                <small class="d-block text-muted"><?= htmlspecialchars($student['admission_number']) ?></small>
                            </td>
                            <?php foreach ($components as $component): ?>
                                <td data-label="<?= htmlspecialchars($component['name']) ?>">
                                    <span class="score-value">
                                        <?= isset($allScores[$student['id']][$component['id']]) ? $allScores[$student['id']][$component['id']] : '-' ?>
                                    </span>
                                </td>
                            <?php endforeach; ?>
                            <td class="total-column" data-label="Total">
                                <span class="score-value"><?= $totals[$student['id']] ?></span>
                            </td>
                            <td class="no-print" data-label="Actions">
                                <div class="action-buttons">
                                    <a href="print_result.php?student_id=<?= $student['id'] ?>&exam_id=<?= $examId ?>" 
                                       class="btn btn-outline" target="_blank">
                                        <i class="fas fa-print"></i>
                                        <span>Print Result</span>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
