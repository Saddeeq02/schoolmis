<?php
session_start();
include '../includes/auth.php';
include '../includes/db.php';
if ($_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/styles.css">
</head>
<body>
    <div class="container">
        <h2 class="text-white mb-4">Ishraaq Admin Dashboard</h2>
        
        <div class="dashboard-grid">
            <a href="create_class.php" class="card">
                <i class="fas fa-chalkboard"></i>
                <h3>Manage Classes</h3>
                <p>Create and manage class schedules</p>
            </a>
            
            <a href="create_subject.php" class="card">
                <i class="fas fa-book"></i>
                <h3>Manage Subjects</h3>
                <p>Add and edit subject information</p>
            </a>
            
            <a href="teachers_list.php" class="card">
                <i class="fas fa-users"></i>
                <h3>Teachers</h3>
                <p>View and manage teacher accounts</p>
            </a>

            <a href="students_list.php" class="card">
                <i class="fas fa-user-graduate"></i>
                <h3>Students</h3>
                <p>View and manage student accounts</p>
            </a>
            
            <a href="exams_list.php" class="card">
                <i class="fas fa-file-alt"></i>
                <h3>Exams</h3>
                <p>Manage exams and report cards</p>
            </a>
            
            <a href="teacher_subjects.php" class="card">
                <i class="fas fa-chalkboard-teacher"></i>
                <h3>Teacher Assignments</h3>
                <p>Assign teachers to subjects</p>
            </a>
            
            <a href="admin_qr_generator.php" class="card">
                <i class="fas fa-qrcode"></i>
                <h3>QR Generator</h3>
                <p>Generate attendance QR codes</p>
            </a>
            
            <a href="report.php" class="card">
                <i class="fas fa-chart-bar"></i>
                <h3>Reports</h3>
                <p>View attendance reports</p>
            </a>
            
            <a href="view_recordings.php" class="card">
                <i class="fas fa-microphone"></i>
                <h3>Recordings</h3>
                <p>Access teacher recordings</p>
            </a>
        </div>
        
        <div class="mt-4 text-center">
            <a href="../login.php" class="btn">Logout</a>
        </div>
    </div>
</body>
</html>
