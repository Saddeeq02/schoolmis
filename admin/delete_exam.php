<?php
ob_start();

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Check if user is logged in and is an admin
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php');
    exit;
}
// Ensure session is started properly
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// Check if exam ID is provided
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['exam_id']) || empty($_POST['exam_id'])) {
    $_SESSION['message'] = 'Invalid request';
    $_SESSION['message_type'] = 'error';
    header('Location: exams_list.php');
    exit;
}

$examId = (int)$_POST['exam_id'];

// Delete the exam
$result = deleteExam($pdo, $examId);

// Set message and redirect
$_SESSION['message'] = $result['message'];
$_SESSION['message_type'] = $result['success'] ? 'success' : 'error';
header('Location: exams_list.php');
exit;
?>
