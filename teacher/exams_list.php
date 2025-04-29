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
$exams = getExamsForTeacher($pdo, $teacherId);

// Get teacher's assigned classes and subjects
$teacherSubjects = getTeacherSubjectsByTeacher($pdo, $teacherId);
$assignedClasses = [];
foreach ($teacherSubjects as $subject) {
    if (!in_array($subject['class_id'], array_column($assignedClasses, 'id'))) {
        $assignedClasses[] = [
            'id' => $subject['class_id'],
            'name' => $subject['class_name']
        ];
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Exams List</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/styles.css">
</head>
<body>
    <div class="container">
        <h2>Exams List</h2>
        
        <?php if (!empty($exams)): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Session</th>
                        <th>Term</th>
                        <th>Class</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($exams as $exam): ?>
                        <tr>
                            <td><?= htmlspecialchars($exam['title']) ?></td>
                            <td><?= htmlspecialchars($exam['session']) ?></td>
                            <td>
                                <?php 
                                    switch($exam['term']) {
                                        case 1: echo "First Term"; break;
                                        case 2: echo "Second Term"; break;
                                        case 3: echo "Third Term"; break;
                                        default: echo "Unknown";
                                    }
                                ?>
                            </td>
                            <td><?= htmlspecialchars($exam['class_name']) ?></td>
                            <td>
                                <div class="action-buttons">
                                    <a href="enter_scores.php?exam_id=<?= $exam['id'] ?>&class_id=<?= $exam['class_id'] ?>" class="btn btn-sm">
                                        <i class="fas fa-edit"></i> Enter Scores
                                    </a>
                                    <a href="view_scores.php?exam_id=<?= $exam['id'] ?>&class_id=<?= $exam['class_id'] ?>" class="btn btn-sm">
                                        <i class="fas fa-eye"></i> View Scores
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="alert alert-info">
                <p>No exams found for your assigned classes.</p>
                <?php if (empty($assignedClasses)): ?>
                    <p>You have not been assigned to any classes yet. Please contact the administrator.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <div class="mt-4">
            <a href="dashboard.php" class="btn">Back to Dashboard</a>
        </div>
    </div>
</body>
</html>
