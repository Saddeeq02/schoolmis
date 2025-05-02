<?php
require_once 'includes/db.php';

session_start();

$error = '';
$results = [];
$studentInfo = null;
$schoolInfo = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $admissionNumber = trim($_POST['admission_number'] ?? '');
    $examId = trim($_POST['exam_id'] ?? '');
    
    if (empty($admissionNumber) || empty($examId)) {
        $error = 'Please provide both admission number and select an exam.';
    } else {
        // Get student info
        $stmt = $pdo->prepare("SELECT s.*, c.class_name FROM students s JOIN classes c ON s.class_id = c.id WHERE s.admission_number = ?");
        $stmt->execute([$admissionNumber]);
        $studentInfo = $stmt->fetch();
        
        if (!$studentInfo) {
            $error = 'Student not found.';
        } else {
            // Get school info
            $stmt = $pdo->prepare("SELECT * FROM schools WHERE id = ?");
            $stmt->execute([$studentInfo['school_id']]);
            $schoolInfo = $stmt->fetch();
            
            // Get exam details
            $stmt = $pdo->prepare("
                SELECT e.*, c.class_name 
                FROM exams e 
                JOIN classes c ON e.class_id = c.id 
                WHERE e.id = ? AND e.class_id = ?
            ");
            $stmt->execute([$examId, $studentInfo['class_id']]);
            $examInfo = $stmt->fetch();
            
            if (!$examInfo) {
                $error = 'Exam not found for this student.';
            } else {
                // Get exam components and scores
                $stmt = $pdo->prepare("
                    SELECT s.*, sub.subject_name, ec.name as component_name, ec.max_marks
                    FROM scores s
                    JOIN subjects sub ON s.subject_id = sub.id
                    JOIN exam_components ec ON s.component_id = ec.id
                    WHERE s.student_id = ? AND s.exam_id = ?
                    ORDER BY sub.subject_name, ec.display_order
                ");
                $stmt->execute([$studentInfo['id'], $examId]);
                $scores = $stmt->fetchAll();
                
                // Process scores by subject
                $results = [];
                foreach ($scores as $score) {
                    if (!isset($results[$score['subject_name']])) {
                        $results[$score['subject_name']] = [
                            'components' => [],
                            'total' => 0,
                            'max_total' => 0
                        ];
                    }
                    
                    $results[$score['subject_name']]['components'][] = [
                        'name' => $score['component_name'],
                        'score' => $score['score'],
                        'max_marks' => $score['max_marks']
                    ];
                    
                    $results[$score['subject_name']]['total'] += $score['score'];
                    $results[$score['subject_name']]['max_total'] += $score['max_marks'];
                }
            }
        }
    }
}

// Get available exams
$exams = [];
if (isset($studentInfo)) {
    $stmt = $pdo->prepare("
        SELECT e.* 
        FROM exams e 
        WHERE e.class_id = ? 
        ORDER BY e.session DESC, e.term DESC
    ");
    $stmt->execute([$studentInfo['class_id']]);
    $exams = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Results</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/clean-styles.css">
    <style>
        @media print {
            .no-print {
                display: none !important;
            }
            
            body {
                background: white !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            
            .container {
                max-width: 100% !important;
                padding: 0 !important;
            }
            
            .card {
                box-shadow: none !important;
                border: none !important;
            }
            
            .table-responsive {
                overflow: visible !important;
            }
            
            table {
                width: 100% !important;
                page-break-inside: auto !important;
            }
            
            tr {
                page-break-inside: avoid !important;
                page-break-after: auto !important;
            }
        }
        
        .signature-line {
            border-top: 1px solid var(--border);
            padding-top: var(--spacing-sm);
            margin-top: var(--spacing-lg);
            text-align: center;
            width: 200px;
        }
        
        .signatures {
            display: flex;
            justify-content: space-between;
            margin-top: var(--spacing-xl);
            flex-wrap: wrap;
            gap: var(--spacing-lg);
        }
        
        .school-logo {
            max-width: 100px;
            height: auto;
            margin-bottom: var(--spacing-md);
        }
        
        .grade {
            font-weight: 600;
            color: var(--primary);
        }
        
        .subject-total {
            font-weight: 600;
            background: var(--light);
        }
        
        @media (max-width: 768px) {
            .signatures {
                justify-content: center;
            }
            
            .signature-line {
                width: 150px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card mb-4 no-print">
            <h1>Student Results</h1>
            
            <form method="post" action="" class="d-flex flex-column gap-3">
                <div class="form-group">
                    <label for="admission_number">Admission Number:</label>
                    <input type="text" 
                           id="admission_number" 
                           name="admission_number" 
                           value="<?= htmlspecialchars($_POST['admission_number'] ?? '') ?>"
                           required>
                </div>
                
                <?php if (!empty($exams)): ?>
                    <div class="form-group">
                        <label for="exam_id">Select Exam:</label>
                        <select name="exam_id" id="exam_id" required>
                            <option value="">Choose an exam...</option>
                            <?php foreach ($exams as $exam): ?>
                                <option value="<?= $exam['id'] ?>" 
                                        <?= (isset($_POST['exam_id']) && $_POST['exam_id'] == $exam['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($exam['title']) ?> 
                                    (<?= htmlspecialchars($exam['session']) ?>, Term <?= $exam['term'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
                
                <div>
                    <button type="submit" class="btn btn-lg">
                        <i class="fas fa-search"></i> View Results
                    </button>
                </div>
            </form>
            
            <?php if ($error): ?>
                <div class="alert alert-danger mt-3">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($studentInfo && !empty($results)): ?>
            <div class="card" id="results">
                <!-- School Header -->
                <div class="text-center mb-4">
                    <?php if ($schoolInfo['logo_path']): ?>
                        <img src="<?= htmlspecialchars($schoolInfo['logo_path']) ?>" 
                             alt="School Logo" 
                             class="school-logo">
                    <?php endif; ?>
                    <h2><?= htmlspecialchars($schoolInfo['name']) ?></h2>
                    <p><?= htmlspecialchars($schoolInfo['address']) ?></p>
                </div>
                
                <!-- Student Info -->
                <div class="card mb-4">
                    <div class="d-flex flex-wrap gap-3">
                        <div>
                            <strong>Student Name:</strong>
                            <?= htmlspecialchars($studentInfo['name']) ?>
                        </div>
                        <div>
                            <strong>Admission No:</strong>
                            <?= htmlspecialchars($studentInfo['admission_number']) ?>
                        </div>
                        <div>
                            <strong>Class:</strong>
                            <?= htmlspecialchars($studentInfo['class_name']) ?>
                        </div>
                        <div>
                            <strong>Session:</strong>
                            <?= htmlspecialchars($examInfo['session']) ?>
                        </div>
                        <div>
                            <strong>Term:</strong>
                            <?= htmlspecialchars($examInfo['term']) ?>
                        </div>
                    </div>
                </div>
                
                <!-- Results Table -->
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Subject</th>
                                <th>Component</th>
                                <th>Score</th>
                                <th>Max Marks</th>
                                <th>Percentage</th>
                                <th>Grade</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results as $subject => $data): ?>
                                <?php foreach ($data['components'] as $index => $component): ?>
                                    <tr>
                                        <?php if ($index === 0): ?>
                                            <td rowspan="<?= count($data['components']) ?>">
                                                <?= htmlspecialchars($subject) ?>
                                            </td>
                                        <?php endif; ?>
                                        <td><?= htmlspecialchars($component['name']) ?></td>
                                        <td><?= number_format($component['score'], 2) ?></td>
                                        <td><?= number_format($component['max_marks'], 2) ?></td>
                                        <td>
                                            <?= number_format(($component['score'] / $component['max_marks']) * 100, 1) ?>%
                                        </td>
                                        <td class="grade">
                                            <?= getGrade(($component['score'] / $component['max_marks']) * 100) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                
                                <!-- Subject Total -->
                                <tr class="subject-total">
                                    <td colspan="2">Total</td>
                                    <td><?= number_format($data['total'], 2) ?></td>
                                    <td><?= number_format($data['max_total'], 2) ?></td>
                                    <td>
                                        <?= number_format(($data['total'] / $data['max_total']) * 100, 1) ?>%
                                    </td>
                                    <td class="grade">
                                        <?= getGrade(($data['total'] / $data['max_total']) * 100) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Signatures -->
                <div class="signatures">
                    <div class="signature-line">
                        <div>Class Teacher</div>
                    </div>
                    <div class="signature-line">
                        <div>Principal</div>
                    </div>
                    <div class="signature-line">
                        <div>Parent/Guardian</div>
                    </div>
                </div>
                
                <!-- Print Button -->
                <div class="text-center mt-4 no-print">
                    <button onclick="window.print()" class="btn btn-lg">
                        <i class="fas fa-print"></i> Print Result
                    </button>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Back Link -->
        <div class="text-center mt-4 no-print">
            <a href="index.php" class="btn">
                <i class="fas fa-arrow-left"></i> Back to Home
            </a>
        </div>
    </div>

    <?php
    function getGrade($percentage) {
        if ($percentage >= 90) return 'A+';
        if ($percentage >= 80) return 'A';
        if ($percentage >= 70) return 'B';
        if ($percentage >= 60) return 'C';
        if ($percentage >= 50) return 'D';
        return 'F';
    }
    ?>
</body>
</html>
