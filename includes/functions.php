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

// Function to get all exams with their class information
function getAllExams($pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT e.*, c.class_name 
            FROM exams e
            JOIN classes c ON e.class_id = c.id
            ORDER BY e.created_at DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error fetching exams: " . $e->getMessage());
        return false;
    }
}

// Function to get exams by class ID
function getExamsByClass($pdo, $classId) {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM exams 
            WHERE class_id = :class_id 
            ORDER BY created_at DESC
        ");
        $stmt->bindParam(':class_id', $classId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error fetching exams by class: " . $e->getMessage());
        return false;
    }
}

// Function to get a single exam by ID
function getExamById($pdo, $examId) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM exams WHERE id = :id");
        $stmt->bindParam(':id', $examId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Error fetching exam: " . $e->getMessage());
        return false;
    }
}

// Function to add a new exam
function addExam($pdo, $title, $session, $term, $classId, $userId) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO exams (title, session, term, class_id, created_by) 
            VALUES (:title, :session, :term, :class_id, :created_by)
        ");
        
        $stmt->bindParam(':title', $title, PDO::PARAM_STR);
        $stmt->bindParam(':session', $session, PDO::PARAM_STR);
        $stmt->bindParam(':term', $term, PDO::PARAM_INT);
        $stmt->bindParam(':class_id', $classId, PDO::PARAM_INT);
        $stmt->bindParam(':created_by', $userId, PDO::PARAM_INT);
        
        $stmt->execute();
        
        return [
            'success' => true,
            'message' => 'Exam added successfully',
            'id' => $pdo->lastInsertId()
        ];
    } catch (PDOException $e) {
        error_log("Error adding exam: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Database error occurred'
        ];
    }
}

// Function to update an exam
function updateExam($pdo, $examId, $title, $session, $term, $classId) {
    try {
        $stmt = $pdo->prepare("
            UPDATE exams 
            SET title = :title, 
                session = :session, 
                term = :term, 
                class_id = :class_id 
            WHERE id = :id
        ");
        
        $stmt->bindParam(':title', $title, PDO::PARAM_STR);
        $stmt->bindParam(':session', $session, PDO::PARAM_STR);
        $stmt->bindParam(':term', $term, PDO::PARAM_INT);
        $stmt->bindParam(':class_id', $classId, PDO::PARAM_INT);
        $stmt->bindParam(':id', $examId, PDO::PARAM_INT);
        
        $stmt->execute();
        
        return [
            'success' => true,
            'message' => 'Exam updated successfully'
        ];
    } catch (PDOException $e) {
        error_log("Error updating exam: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Database error occurred'
        ];
    }
}

// Function to delete an exam
function deleteExam($pdo, $examId) {
    try {
        // Start transaction to ensure all related records are deleted
        $pdo->beginTransaction();
        
        // Delete exam components first (due to foreign key constraints)
        $stmtComponents = $pdo->prepare("DELETE FROM exam_components WHERE exam_id = :exam_id");
        $stmtComponents->bindParam(':exam_id', $examId, PDO::PARAM_INT);
        $stmtComponents->execute();
        
        // Delete the exam
        $stmtExam = $pdo->prepare("DELETE FROM exams WHERE id = :id");
        $stmtExam->bindParam(':id', $examId, PDO::PARAM_INT);
        $stmtExam->execute();
        
        // Commit the transaction
        $pdo->commit();
        
        if ($stmtExam->rowCount() > 0) {
            return [
                'success' => true,
                'message' => 'Exam deleted successfully'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Exam not found'
            ];
        }
    } catch (PDOException $e) {
        // Rollback the transaction if something failed
        $pdo->rollBack();
        error_log("Error deleting exam: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Database error occurred. The exam may have associated records.'
        ];
    }
}

// Function to get exam components by exam ID
function getExamComponents($pdo, $examId) {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM exam_components 
            WHERE exam_id = :exam_id 
            ORDER BY display_order ASC
        ");
        $stmt->bindParam(':exam_id', $examId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error fetching exam components: " . $e->getMessage());
        return false;
    }
}

// Function to add an exam component
function addExamComponent($pdo, $examId, $name, $maxMarks, $isEnabled, $displayOrder) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO exam_components (exam_id, name, max_marks, is_enabled, display_order) 
            VALUES (:exam_id, :name, :max_marks, :is_enabled, :display_order)
        ");
        
        $stmt->bindParam(':exam_id', $examId, PDO::PARAM_INT);
        $stmt->bindParam(':name', $name, PDO::PARAM_STR);
        $stmt->bindParam(':max_marks', $maxMarks, PDO::PARAM_INT);
        $stmt->bindParam(':is_enabled', $isEnabled, PDO::PARAM_INT);
        $stmt->bindParam(':display_order', $displayOrder, PDO::PARAM_INT);
        
        $stmt->execute();
        
        return [
            'success' => true,
            'message' => 'Exam component added successfully',
            'id' => $pdo->lastInsertId()
        ];
    } catch (PDOException $e) {
        error_log("Error adding exam component: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Database error occurred'
        ];
    }
}

