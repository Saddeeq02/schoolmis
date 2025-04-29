<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdmin();

// Get all teachers, subjects, and classes for the form
$teachers = getAllTeachers($pdo);
$subjects = $pdo->query("SELECT * FROM subjects ORDER BY subject_name ASC")->fetchAll();
$classes = $pdo->query("SELECT * FROM classes ORDER BY class_name ASC")->fetchAll();

// Handle form submission for adding a new assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_assignment'])) {
        $assignmentId = $_POST['delete_assignment'];
        $result = deleteTeacherSubject($pdo, $assignmentId);
        
        if ($result['success']) {
            $successMessage = $result['message'];
        } else {
            $errorMessage = $result['message'];
        }
    } else {
        $teacherId = $_POST['teacher_id'];
        $subjectId = $_POST['subject_id'];
        $classId = $_POST['class_id'];
        
        if (isset($_POST['assignment_id'])) {
            // Update existing assignment
            $assignmentId = $_POST['assignment_id'];
            $result = updateTeacherSubject($pdo, $assignmentId, $teacherId, $subjectId, $classId);
        } else {
            // Add new assignment
            $result = addTeacherSubject($pdo, $teacherId, $subjectId, $classId);
        }
        
        if ($result['success']) {
            $successMessage = $result['message'];
        } else {
            $errorMessage = $result['message'];
        }
    }
}

// Get all teacher-subject assignments directly from the database
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
    $assignments = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching teacher-subject assignments: " . $e->getMessage());
    $errorMessage = "Error loading assignments. Please try again.";
    $assignments = [];
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Teacher-Subject Assignments</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/styles.css">
</head>
<body>
    <div class="container">
        <h2 class="text-white mb-4">Manage Teacher-Subject Assignments</h2>
        
        <?php if (isset($successMessage)): ?>
            <div class="alert alert-success">
                <?= $successMessage ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($errorMessage)): ?>
            <div class="alert alert-danger">
                <?= $errorMessage ?>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <h3>Add New Assignment</h3>
            <form method="POST" action="" class="mb-4">
                <div class="form-group">
                    <label for="teacherId">Teacher</label>
                    <select id="teacherId" name="teacher_id" required>
                        <option value="">Select a teacher...</option>
                        <?php foreach ($teachers as $teacher): ?>
                            <option value="<?= $teacher['id'] ?>"><?= htmlspecialchars($teacher['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="subjectId">Subject</label>
                    <select id="subjectId" name="subject_id" required>
                        <option value="">Select a subject...</option>
                        <?php foreach ($subjects as $subject): ?>
                            <option value="<?= $subject['id'] ?>"><?= htmlspecialchars($subject['subject_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="classId">Class</label>
                    <select id="classId" name="class_id" required>
                        <option value="">Select a class...</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?= $class['id'] ?>"><?= htmlspecialchars($class['class_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button type="submit" class="btn w-100">Add Assignment</button>
            </form>
        </div>

        <div class="mt-4">
            <div class="card">
                <h3>Existing Assignments</h3>
                <div class="table-responsive">
                    <table class="recordings-table mt-3">
                        <thead>
                            <tr>
                                <th>Teacher</th>
                                <th>Subject</th>
                                <th>Class</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($assignments)): ?>
                                <?php foreach ($assignments as $assignment): ?>
                                    <tr data-id="<?= $assignment['id'] ?>">
                                        <td><?= htmlspecialchars($assignment['teacher_name']) ?></td>
                                        <td><?= htmlspecialchars($assignment['subject_name']) ?></td>
                                        <td><?= htmlspecialchars($assignment['class_name']) ?></td>
                                        <td><?= htmlspecialchars($assignment['created_at']) ?></td>
                                        <td>
                                            <button class="btn" onclick="editAssignment(<?= $assignment['id'] ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn" onclick="deleteAssignment(<?= $assignment['id'] ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5">No assignments found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Edit Assignment Modal -->
        <div id="editModal" class="modal" style="display: none;">
            <div class="modal-content card">
                <h3>Edit Assignment</h3>
                <form id="editForm" method="POST" action="" class="mt-3">
                    <input type="hidden" id="editAssignmentId" name="assignment_id">
                    <div class="form-group">
                        <label for="editTeacherId">Teacher</label>
                        <select id="editTeacherId" name="teacher_id" required>
                            <?php foreach ($teachers as $teacher): ?>
                                <option value="<?= $teacher['id'] ?>"><?= htmlspecialchars($teacher['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="editSubjectId">Subject</label>
                        <select id="editSubjectId" name="subject_id" required>
                            <?php foreach ($subjects as $subject): ?>
                                <option value="<?= $subject['id'] ?>"><?= htmlspecialchars($subject['subject_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="editClassId">Class</label>
                        <select id="editClassId" name="class_id" required>
                            <?php foreach ($classes as $class): ?>
                                <option value="<?= $class['id'] ?>"><?= htmlspecialchars($class['class_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mt-3">
                        <button type="submit" class="btn">Update</button>
                        <button type="button" class="btn" onclick="closeEditModal()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="mt-4 text-center">
            <a href="dashboard.php" class="btn">Back to Dashboard</a>
        </div>
    </div>

    <script>
        function editAssignment(assignmentId) {
            // Fetch assignment details via AJAX
            fetch(`get_assignment.php?id=${assignmentId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('editAssignmentId').value = assignmentId;
                        
                        // Set selected values in dropdowns
                        document.getElementById('editTeacherId').value = data.assignment.teacher_id;
                        document.getElementById('editSubjectId').value = data.assignment.subject_id;
                        document.getElementById('editClassId').value = data.assignment.class_id;
                        
                        document.getElementById('editModal').style.display = 'flex';
                    } else {
                        alert('Error fetching assignment details');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error fetching assignment details');
                });
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        function deleteAssignment(assignmentId) {
            if (confirm('Are you sure you want to delete this assignment?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="delete_assignment" value="${assignmentId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Close modal on outside click
        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            if (event.target === modal) {
                closeEditModal();
            }
        }
    </script>
</body>
</html>
