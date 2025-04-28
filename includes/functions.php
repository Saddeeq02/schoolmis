<?php
// Function to get all students with their class information
function getAllStudents($pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT s.*, c.class_name 
            FROM students s
            JOIN classes c ON s.class_id = c.id
            ORDER BY s.name ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error fetching students: " . $e->getMessage());
        return false;
    }
}

// Function to get students by class ID
function getStudentsByClass($pdo, $classId) {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM students 
            WHERE class_id = :class_id 
            ORDER BY name ASC
        ");
        $stmt->bindParam(':class_id', $classId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error fetching students by class: " . $e->getMessage());
        return false;
    }
}

// Function to get a single student by ID
function getStudentById($pdo, $studentId) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM students WHERE id = :id");
        $stmt->bindParam(':id', $studentId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Error fetching student: " . $e->getMessage());
        return false;
    }
}

// Function to add a new student
function addStudent($pdo, $admissionNumber, $name, $classId, $userId) {
    try {
        // Check if admission number already exists
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE admission_number = :admission_number");
        $checkStmt->bindParam(':admission_number', $admissionNumber, PDO::PARAM_STR);
        $checkStmt->execute();
        
        if ($checkStmt->fetchColumn() > 0) {
            return [
                'success' => false,
                'message' => 'Admission number already exists'
            ];
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO students (admission_number, name, class_id, created_by) 
            VALUES (:admission_number, :name, :class_id, :created_by)
        ");
        
        $stmt->bindParam(':admission_number', $admissionNumber, PDO::PARAM_STR);
        $stmt->bindParam(':name', $name, PDO::PARAM_STR);
        $stmt->bindParam(':class_id', $classId, PDO::PARAM_INT);
        $stmt->bindParam(':created_by', $userId, PDO::PARAM_INT);
        
        $stmt->execute();
        
        return [
            'success' => true,
            'message' => 'Student added successfully',
            'id' => $pdo->lastInsertId()
        ];
    } catch (PDOException $e) {
        error_log("Error adding student: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Database error occurred'
        ];
    }
}

// Function to update a student
function updateStudent($pdo, $studentId, $admissionNumber, $name, $classId) {
    try {
        // Check if admission number already exists for another student
        $checkStmt = $pdo->prepare("
            SELECT COUNT(*) FROM students 
            WHERE admission_number = :admission_number AND id != :id
        ");
        $checkStmt->bindParam(':admission_number', $admissionNumber, PDO::PARAM_STR);
        $checkStmt->bindParam(':id', $studentId, PDO::PARAM_INT);
        $checkStmt->execute();
        
        if ($checkStmt->fetchColumn() > 0) {
            return [
                'success' => false,
                'message' => 'Admission number already exists for another student'
            ];
        }
        
        $stmt = $pdo->prepare("
            UPDATE students 
            SET admission_number = :admission_number, 
                name = :name, 
                class_id = :class_id 
            WHERE id = :id
        ");
        
        $stmt->bindParam(':admission_number', $admissionNumber, PDO::PARAM_STR);
        $stmt->bindParam(':name', $name, PDO::PARAM_STR);
        $stmt->bindParam(':class_id', $classId, PDO::PARAM_INT);
        $stmt->bindParam(':id', $studentId, PDO::PARAM_INT);
        
        $stmt->execute();
        
        return [
            'success' => true,
            'message' => 'Student updated successfully'
        ];
    } catch (PDOException $e) {
        error_log("Error updating student: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Database error occurred'
        ];
    }
}

// Function to delete a student
function deleteStudent($pdo, $studentId) {
    try {
        $stmt = $pdo->prepare("DELETE FROM students WHERE id = :id");
        $stmt->bindParam(':id', $studentId, PDO::PARAM_INT);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            return [
                'success' => true,
                'message' => 'Student deleted successfully'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Student not found'
            ];
        }
    } catch (PDOException $e) {
        error_log("Error deleting student: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Database error occurred. The student may have associated records.'
        ];
    }
}
?>