// Function to update an exam component
function updateExamComponent($pdo, $componentId, $name, $maxMarks, $isEnabled) {
    try {
        $stmt = $pdo->prepare("
            UPDATE exam_components 
            SET name = :name, 
                max_marks = :max_marks, 
                is_enabled = :is_enabled 
            WHERE id = :id
        ");
        
        $stmt->bindParam(':name', $name, PDO::PARAM_STR);
        $stmt->bindParam(':max_marks', $maxMarks, PDO::PARAM_INT);
        $stmt->bindParam(':is_enabled', $isEnabled, PDO::PARAM_INT);
        $stmt->bindParam(':id', $componentId, PDO::PARAM_INT);
        
        $stmt->execute();
        
        return [
            'success' => true,
            'message' => 'Exam component updated successfully'
        ];
    } catch (PDOException $e) {
        error_log("Error updating exam component: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Database error occurred'
        ];
    }
}

// Function to delete an exam component
function deleteExamComponent($pdo, $componentId) {
    try {
        $stmt = $pdo->prepare("DELETE FROM exam_components WHERE id = :id");
        $stmt->bindParam(':id', $componentId, PDO::PARAM_INT);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            return [
                'success' => true,
                'message' => 'Exam component deleted successfully'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Exam component not found'
            ];
        }
    } catch (PDOException $e) {
        error_log("Error deleting exam component: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Database error occurred'
        ];
    }
}

// Function to get school details
function getSchoolDetails($pdo, $schoolName) {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM school_details 
            WHERE school_name = :school_name
        ");
        $stmt->bindParam(':school_name', $schoolName, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Error fetching school details: " . $e->getMessage());
        return false;
    }
}

// Function to add or update school details
function saveSchoolDetails($pdo, $schoolName, $address, $logoPath, $userId) {
    try {
        // Check if school details already exist
        $checkStmt = $pdo->prepare("SELECT id FROM school_details WHERE school_name = :school_name");
        $checkStmt->bindParam(':school_name', $schoolName, PDO::PARAM_STR);
        $checkStmt->execute();
        $exists = $checkStmt->fetch();
        
        if ($exists) {
            // Update existing record
            $stmt = $pdo->prepare("
                UPDATE school_details 
                SET address = :address, 
                    logo_path = :logo_path, 
                    updated_at = CURRENT_TIMESTAMP 
                WHERE school_name = :school_name
            ");
        } else {
            // Insert new record
            $stmt = $pdo->prepare("
                INSERT INTO school_details (school_name, address, logo_path, created_by) 
                VALUES (:school_name, :address, :logo_path, :created_by)
            ");
            $stmt->bindParam(':created_by', $userId, PDO::PARAM_INT);
        }
        
        $stmt->bindParam(':school_name', $schoolName, PDO::PARAM_STR);
        $stmt->bindParam(':address', $address, PDO::PARAM_STR);
        $stmt->bindParam(':logo_path', $logoPath, PDO::PARAM_STR);
        
        $stmt->execute();
        
        return [
            'success' => true,
            'message' => 'School details saved successfully'
        ];
    } catch (PDOException $e) {
        error_log("Error saving school details: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Database error occurred: ' . $e->getMessage()
        ];
    }
}

// Function to get all teachers
function getAllTeachers($pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM users 
            WHERE role = 'teacher' 
            ORDER BY name ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error fetching teachers: " . $e->getMessage());
        return false;
    }
}

// Function to get all teacher-subject assignments with details
function getAllTeacherSubjects($pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT ts.id, u.name AS teacher_name, s.subject_name, c.class_name, ts.created_at
            FROM teacher_subjects ts
            JOIN users u ON ts.teacher_id = u.id
            JOIN subjects s ON ts.subject_id = s.id
            JOIN classes c ON ts.class_id = c.id
            ORDER BY u.name ASC, c.class_name ASC, s.subject_name ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error fetching teacher-subject assignments: " . $e->getMessage());
        return false;
    }
}

