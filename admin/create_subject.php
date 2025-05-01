<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/auth.php';

requireAdmin();

// Get all schools for dropdown
$schoolsStmt = $pdo->query("SELECT id, name FROM schools ORDER BY name ASC");
$schools = $schoolsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get current school_id from session or default to first school
$currentSchoolId = $_SESSION['school_id'] ?? null;
if (!$currentSchoolId && !empty($schools)) {
    $currentSchoolId = $schools[0]['id'];
    $_SESSION['school_id'] = $currentSchoolId;
}

// Handle school selection
if (isset($_GET['school_id'])) {
    $currentSchoolId = (int)$_GET['school_id'];
    $_SESSION['school_id'] = $currentSchoolId;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_subject'])) {
        $subject_id = $_POST['delete_subject'];
        $stmt = $pdo->prepare("DELETE FROM subjects WHERE id = ?");
        $stmt->execute([$subject_id]);
    } else {
        $subject_name = $_POST['subject_name'];
        $description = $_POST['description'] ?? null;
        $class_id = $_POST['class_id'];
        $school_id = $_POST['school_id'] ?? $currentSchoolId;

        if (isset($_POST['subject_id'])) {
            $subject_id = $_POST['subject_id'];
            $stmt = $pdo->prepare("UPDATE subjects SET subject_name = ?, description = ?, class_id = ?, school_id = ? WHERE id = ?");
            $stmt->execute([$subject_name, $description, $class_id, $school_id, $subject_id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO subjects (subject_name, description, class_id, school_id) VALUES (?, ?, ?, ?)");
            $stmt->execute([$subject_name, $description, $class_id, $school_id]);
        }
    }

    header("Location: create_subjects.php?school_id=" . $currentSchoolId);
    exit();
}

// Get classes for the current school
$classesStmt = $pdo->prepare("SELECT id, class_name FROM classes WHERE school_id = ? ORDER BY class_name ASC");
$classesStmt->execute([$currentSchoolId]);
$classes = $classesStmt->fetchAll(PDO::FETCH_ASSOC);

// Get subjects for the current school
$subjectsStmt = $pdo->prepare("
    SELECT subjects.id, subjects.subject_name AS name, subjects.description, 
           classes.class_name AS class_name, subjects.created_at 
    FROM subjects 
    LEFT JOIN classes ON subjects.class_id = classes.id
    WHERE subjects.school_id = ?
");
$subjectsStmt->execute([$currentSchoolId]);
$subjects = $subjectsStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage Subjects</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/styles.css">
    <style>
        .school-selector {
            margin-bottom: 20px;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }
        .school-selector select {
            padding: 8px;
            border-radius: 4px;
            border: 1px solid #ced4da;
            margin-left: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2 class="text-white mb-4">Manage Subjects</h2>
        
        <!-- School Selector -->
        <div class="card mb-4">
            <div class="school-selector">
                <form method="GET" action="">
                    <label for="school_id">Select School:</label>
                    <select id="school_id" name="school_id" onchange="this.form.submit()">
                        <?php foreach ($schools as $school): ?>
                            <option value="<?= $school['id'] ?>" <?= $currentSchoolId == $school['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($school['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
        </div>
        
        <div class="card">
            <h3>Add New Subject</h3>
            <form method="POST" action="" class="mb-4">
                <input type="hidden" name="school_id" value="<?= $currentSchoolId ?>">
                
                <div class="form-group">
                    <label for="subjectName">Subject Name</label>
                    <input type="text" id="subjectName" name="subject_name" required placeholder="Enter subject name">
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <input type="text" id="description" name="description" placeholder="Enter subject description">
                </div>
                
                <div class="form-group">
                    <label for="classId">Associated Class</label>
                    <select id="classId" name="class_id" required>
                        <option value="">Select a class...</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?= $class['id'] ?>"><?= htmlspecialchars($class['class_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button type="submit" class="btn w-100">Add Subject</button>
            </form>
        </div>

        <div class="mt-4">
            <div class="card">
                <h3>Existing Subjects</h3>
                <div class="table-responsive">
                    <table class="recordings-table mt-3">
                        <thead>
                            <tr>
                                <th>Subject Name</th>
                                <th>Description</th>
                                <th>Class</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($subjects as $subject): ?>
                                <tr data-id="<?= $subject['id'] ?>">
                                    <td><?= htmlspecialchars($subject['name']) ?></td>
                                    <td><?= htmlspecialchars($subject['description'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($subject['class_name']) ?></td>
                                    <td><?= htmlspecialchars($subject['created_at']) ?></td>
                                    <td>
                                        <button class="btn" onclick="editSubject(<?= $subject['id'] ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn" onclick="deleteSubject(<?= $subject['id'] ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Edit Subject Modal -->
        <div id="editModal" class="modal" style="display: none;">
            <div class="modal-content card">
                <h3>Edit Subject</h3>
                <form id="editForm" method="POST" action="" class="mt-3">
                    <input type="hidden" id="editSubjectId" name="subject_id">
                    <input type="hidden" name="school_id" value="<?= $currentSchoolId ?>">
                    <div class="form-group">
                        <label for="editSubjectName">Subject Name</label>
                        <input type="text" id="editSubjectName" name="subject_name" required>
                    </div>
                    <div class="form-group">
                        <label for="editDescription">Description</label>
                        <input type="text" id="editDescription" name="description">
                    </div>
                    <div class="form-group">
                        <label for="editClassId">Associated Class</label>
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
        function editSubject(subjectId) {
            const row = document.querySelector(`tr[data-id="${subjectId}"]`);
            document.getElementById('editSubjectId').value = subjectId;
            document.getElementById('editSubjectName').value = row.children[0].textContent;
            document.getElementById('editDescription').value = row.children[1].textContent;
            // Find and select the correct class in the dropdown
            const className = row.children[2].textContent;
            const classSelect = document.getElementById('editClassId');
            Array.from(classSelect.options).forEach(option => {
                if (option.text === className) {
                    option.selected = true;
                }
            });
            document.getElementById('editModal').style.display = 'flex';
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        function deleteSubject(subjectId) {
            if (confirm('Are you sure you want to delete this subject?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="delete_subject" value="${subjectId}">
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