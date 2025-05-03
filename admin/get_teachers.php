<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

// Check if user is logged in and is an admin
if (!isAdmin()) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$schoolId = isset($_GET['school_id']) ? (int)$_GET['school_id'] : 0;
$type = isset($_GET['type']) ? $_GET['type'] : 'teachers';

try {
    $data = [];
    
    switch($type) {
        case 'teachers':
            $query = "SELECT id, name FROM users WHERE role = 'teacher'";
            if ($schoolId > 0) {
                $query .= " AND school_id = :school_id";
            }
            $query .= " ORDER BY name";
            break;
            
        case 'classes':
            $query = "SELECT id, class_name as name FROM classes";
            if ($schoolId > 0) {
                $query .= " WHERE school_id = :school_id";
            }
            $query .= " ORDER BY class_name";
            break;
            
        case 'subjects':
            $query = "SELECT id, subject_name as name FROM subjects";
            if ($schoolId > 0) {
                $query .= " WHERE school_id = :school_id";
            }
            $query .= " ORDER BY subject_name";
            break;
            
        default:
            throw new Exception('Invalid type specified');
    }

    $stmt = $pdo->prepare($query);
    if ($schoolId > 0) {
        $stmt->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
    }
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: application/json');
    echo json_encode($data);
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
}
?>