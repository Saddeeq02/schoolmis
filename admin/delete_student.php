<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Check if user is logged in and is an admin
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php');
    exit;
}

// Check if student ID is provided
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['student_id']) || empty($_POST['student_id'])) {
    $_SESSION['message'] = 'Invalid request';
    $_SESSION['message_type'] = 'error';
    header('Location: students_list.php');
    exit;
}

$studentId = (int)$_POST['student_id'];

// Delete the student
$result = deleteStudent($pdo, $studentId);

// Set message and redirect
$_SESSION['message'] = $result['message'];
$_SESSION['message_type'] = $result['success'] ? 'success' : 'error';
header('Location: students_list.php');
exit;
?>
