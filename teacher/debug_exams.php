<?php
include '../includes/auth.php';
include '../includes/db.php';
include '../includes/functions.php';

if ($_SESSION['role'] != 'teacher') {
    header("Location: ../index.php");
    exit();
}

$teacherId = $_SESSION['user_id'];

// Get teacher information
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
$stmt->bindParam(':id', $teacherId, PDO::PARAM_INT);
$stmt->execute();
$teacher = $stmt->fetch();

// Get teacher's assigned classes
$teacherClasses = getTeacherSubjectsByTeacher($pdo, $teacherId);
$classIds = [];
foreach ($teacherClasses as $class) {
    if (!in_array($class['class_id'], $classIds)) {
        $classIds[] = $class['class_id'];
    }
}

// Direct query for classes
$classIdList = implode(',', $classIds);
$classesQuery = "SELECT id, class_name FROM classes WHERE id IN ($classIdList)";
$classesStmt = $pdo->query($classesQuery);
$classes = $classesStmt->fetchAll();

// Get all exams in the system
$allExamsStmt = $pdo->query("
    SELECT e.id, e.title, e.class_id, c.class_name 
    FROM exams e 
    JOIN classes c ON e.class_id = c.id
");
$allExams = $allExamsStmt->fetchAll();

// Get exams for teacher using the function
$examsForTeacher = getExamsForTeacher($pdo, $teacherId);

// Direct query for exams
$directExamsQuery = "
    SELECT e.id, e.title, e.class_id, c.class_name 
    FROM exams e 
    JOIN classes c ON e.class_id = c.id
    WHERE e.class_id IN ($classIdList)
";
$directExamsStmt = $pdo->query($directExamsQuery);
$directExams = $directExamsStmt->fetchAll();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Debug Exams</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/styles.css">
    <style>
        .debug-section {
            margin-bottom: 30px;
            padding: 15px;
            background-color: #f9f9f9;
            border-radius: 5px;
        }
        .debug-table {
            width: 100%;
            border-collapse: collapse;
        }
        .debug-table th, .debug-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        .debug-table th {
            background-color: #f2f2f2;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Debug Exams Information</h2>
        
        <div class="debug-section">
            <h3>Teacher Information</h3>
            <p>
                <strong>Teacher ID:</strong> <?= $teacher['id'] ?><br>
                <strong>Teacher Name:</strong> <?= $teacher['name'] ?>
            </p>
        </div>
        
        <div class="debug-section">
            <h3>Teacher's Assigned Classes</h3>
            <?php if (!empty($teacherClasses)): ?>
                <table class="debug-table">
                    <thead>
                        <tr>
                            <th>Class ID</th>
                            <th>Class Name</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($teacherClasses as $class): ?>
                            <tr>
                                <td><?= $class['class_id'] ?></td>
                                <td><?= $class['class_name'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No classes assigned to this teacher.</p>
            <?php endif; ?>
        </div>
        
        <div class="debug-section">
            <h3>Direct Query for Classes</h3>
            <?php if (!empty($classes)): ?>
                <table class="debug-table">
                    <thead>
                        <tr>
                            <th>Class ID</th>
                            <th>Class Name</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($classes as $class): ?>
                            <tr>
                                <td><?= $class['id'] ?></td>
                                <td><?= $class['class_name'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No classes found for this teacher using direct query.</p>
            <?php endif; ?>
        </div>
        
        <div class="debug-section">
            <h3>All Exams in System</h3>
            <?php if (!empty($allExams)): ?>
                <table class="debug-table">
                    <thead>
                        <tr>
                            <th>Exam ID</th>
                            <th>Title</th>
                            <th>Class ID</th>
                            <th>Class Name</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allExams as $exam): ?>
                            <tr>
                                <td><?= $exam['id'] ?></td>
                                <td><?= $exam['title'] ?></td>
                                <td><?= $exam['class_id'] ?></td>
                                <td><?= $exam['class_name'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No exams found in the system.</p>
            <?php endif; ?>
        </div>
        
        <div class="debug-section">
            <h3>Exams for Teacher (Using getExamsForTeacher function)</h3>
            <?php if (!empty($examsForTeacher)): ?>
                <table class="debug-table">
                    <thead>
                        <tr>
                            <th>Exam ID</th>
                            <th>Title</th>
                            <th>Class ID</th>
                            <th>Class Name</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($examsForTeacher as $exam): ?>
                            <tr>
                                <td><?= $exam['id'] ?></td>
                                <td><?= $exam['title'] ?></td>
                                <td><?= $exam['class_id'] ?></td>
                                <td><?= $exam['class_name'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No exams found for this teacher using the function.</p>
            <?php endif; ?>
        </div>
        
        <div class="debug-section">
            <h3>Direct Query for Exams</h3>
            <?php if (!empty($directExams)): ?>
                <table class="debug-table">
                    <thead>
                        <tr>
                            <th>Exam ID</th>
                            <th>Title</th>
                            <th>Class ID</th>
                            <th>Class Name</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($directExams as $exam): ?>
                            <tr>
                                <td><?= $exam['id'] ?></td>
                                <td><?= $exam['title'] ?></td>
                                <td><?= $exam['class_id'] ?></td>
                                <td><?= $exam['class_name'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No exams found for this teacher using direct query.</p>
            <?php endif; ?>
        </div>
        
        <div class="mt-4">
            <a href="exams_list.php" class="btn">Back to Exams List</a>
            <a href="dashboard.php" class="btn">Back to Dashboard</a>
        </div>
    </div>
</body>
</html>