// Function to get teacher-subject assignments by teacher ID
function getTeacherSubjectsByTeacher($pdo, $teacherId) {
    try {
        $stmt = $pdo->prepare("
            SELECT ts.id, s.subject_name, c.class_name, s.id as subject_id, c.id as class_id
            FROM teacher_subjects ts
            JOIN subjects s ON ts.subject_id = s.id
            JOIN classes c ON ts.class_id = c.id
            WHERE ts.teacher_id = :teacher_id
            ORDER BY c.class_name ASC, s.subject_name ASC
        ");
        $stmt->bindParam(':teacher_id', $teacherId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error fetching teacher-subject assignments by teacher: " . $e->getMessage());
        return false;
    }
}

// Function to get teacher-subject assignments by class ID
function getTeacherSubjectsByClass($pdo, $classId) {
    try {
        $stmt = $pdo->prepare("
            SELECT ts.id, u.name AS teacher_name, s.subject_name
            FROM teacher_subjects ts
            JOIN users u ON ts.teacher_id = u.id
            JOIN subjects s ON ts.subject_id = s.id
            WHERE ts.class_id = :class_id
            ORDER BY s.subject_name ASC
        ");
        $stmt->bindParam(':class_id', $classId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error fetching teacher-subject assignments by class: " . $e->getMessage());
        return false;
    }
}

// Function to check if a teacher-subject assignment already exists
function teacherSubjectExists($pdo, $teacherId, $subjectId, $classId) {
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM teacher_subjects 
            WHERE teacher_id = :teacher_id 
            AND subject_id = :subject_id 
            AND class_id = :class_id
        ");
        $stmt->bindParam(':teacher_id', $teacherId, PDO::PARAM_INT);
        $stmt->bindParam(':subject_id', $subjectId, PDO::PARAM_INT);
        $stmt->bindParam(':class_id', $classId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        error_log("Error checking teacher-subject assignment: " . $e->getMessage());
        return false;
    }
}

// Function to add a new teacher-subject assignment
function addTeacherSubject($pdo, $teacherId, $subjectId, $classId) {
    try {
        // Check if assignment already exists
        if (teacherSubjectExists($pdo, $teacherId, $subjectId, $classId)) {
            return [
                'success' => false,
                'message' => 'This teacher is already assigned to this subject for this class'
            ];
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO teacher_subjects (teacher_id, subject_id, class_id) 
            VALUES (:teacher_id, :subject_id, :class_id)
        ");
        
        $stmt->bindParam(':teacher_id', $teacherId, PDO::PARAM_INT);
        $stmt->bindParam(':subject_id', $subjectId, PDO::PARAM_INT);
        $stmt->bindParam(':class_id', $classId, PDO::PARAM_INT);
        
        $stmt->execute();
        
        return [
            'success' => true,
            'message' => 'Teacher-subject assignment added successfully',
            'id' => $pdo->lastInsertId()
        ];
    } catch (PDOException $e) {
        error_log("Error adding teacher-subject assignment: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Database error occurred'
        ];
    }
}

// Function to update a teacher-subject assignment
function updateTeacherSubject($pdo, $assignmentId, $teacherId, $subjectId, $classId) {
    try {
        // Get current assignment details to check if it's changing
        $currentStmt = $pdo->prepare("SELECT teacher_id, subject_id, class_id FROM teacher_subjects WHERE id = :id");
        $currentStmt->bindParam(':id', $assignmentId, PDO::PARAM_INT);
        $currentStmt->execute();
        $current = $currentStmt->fetch();
        
        // If the assignment is changing, check if the new one already exists
        if ($current['teacher_id'] != $teacherId || $current['subject_id'] != $subjectId || $current['class_id'] != $classId) {
            if (teacherSubjectExists($pdo, $teacherId, $subjectId, $classId)) {
                return [
                    'success' => false,
                    'message' => 'This teacher is already assigned to this subject for this class'
                ];
            }
        }
        
        $stmt = $pdo->prepare("
            UPDATE teacher_subjects 
            SET teacher_id = :teacher_id, 
                subject_id = :subject_id, 
                class_id = :class_id 
            WHERE id = :id
        ");
        
        $stmt->bindParam(':teacher_id', $teacherId, PDO::PARAM_INT);
        $stmt->bindParam(':subject_id', $subjectId, PDO::PARAM_INT);
        $stmt->bindParam(':class_id', $classId, PDO::PARAM_INT);
        $stmt->bindParam(':id', $assignmentId, PDO::PARAM_INT);
        
        $stmt->execute();
        
        return [
            'success' => true,
            'message' => 'Teacher-subject assignment updated successfully'
        ];
    } catch (PDOException $e) {
        error_log("Error updating teacher-subject assignment: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Database error occurred'
        ];
    }
}

// Function to delete a teacher-subject assignment
function deleteTeacherSubject($pdo, $assignmentId) {
    try {
        $stmt = $pdo->prepare("DELETE FROM teacher_subjects WHERE id = :id");
        $stmt->bindParam(':id', $assignmentId, PDO::PARAM_INT);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            return [
                'success' => true,
                'message' => 'Teacher-subject assignment deleted successfully'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Teacher-subject assignment not found'
            ];
        }
    } catch (PDOException $e) {
        error_log("Error deleting teacher-subject assignment: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Database error occurred'
        ];
    }
}

// Function to get a single teacher-subject assignment by ID
function getTeacherSubjectById($pdo, $assignmentId) {
    try {
        $stmt = $pdo->prepare("
            SELECT ts.*, u.name AS teacher_name, s.subject_name, c.class_name
            FROM teacher_subjects ts
            JOIN users u ON ts.teacher_id = u.id
            JOIN subjects s ON ts.subject_id = s.id
            JOIN classes c ON ts.class_id = c.id
            WHERE ts.id = :id
        ");
        $stmt->bindParam(':id', $assignmentId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Error fetching teacher-subject assignment: " . $e->getMessage());
        return false;
    }
}

// Function to get subjects taught by a teacher for a specific class
function getTeacherSubjectsForClass($pdo, $teacherId, $classId) {
    try {
        $stmt = $pdo->prepare("
            SELECT ts.id, s.id as subject_id, s.subject_name
            FROM teacher_subjects ts
            JOIN subjects s ON ts.subject_id = s.id
            WHERE ts.teacher_id = :teacher_id AND ts.class_id = :class_id
            ORDER BY s.subject_name ASC
        ");
        $stmt->bindParam(':teacher_id', $teacherId, PDO::PARAM_INT);
        $stmt->bindParam(':class_id', $classId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error fetching teacher subjects for class: " . $e->getMessage());
        return false;
    }
}

// Function to check if a score exists for a student, exam, subject, and component
function scoreExists($pdo, $studentId, $examId, $subjectId, $componentId) {
    try {
        $stmt = $pdo->prepare("
            SELECT id FROM student_scores 
            WHERE student_id = :student_id 
            AND exam_id = :exam_id 
            AND subject_id = :subject_id 
            AND component_id = :component_id
        ");
        $stmt->bindParam(':student_id', $studentId, PDO::PARAM_INT);
        $stmt->bindParam(':exam_id', $examId, PDO::PARAM_INT);
        $stmt->bindParam(':subject_id', $subjectId, PDO::PARAM_INT);
        $stmt->bindParam(':component_id', $componentId, PDO::PARAM_INT);
        $stmt->execute();
        
        $result = $stmt->fetch();
        return $result ? $result['id'] : false;
    } catch (PDOException $e) {
        error_log("Error checking if score exists: " . $e->getMessage());
        return false;
    }
}

// Function to save or update a student score
function saveStudentScore($pdo, $studentId, $examId, $subjectId, $componentId, $score, $teacherId) {
    try {
        // Check if the score already exists
        $scoreId = scoreExists($pdo, $studentId, $examId, $subjectId, $componentId);
        
        if ($scoreId) {
            // Update existing score
            $stmt = $pdo->prepare("
                UPDATE student_scores 
                SET score = :score, 
                    updated_by = :teacher_id, 
                    updated_at = CURRENT_TIMESTAMP 
                WHERE id = :id
            ");
            $stmt->bindParam(':id', $scoreId, PDO::PARAM_INT);
        } else {
            // Insert new score
            $stmt = $pdo->prepare("
                INSERT INTO student_scores (student_id, exam_id, subject_id, component_id, score, created_by) 
                VALUES (:student_id, :exam_id, :subject_id, :component_id, :score, :teacher_id)
            ");
            $stmt->bindParam(':student_id', $studentId, PDO::PARAM_INT);
            $stmt->bindParam(':exam_id', $examId, PDO::PARAM_INT);
            $stmt->bindParam(':subject_id', $subjectId, PDO::PARAM_INT);
            $stmt->bindParam(':component_id', $componentId, PDO::PARAM_INT);
        }
        
        $stmt->bindParam(':score', $score, PDO::PARAM_INT);
        $stmt->bindParam(':teacher_id', $teacherId, PDO::PARAM_INT);
        
        $stmt->execute();
        
        return [
            'success' => true,
            'message' => 'Score saved successfully'
        ];
    } catch (PDOException $e) {
        error_log("Error saving student score: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Database error occurred'
        ];
    }
}

// Function to get student scores for a specific exam, subject, and component
function getStudentScores($pdo, $examId, $subjectId, $componentId = null) {
    try {
        $sql = "
            SELECT ss.*, s.name AS student_name, s.admission_number
            FROM student_scores ss
            JOIN students s ON ss.student_id = s.id
            WHERE ss.exam_id = :exam_id AND ss.subject_id = :subject_id
        ";
        
        if ($componentId) {
            $sql .= " AND ss.component_id = :component_id";
        }
        
        $sql .= " ORDER BY s.name ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':exam_id', $examId, PDO::PARAM_INT);
        $stmt->bindParam(':subject_id', $subjectId, PDO::PARAM_INT);
        
        if ($componentId) {
            $stmt->bindParam(':component_id', $componentId, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error fetching student scores: " . $e->getMessage());
        return false;
    }
}

// Function to get a student's scores for a specific exam and subject
function getStudentScoresByStudent($pdo, $studentId, $examId, $subjectId) {
    try {
        $stmt = $pdo->prepare("
            SELECT ss.*, ec.name AS component_name, ec.max_marks
            FROM student_scores ss
            JOIN exam_components ec ON ss.component_id = ec.id
            WHERE ss.student_id = :student_id 
            AND ss.exam_id = :exam_id 
            AND ss.subject_id = :subject_id
            ORDER BY ec.display_order ASC
        ");
        $stmt->bindParam(':student_id', $studentId, PDO::PARAM_INT);
        $stmt->bindParam(':exam_id', $examId, PDO::PARAM_INT);
        $stmt->bindParam(':subject_id', $subjectId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error fetching student scores by student: " . $e->getMessage());
        return false;
    }
}

// Function to get exams for a teacher
function getExamsForTeacher($pdo, $teacherId) {
    try {
        // Get the class IDs that this teacher is assigned to
        $classStmt = $pdo->prepare("
            SELECT DISTINCT class_id 
            FROM teacher_subjects 
            WHERE teacher_id = :teacher_id
        ");
        $classStmt->bindParam(':teacher_id', $teacherId, PDO::PARAM_INT);
        $classStmt->execute();
        $classIds = $classStmt->fetchAll(PDO::FETCH_COLUMN);
        
        // If no classes are assigned, return empty array
        if (empty($classIds)) {
            return [];
        }
        
        // Convert array of class IDs to comma-separated string for IN clause
        $classIdList = implode(',', $classIds);
        
        // Get exams for these classes
        $examStmt = $pdo->prepare("
            SELECT e.*, c.class_name
            FROM exams e
            JOIN classes c ON e.class_id = c.id
            WHERE e.class_id IN ($classIdList)
            ORDER BY e.created_at DESC
        ");
        $examStmt->execute();
        return $examStmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error fetching exams for teacher: " . $e->getMessage());
        return [];
    }
}
?>
