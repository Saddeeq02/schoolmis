<?php
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
            transition: all 0.3s ease;
        }
        
        .invalid-score {
            border-color: var(--danger);
            background-color: var(--light);
        }

        .score-saved {
            border-color: var(--success);
            animation: flash-success 1s;
        }

        @keyframes flash-success {
            0% { box-shadow: 0 0 0 2px var(--success); }
            100% { box-shadow: none; }
        }
        
        .sticky-actions {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            padding: 1rem;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
            z-index: 1000;
            display: flex;
            justify-content: space-between;
            gap: 1rem;
        }

        .sticky-actions .btn {
            flex: 1;
            white-space: nowrap;
        }

        .container {
            padding-bottom: 80px; /* Space for sticky buttons */
        }

        .student-card {
            background: white;
            border-radius: var(--radius);
            padding: 1rem;
            margin-bottom: 1rem;
            border: 1px solid var(--border);
        }

        .student-card .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .student-scores {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
        }

        .score-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .score-group label {
            font-size: 0.9rem;
            font-weight: 500;
        }

        .score-group small {
            color: var(--text-light);
        }

        .save-indicator {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            color: var(--text-light);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .save-indicator.visible {
            opacity: 1;
        }

        .save-indicator i {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            100% { transform: rotate(360deg); }
        }
        
        @media (max-width: 768px) {
            .student-card {
                margin: 0.5rem -1rem;
                border-radius: 0;
            }

            .score-input {
                width: 100%;
            }

            .sticky-actions {
                padding: 0.75rem;
            }

            .sticky-actions .btn {
                padding: 0.75rem;
                font-size: 0.9rem;
            }

            .btn i {
                margin-right: 0;
            }

            .btn span {
                display: none;
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
            <?php foreach ($students as $student): ?>
                <div class="student-card">
                    <div class="header">
                        <div>
                            <strong><?= htmlspecialchars($student['name']) ?></strong>
                            <small class="d-block text-light"><?= htmlspecialchars($student['admission_number']) ?></small>
                        </div>
                        <div class="save-indicator" id="indicator-<?= $student['id'] ?>">
                            <i class="fas fa-spinner"></i> Saving...
                        </div>
                    </div>
                    
                    <div class="student-scores">
                        <?php foreach ($components as $component): ?>
                            <div class="score-group">
                                <label><?= htmlspecialchars($component['name']) ?></label>
                                <input type="number" 
                                       name="scores[<?= $student['id'] ?>][<?= $component['id'] ?>]" 
                                       value="<?= $existingScores[$student['id']][$component['id']] ?>"
                                       class="score-input"
                                       data-student="<?= $student['id'] ?>"
                                       data-component="<?= $component['id'] ?>"
                                       min="0"
                                       max="<?= $component['max_marks'] ?>"
                                       step="0.01"
                                       required>
                                <small>Max: <?= $component['max_marks'] ?></small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </form>

        <!-- Sticky Action Buttons -->
        <div class="sticky-actions">
            <a href="exams_list.php" class="btn">
                <i class="fas fa-arrow-left"></i>
                <span>Back</span>
            </a>
            <button type="submit" form="scoresForm" class="btn btn-primary">
                <i class="fas fa-save"></i>
                <span>Save All</span>
            </button>
            <a href="view_scores.php?exam_id=<?= $examId ?>&class_id=<?= $classId ?>&subject_id=<?= $selectedSubjectId ?>" 
               class="btn">
                <i class="fas fa-eye"></i>
                <span>View</span>
            </a>
        </div>
    </div>

    <script>
    // Debounce function to limit API calls
    function debounce(func, wait) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), wait);
        };
    }

    // Save score for a single student and component
    // async function saveScore(input) {
    //     const studentId = input.dataset.student;
    //     const componentId = input.dataset.component;
    //     const score = input.value;
    //     const indicator = document.getElementById(`indicator-${studentId}`);
        
    //     try {
    //         // Show saving indicator
    //         indicator.classList.add('visible');
            
    //         const response = await fetch('save_score.php', {
    //             method: 'POST',
    //             headers: {
    //                 'Content-Type': 'application/json',
    //             },
    //             body: JSON.stringify({
    //                 student_id: studentId,
    //                 exam_id: <?= $examId ?>,
    //                 subject_id: <?= $selectedSubjectId ?>,
    //                 component_id: componentId,
    //                 score: score
    //             })
    //         });
            
    //         const result = await response.json();
            
    //         if (result.success) {
    //             input.classList.add('score-saved');
    //             setTimeout(() => input.classList.remove('score-saved'), 1000);
    //         } else {
    //             input.classList.add('invalid-score');
    //             alert(result.message || 'Error saving score');
    //         }
    //     } catch (error) {
    //         console.error('Error:', error);
    //         input.classList.add('invalid-score');
    //         alert('Failed to save score. Please try again.');
    //     } finally {
    //         // Hide saving indicator
    //         setTimeout(() => indicator.classList.remove('visible'), 500);
    //     }
    // }

    // Setup input handlers
    document.querySelectorAll('.score-input').forEach(input => {
        const debouncedSave = debounce(() => saveScore(input), 1000);
        
        input.addEventListener('input', () => {
            input.classList.remove('invalid-score', 'score-saved');
            const value = parseFloat(input.value);
            const max = parseFloat(input.getAttribute('max'));
            
            if (!isNaN(value) && value >= 0 && value <= max) {
                debouncedSave();
            }
        });
        
        input.addEventListener('blur', () => {
            const value = parseFloat(input.value);
            const max = parseFloat(input.getAttribute('max'));
            
            if (isNaN(value) || value < 0 || value > max) {
                input.classList.add('invalid-score');
            }
        });
    });

    // Form submission handler for saving all scores
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
