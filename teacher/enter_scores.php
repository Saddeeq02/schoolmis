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

// Check if exam_id and class_id are provided
if (!isset($_GET['exam_id']) || !isset($_GET['class_id'])) {
    $_SESSION['message'] = 'Invalid request. Missing exam or class information.';
    $_SESSION['message_type'] = 'alert-error';
    header("Location: exams_list.php");
    exit();
}

$examId = $_GET['exam_id'];
$classId = $_GET['class_id'];

// Get exam details
$exam = getExamById($pdo, $examId);
if (!$exam) {
    $_SESSION['message'] = 'Exam not found.';
    $_SESSION['message_type'] = 'alert-error';
    header("Location: exams_list.php");
    exit();
}

// Get subjects taught by this teacher for this class
$subjects = getTeacherSubjectsForClass($pdo, $teacherId, $classId);
if (!$subjects || count($subjects) == 0) {
    $_SESSION['message'] = 'You do not teach any subjects for this class.';
    $_SESSION['message_type'] = 'alert-error';
    header("Location: exams_list.php");
    exit();
}

// Get selected subject from GET parameters or use the first subject
$selectedSubjectId = $_GET['subject_id'] ?? $subjects[0]['subject_id'];

// Verify that the teacher teaches this subject
$validSubject = false;
$subjectName = '';
foreach ($subjects as $subject) {
    if ($subject['subject_id'] == $selectedSubjectId) {
        $validSubject = true;
        $subjectName = $subject['subject_name'];
        break;
    }
}

if (!$validSubject) {
    $_SESSION['message'] = 'You are not authorized to enter scores for this subject.';
    $_SESSION['message_type'] = 'alert-error';
    header("Location: exams_list.php");
    exit();
}

// Get exam components
$components = getExamComponents($pdo, $examId);
if (!$components || count($components) == 0) {
    $_SESSION['message'] = 'No exam components found for this exam.';
    $_SESSION['message_type'] = 'alert-error';
    header("Location: exams_list.php");
    exit();
}

// Get students in this class
$students = getStudentsByClass($pdo, $classId);
if (!$students || count($students) == 0) {
    $_SESSION['message'] = 'No students found in this class.';
    $_SESSION['message_type'] = 'alert-error';
    header("Location: exams_list.php");
    exit();
}

// Get existing scores for all components
$existingScores = [];
foreach ($students as $student) {
    $existingScores[$student['id']] = [];
    foreach ($components as $component) {
        $scoreId = scoreExists($pdo, $student['id'], $examId, $selectedSubjectId, $component['id']);
        if ($scoreId) {
            $stmt = $pdo->prepare("SELECT score FROM student_scores WHERE id = :id");
            $stmt->bindParam(':id', $scoreId, PDO::PARAM_INT);
            $stmt->execute();
            $score = $stmt->fetchColumn();
            $existingScores[$student['id']][$component['id']] = $score;
        }
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_scores'])) {
    $success = true;
    $message = 'Scores saved successfully.';
    
    foreach ($students as $student) {
        foreach ($components as $component) {
            $scoreKey = "score_{$student['id']}_{$component['id']}";
            
            if (isset($_POST[$scoreKey]) && $_POST[$scoreKey] !== '') {
                $score = $_POST[$scoreKey];
                
                // Validate score
                if (!is_numeric($score) || $score < 0 || $score > $component['max_marks']) {
                    $success = false;
                    $message = "Invalid score for student {$student['name']} in {$component['name']}. Score must be between 0 and {$component['max_marks']}.";
                    break 2; // Break both loops
                }
                
                $result = saveStudentScore($pdo, $student['id'], $examId, $selectedSubjectId, $component['id'], $score, $teacherId);
                if (!$result['success']) {
                    $success = false;
                    $message = $result['message'];
                    break 2; // Break both loops
                }
            }
        }
    }
    
    $_SESSION['message'] = $message;
    $_SESSION['message_type'] = $success ? 'alert-success' : 'alert-error';
    
    // Redirect to the same page to prevent form resubmission
    header("Location: enter_scores.php?exam_id=$examId&class_id=$classId&subject_id=$selectedSubjectId");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Enter Scores</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/styles.css">
    <style>
        .score-input {
            width: 50px;
            text-align: center;
            padding: 5px;
        }
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
        .component-header {
            font-size: 0.9em;
        }
        .max-marks {
            display: block;
            font-size: 0.8em;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Enter Scores</h2>
        <h3><?= htmlspecialchars($exam['title']) ?> - <?= htmlspecialchars($exam['session']) ?></h3>
        
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert <?= $_SESSION['message_type'] ?>">
                <?= $_SESSION['message'] ?>
            </div>
            <?php unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
        <?php endif; ?>
        
        <div class="subject-selector">
            <form method="get" action="">
                <input type="hidden" name="exam_id" value="<?= $examId ?>">
                <input type="hidden" name="class_id" value="<?= $classId ?>">
                
                <div class="form-group">
                    <label for="subject_id">Subject:</label>
                    <select name="subject_id" id="subject_id" onchange="this.form.submit()">
                        <?php foreach ($subjects as $subject): ?>
                            <option value="<?= $subject['subject_id'] ?>" <?= $selectedSubjectId == $subject['subject_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($subject['subject_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>
        
        <form method="post" action="">
            <div class="table-responsive">
                <table class="score-table">
                    <thead>
                        <tr>
                            <th>Admission No.</th>
                            <th>Student Name</th>
                            <?php foreach ($components as $component): ?>
                                <th class="component-header">
                                    <?= htmlspecialchars($component['name']) ?>
                                    <span class="max-marks">Max: <?= $component['max_marks'] ?></span>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): ?>
                            <tr>
                                <td><?= htmlspecialchars($student['admission_number']) ?></td>
                                <td><?= htmlspecialchars($student['name']) ?></td>
                                <?php foreach ($components as $component): ?>
                                    <td>
                                        <input 
                                            type="number" 
                                            name="score_<?= $student['id'] ?>_<?= $component['id'] ?>" 
                                            class="score-input" 
                                            min="0" 
                                            max="<?= $component['max_marks'] ?>" 
                                            value="<?= isset($existingScores[$student['id']][$component['id']]) ? $existingScores[$student['id']][$component['id']] : '' ?>"
                                        >
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="mt-4">
                <button type="submit" name="save_scores" class="btn">Save All Scores</button>
                <a href="view_scores.php?exam_id=<?= $examId ?>&class_id=<?= $classId ?>&subject_id=<?= $selectedSubjectId ?>" class="btn">View Scores</a>
                <a href="exams_list.php" class="btn">Back to Exams List</a>
            </div>
        </form>
    </div>
</body>
</html>
