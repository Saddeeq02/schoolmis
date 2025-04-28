<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/auth.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_class'])) {
        $class_id = $_POST['delete_class'];
        $stmt = $pdo->prepare("DELETE FROM classes WHERE id = ?");
        $stmt->execute([$class_id]);
    } else {
        $class_name = $_POST['class_name'];
        $description = $_POST['description'] ?? null;

        if (isset($_POST['class_id'])) {
            $class_id = $_POST['class_id'];
            $stmt = $pdo->prepare("UPDATE classes SET class_name = ?, description = ? WHERE id = ?");
            $stmt->execute([$class_name, $description, $class_id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO classes (class_name, description) VALUES (?, ?)");
            $stmt->execute([$class_name, $description]);
        }
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

$stmt = $pdo->query("SELECT * FROM classes");
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Classes</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/styles.css">
</head>
<body>
    <div class="container">
        <h2 class="text-white mb-4">Manage Classes</h2>
        
        <div class="card">
            <h3>Add New Class</h3>
            <form method="POST" action="" class="mb-4">
                <div class="form-group">
                    <label for="className">Class Name</label>
                    <input type="text" id="className" name="class_name" required placeholder="Enter class name">
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <input type="text" id="description" name="description" placeholder="Enter class description">
                </div>
                
                <button type="submit" class="btn w-100">Add Class</button>
            </form>
        </div>

        <div class="mt-4">
            <div class="card">
                <h3>Existing Classes</h3>
                <div class="table-responsive">
                    <table class="recordings-table mt-3">
                        <thead>
                            <tr>
                                <th>Class Name</th>
                                <th>Description</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($classes as $class): ?>
                                <tr data-id="<?= $class['id'] ?>">
                                    <td><?= htmlspecialchars($class['class_name']) ?></td>
                                    <td><?= htmlspecialchars($class['description'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($class['created_at']) ?></td>
                                    <td>
                                        <button class="btn" onclick="editClass(<?= $class['id'] ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn" onclick="deleteClass(<?= $class['id'] ?>)">
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

        <!-- Edit Class Modal -->
        <div id="editModal" class="modal" style="display: none;">
            <div class="modal-content card">
                <h3>Edit Class</h3>
                <form id="editForm" method="POST" action="" class="mt-3">
                    <input type="hidden" id="editClassId" name="class_id">
                    <div class="form-group">
                        <label for="editClassName">Class Name</label>
                        <input type="text" id="editClassName" name="class_name" required>
                    </div>
                    <div class="form-group">
                        <label for="editDescription">Description</label>
                        <input type="text" id="editDescription" name="description">
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
        function editClass(classId) {
            // Fetch class details and populate form
            const row = document.querySelector(`tr[data-id="${classId}"]`);
            document.getElementById('editClassId').value = classId;
            document.getElementById('editClassName').value = row.children[0].textContent;
            document.getElementById('editDescription').value = row.children[1].textContent;
            document.getElementById('editModal').style.display = 'flex';
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        function deleteClass(classId) {
            if (confirm('Are you sure you want to delete this class?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="delete_class" value="${classId}">
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