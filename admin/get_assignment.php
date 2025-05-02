<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/auth.php';

requireAdmin();

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Assignment ID is required']);
    exit;
}

$assignmentId = $_GET['id'];
$school_id = isset($_GET['school_id']) ? $_GET['school_id'] : ($_SESSION['school_id'] ?? null);

try {
    $stmt = $pdo->prepare("
        SELECT ts.* 
        FROM teacher_subjects ts
        WHERE ts.id = :id
        " . ($school_id ? " AND ts.school_id = :school_id" : "") . "
    ");
    
    $stmt->bindParam(':id', $assignmentId, PDO::PARAM_INT);
    
    if ($school_id) {
        $stmt->bindParam(':school_id', $school_id, PDO::PARAM_INT);
    }
    
    $stmt->execute();
    $assignment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($assignment) {
        echo json_encode(['success' => true, 'assignment' => $assignment]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Assignment not found']);
    }
} catch (PDOException $e) {
    error_log("Error fetching assignment: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>
