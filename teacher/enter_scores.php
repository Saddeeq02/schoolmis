<?php
session_start();
include '../includes/auth.php';
include '../includes/db.php';
include '../includes/functions.php';

// Verify teacher role
if ($_SESSION['role'] != 'teacher') {
    header("Location: ../index.php");
    exit();
}

$teacherId = $_SESSION['user_id'];
$schoolId = $_SESSION['school_id'] ?? 1;

// Check if exam_id and class_id are provided
if (!isset($_GET['exam_id']) || !isset($_GET['class_id'])) {
    $_SESSION['message'] = 'Invalid request. Missing exam or class information.';
    $_SESSION['message_type'] = 'alert-danger';
    header("Location: exams_list.php");
    exit();
}

$examId = $_GET['exam_id'];
$classId = $_GET['class_id'];

// Get exam details
$exam = getExamById($pdo, $examId);
if (!$exam) {
    $_SESSION['message'] = 'Exam not found.';
    $_SESSION['message_type'] = 'alert-danger';
    header("Location: exams_list.php");
    exit();
}

// Get subjects taught by this teacher for this class
$subjects = getTeacherSubjectsForClass($pdo, $teacherId, $classId);
if (!$subjects || count($subjects) == 0) {
    $_SESSION['message'] = 'You do not teach any subjects for this class.';
    $_SESSION['message_type'] = 'alert-danger';
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
    $_SESSION['message_type'] = 'alert-danger';
    header("Location: exams_list.php");
    exit();
}

// Process score submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    $success = true;
    
    // Get exam components
    $components = getExamComponents($pdo, $examId);
    
    // Begin transaction
    $pdo->beginTransaction();
    
    try {
        foreach ($_POST['scores'] as $studentId => $componentScores) {
            foreach ($componentScores as $componentId => $score) {
                // Validate score
                if (!is_numeric($score)) {
                    throw new Exception("Invalid score value for student ID: $studentId");
                }
                
                // Get component details for validation
                $component = null;
                foreach ($components as $comp) {
                    if ($comp['id'] == $componentId) {
                        $component = $comp;
                        break;
                    }
                }
                
                if (!$component) {
                    throw new Exception("Invalid component ID: $componentId");
                }
                
                // Validate score range
                if ($score < 0 || $score > $component['max_marks']) {
                    throw new Exception("Score must be between 0 and {$component['max_marks']} for {$component['name']}");
                }
                
                // Save score
                $result = saveStudentScore($pdo, $studentId, $examId, $selectedSubjectId, $componentId, $score, $teacherId);
                if (!$result['success']) {
                    throw new Exception($result['message']);
                }
            }
        }
        
        // Commit transaction if all scores are saved successfully
        $pdo->commit();
        $_SESSION['message'] = 'Scores saved successfully!';
        $_SESSION['message_type'] = 'alert-success';
        
        // Redirect to view scores
        header("Location: view_scores.php?exam_id=$examId&class_id=$classId&subject_id=$selectedSubjectId");
        exit();
        
    } catch (Exception $e) {
        // Rollback transaction if any error occurs
        $pdo->rollBack();
        $_SESSION['message'] = 'Error saving scores: ' . $e->getMessage();
        $_SESSION['message_type'] = 'alert-danger';
    }
}

// Get exam components
$components = getExamComponents($pdo, $examId);
if (!$components || count($components) == 0) {
    $_SESSION['message'] = 'No exam components found for this exam.';
    $_SESSION['message_type'] = 'alert-danger';
    header("Location: exams_list.php");
    exit();
}

// Get students in this class
$students = getStudentsByClass($pdo, $classId);
if (!$students || count($students) == 0) {
    $_SESSION['message'] = 'No students found in this class.';
    $_SESSION['message_type'] = 'alert-danger';
    header("Location: exams_list.php");
    exit();
}

