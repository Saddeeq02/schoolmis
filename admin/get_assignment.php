<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdmin();

header('Content-Type: application/json');

if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Assignment ID is required'
    ]);
    exit;
}

$assignmentId = $_GET['id'];
$assignment = getTeacherSubjectById($pdo, $assignmentId);

if ($assignment) {
    echo json_encode([
        'success' => true,
        'assignment' => $assignment
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Assignment not found'
    ]);
}
?>
