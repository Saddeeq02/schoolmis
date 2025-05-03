<?php
include '../includes/auth.php';
include '../includes/db.php';
include '../includes/functions.php';

// Verify teacher role
if ($_SESSION['role'] != 'teacher') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get JSON data
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request data']);
    exit();
}

// Extract data
$studentId = $data['student_id'] ?? null;
$examId = $data['exam_id'] ?? null;
$subjectId = $data['subject_id'] ?? null;
$componentId = $data['component_id'] ?? null;
$score = $data['score'] ?? null;
$teacherId = $_SESSION['user_id'];

// Validate required fields
if (!$studentId || !$examId || !$subjectId || !$componentId || !isset($score)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

try {
    // Verify teacher teaches this subject
    $stmt = $pdo->prepare("
        SELECT 1 
        FROM teacher_subjects ts
        JOIN students s ON s.class_id = ts.class_id
        WHERE ts.teacher_id = ? 
        AND ts.subject_id = ?
        AND s.id = ?
    ");
    $stmt->execute([$teacherId, $subjectId, $studentId]);
    
    if (!$stmt->fetch()) {
        throw new Exception('You are not authorized to enter scores for this student/subject');
    }

    // Get component details for validation
    $stmt = $pdo->prepare("SELECT * FROM exam_components WHERE id = ? AND exam_id = ?");
    $stmt->execute([$componentId, $examId]);
    $component = $stmt->fetch();

    if (!$component) {
        throw new Exception('Invalid exam component');
    }

    // Validate score range
    if (!is_numeric($score) || $score < 0 || $score > $component['max_marks']) {
        throw new Exception("Score must be between 0 and {$component['max_marks']}");
    }

    // Save the score
    $result = saveStudentScore($pdo, $studentId, $examId, $subjectId, $componentId, $score, $teacherId);
    
    if (!$result['success']) {
        throw new Exception($result['message']);
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}