// Get existing scores for all components
$existingScores = [];
foreach ($students as $student) {
    $existingScores[$student['id']] = [];
    foreach ($components as $component) {
        $score = getStudentComponentScore($pdo, $student['id'], $examId, $selectedSubjectId, $component['id']);
        $existingScores[$student['id']][$component['id']] = $score;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Enter Scores</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/clean-styles.css">
    <style>
        .score-input {
            width: 70px;
            text-align: center;
            padding: 8px;
            border-radius: var(--radius);
            border: 1px solid var(--border);
        }
        
        .invalid-score {
            border-color: var(--danger);
            background-color: var(--light);
        }
        
        @media (max-width: 768px) {
            .table-responsive {
                margin: 0 -1rem;
            }
            
            table {
                display: block;
            }
            
            thead {
                display: none;
            }
            
            tbody tr {
                display: block;
                margin-bottom: 1rem;
                border: 1px solid var(--border);
                border-radius: var(--radius);
                padding: 1rem;
                background: var(--white);
            }
            
            td {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 0.5rem 0;
                border: none;
            }
            
            td::before {
                content: attr(data-label);
                font-weight: 600;
                margin-right: 1rem;
            }
            
            .score-input {
                width: 100px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Exam Info Card -->
        <div class="card mb-3">
            <h2 class="mb-2"><?= htmlspecialchars($exam['title']) ?></h2>
            <div class="d-flex flex-wrap gap-3">
                <p class="mb-0"><strong>Session:</strong> <?= htmlspecialchars($exam['session']) ?></p>
                <p class="mb-0"><strong>Term:</strong> <?php 
                    switch($exam['term']) {
                        case 1: echo "First Term"; break;
                        case 2: echo "Second Term"; break;
                        case 3: echo "Third Term"; break;
                        default: echo "Unknown";
                    }
                ?></p>
            </div>
        </div>
        
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert <?= $_SESSION['message_type'] ?> mb-3">
                <?= $_SESSION['message'] ?>
            </div>
            <?php unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
        <?php endif; ?>
        
        <!-- Subject Selector -->
        <div class="card mb-3">
            <form method="get" action="" class="mb-0">
                <input type="hidden" name="exam_id" value="<?= $examId ?>">
                <input type="hidden" name="class_id" value="<?= $classId ?>">
                
                <div class="form-group mb-0">
                    <label for="subject_id">Subject:</label>
                    <select name="subject_id" id="subject_id" onchange="this.form.submit()" class="form-select">
                        <?php foreach ($subjects as $subject): ?>
                            <option value="<?= $subject['subject_id'] ?>" 
                                    <?= $selectedSubjectId == $subject['subject_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($subject['subject_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>
        
        <!-- Scores Form -->
        <form method="post" action="" id="scoresForm">
            <div class="card">
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Admission No.</th>
                                <th>Student Name</th>
                                <?php foreach ($components as $component): ?>
                                    <th>
                                        <?= htmlspecialchars($component['name']) ?>
                                        <small class="d-block text-light">Max: <?= $component['max_marks'] ?></small>
                                    </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student): ?>
                                <tr>
                                    <td data-label="Admission No."><?= htmlspecialchars($student['admission_number']) ?></td>
                                    <td data-label="Name"><?= htmlspecialchars($student['name']) ?></td>
                                    <?php foreach ($components as $component): ?>
                                        <td data-label="<?= htmlspecialchars($component['name']) ?>">
                                            <input type="number" 
                                                   name="scores[<?= $student['id'] ?>][<?= $component['id'] ?>]" 
                                                   value="<?= $existingScores[$student['id']][$component['id']] ?>"
                                                   class="score-input"
                                                   min="0"
                                                   max="<?= $component['max_marks'] ?>"
                                                   step="0.01"
                                                   required>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="d-flex flex-wrap gap-2 justify-content-between mt-4">
                <button type="submit" class="btn btn-lg">
                    <i class="fas fa-save"></i> Save Scores
                </button>
                <a href="view_scores.php?exam_id=<?= $examId ?>&class_id=<?= $classId ?>&subject_id=<?= $selectedSubjectId ?>" 
                   class="btn btn-lg">
                    <i class="fas fa-eye"></i> View Scores
                </a>
                <a href="exams_list.php" class="btn btn-lg">
                    <i class="fas fa-arrow-left"></i> Back to Exams
                </a>
            </div>
        </form>
    </div>

    <script>
    document.getElementById('scoresForm').addEventListener('submit', function(e) {
        let hasError = false;
        const inputs = document.querySelectorAll('.score-input');
        
        inputs.forEach(input => {
            input.classList.remove('invalid-score');
            const value = parseFloat(input.value);
            const max = parseFloat(input.getAttribute('max'));
            
            if (isNaN(value) || value < 0 || value > max) {
                input.classList.add('invalid-score');
                hasError = true;
            }
        });
        
        if (hasError) {
            e.preventDefault();
            alert('Please check the highlighted scores. Scores must be between 0 and the maximum marks allowed.');
        }
    });
    </script>
</body>
</html>
