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

// Get school details - use 'default' if no specific school name is set
$schoolName = isset($_SESSION['school_name']) ? $_SESSION['school_name'] : 'default';
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
    <title>Student Results</title>
    <link rel="stylesheet" href="assets/clean-styles.css">
    <style>
        .results-container {
            max-width: 800px;
            margin: 0 auto;
            border: 10px solid <?= $borderColor ?>;
            padding: 15px;
            background-color: white;
            font-size: 11px; /* Smaller base font size to fit more content */
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
            margin-bottom: 3mm;
            font-size: 9pt;
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .results-table th, .results-table td {
            padding: 2mm;
            border: 0.5pt solid #DEB887;
            text-align: left;
        }

        .results-table th {
            background: linear-gradient(135deg, #8B4513, #A0522D) !important;
            color: white !important;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 8.5pt;
            letter-spacing: 0.2mm;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .results-table tbody tr:nth-child(even) {
            background-color: #FFF8F3;
        }

        .results-table tbody td {
            color: #1A1A1A;
        }

        .results-table .grade {
            font-weight: 600;
            color: #8B4513;
        }

        .results-table tfoot tr {
            border-top: 1pt solid #8B4513;
        }

        .results-table tfoot th {
            background: #FFF8F3 !important;
            color: #1A1A1A !important;
            font-weight: 600;
        }

        .print-button {
            display: block;
            margin: 20px auto;
            padding: 10px 20px;
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
            width: 100%;
            border-collapse: collapse;
            font-size: 8.5pt;
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .skills-table th, .skills-table td {
            padding: 1.5mm;
            border: 0.5pt solid #DEB887;
            text-align: left;
        }

        .skills-table th {
            background: linear-gradient(135deg, #8B4513, #A0522D) !important;
            color: white !important;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 8pt;
            letter-spacing: 0.2mm;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        h4, h3, h2 {
            margin: 5px 0;
        }
        @media print {
            body {
                background: white;
                color: black;
                font-size: 11px;
            }
            .container {
                background: white;
                box-shadow: none;
                padding: 0;
                max-width: 100%;
            }
            .print-button, form {
                display: none;
            }
            .results-container {
                border: 10px solid <?= $borderColor ?>;
                padding: 15px;
                page-break-after: always;
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
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($student && $selectedExam): ?>
            <div class="results-container">
                <div class="school-header">
                <?php if ($schoolDetails && !empty($schoolDetails['logo_path'])): ?>
                        <img src="<?= './' . htmlspecialchars($schoolDetails['logo_path']); ?>" alt="School Logo" class="school-logo">
                    <?php endif; ?>
                    <img src="<?= './' . htmlspecialchars($schoolDetails['logo_path']); ?>" alt="School Logo" class="school-logo">

                    <h2><?= $schoolDetails ? htmlspecialchars($schoolDetails['school_name']) : 'School Name'; ?></h2>
                    <p><?= $schoolDetails ? htmlspecialchars($schoolDetails['address']) : ''; ?></p>
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
                            // Get exam components
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
                        // Get subjects for the student's class
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
                                
                                // Calculate grade and remarks
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
                
                <div>
                    <h4>Position in Class: <?php echo getStudentPosition($pdo, $student['id'], $selectedExam['id'], $student['class_id']); ?></h4>
                    
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
                
                <button class="btn print-button" onclick="window.print()">Print Result</button>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
