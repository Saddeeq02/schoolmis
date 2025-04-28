<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', dirname(__DIR__) . '/error.log');

try {
    include '../includes/db.php';
    
    // Debug input
    error_log('POST data: ' . print_r($_POST, true));
    error_log('FILES data: ' . print_r($_FILES, true));

    if ($_SERVER['REQUEST_METHOD'] != 'POST') {
        throw new Exception('Invalid request method');
    }

    $uploadDir = dirname(__DIR__) . '/recordings/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    if (!isset($_FILES['audio']) || $_FILES['audio']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Audio file upload failed');
    }

    $newFileName = date('Y-m-d_H-i-s') . '_' . uniqid() . '.webm';
    $uploadFile = $uploadDir . $newFileName;
    $fileUrl = 'recordings/' . $newFileName;

    if (!move_uploaded_file($_FILES['audio']['tmp_name'], $uploadFile)) {
        throw new Exception('Failed to move uploaded file');
    }

    chmod($uploadFile, 0644);

    // Update SQL query to include all required fields
    $sql = "INSERT INTO recordings (
        teacher_id, 
        class_id, 
        subject_id, 
        recording_path, 
        start_time, 
        end_time, 
        created_at
    ) VALUES (
        :teacher_id, 
        :class_id, 
        :subject_id, 
        :recording_path, 
        NOW(), 
        NOW(), 
        NOW()
    )";
    
    $stmt = $pdo->prepare($sql);
    $params = [
        ':teacher_id' => $_POST['teacher_id'],
        ':class_id' => $_POST['class_id'],
        ':subject_id' => $_POST['subject_id'],
        ':recording_path' => $fileUrl
    ];

    // Debug SQL
    error_log('SQL: ' . $sql);
    error_log('Params: ' . print_r($params, true));

    if (!$stmt->execute($params)) {
        throw new Exception('Database error: ' . implode(', ', $stmt->errorInfo()));
    }

    if ($stmt->rowCount() === 0) {
        throw new Exception('No rows inserted');
    }

    echo json_encode([
        'status' => 'success',
        'file' => $fileUrl,
        'message' => 'Recording saved successfully',
        'debug' => [
            'inserted_id' => $pdo->lastInsertId(),
            'file_size' => filesize($uploadFile)
        ]
    ]);

} catch (Exception $e) {
    error_log('Recording Error: ' . $e->getMessage());
    if (isset($uploadFile) && file_exists($uploadFile)) {
        unlink($uploadFile); // Clean up file if database insert failed
    }
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'debug' => error_get_last()
    ]);
}
?>