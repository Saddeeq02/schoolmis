<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../components/school_selector.php';

requireAdmin();

// Get current school_id from session or default to first school
$currentSchoolId = $_SESSION['school_id'] ?? null;

// Handle school selection
if (isset($_GET['school_id'])) {
   $currentSchoolId = (int)$_GET['school_id'];
   $_SESSION['school_id'] = $currentSchoolId;
}

// Check if student ID is provided
if (!isset($_GET['id'])) {
    $_SESSION['error_message'] = "No student specified";
    header("Location: students_list.php?school_id=" . $currentSchoolId);
    exit();
}

$student_id = (int)$_GET['id'];

// Get student data
$stmt = $pdo->prepare("SELECT * FROM students WHERE id = ? AND school_id = ?");
$stmt->execute([$student_id, $currentSchoolId]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    $_SESSION['error_message'] = "Student not found or does not belong to the current school";
    header("Location: students_list.php?school_id=" . $currentSchoolId);
    exit();
}

// Get classes for the current school
$classesStmt = $pdo->prepare("SELECT id, class_name FROM classes WHERE school_id = ? ORDER BY class_name ASC");
$classesStmt->execute([$currentSchoolId]);
$classes = $classesStmt->fetchAll(PDO::FETCH_ASSOC);

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Basic validation
    $required_fields = ['name', 'admission_number'];
    $errors = [];
    
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
        }
    }
    
    if (empty($errors)) {
        // Process the form data
        $name = $_POST['name'];
        $admission_number = $_POST['admission_number'];
        $class_id = !empty($_POST['class_id']) ? $_POST['class_id'] : null;
        
        // Check if admission number already exists for another student
        $checkStmt = $pdo->prepare("SELECT id FROM students WHERE admission_number = ? AND school_id = ? AND id != ?");
        $checkStmt->execute([$admission_number, $currentSchoolId, $student_id]);
        if ($checkStmt->rowCount() > 0) {
            $errors[] = "Admission number already exists for another student in this school";
        } else {
            // Update the student
            $stmt = $pdo->prepare("
                UPDATE students SET 
                    name = ?,
                    admission_number = ?,
                    class_id = ?
                WHERE id = ? AND school_id = ?
            ");
            
            $stmt->execute([
                $name, $admission_number, $class_id,
                $student_id, $currentSchoolId
            ]);
            
            $_SESSION['success_message'] = "Student updated successfully!";
            header("Location: students_list.php?school_id=" . $currentSchoolId);
            exit();
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
   <title>Edit Student</title>
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
   <link rel="stylesheet" href="../assets/styles.css">
</head>
<body>
   <div class="container">
       <h2 class="text-white mb-4">Edit Student</h2>
       
       <!-- School Selector Component -->
       <div class="card mb-4">
           <?php renderSchoolSelector($pdo, $_SERVER['PHP_SELF'] . '?id=' . $student_id, $currentSchoolId); ?>
       </div>
       
       <!-- Display errors if any -->
       <?php if (!empty($errors)): ?>
           <div class="alert alert-danger">
               <ul style="margin-bottom: 0;">
                   <?php foreach ($errors as $error): ?>
                       <li><?= htmlspecialchars($error) ?></li>
                   <?php endforeach; ?>
               </ul>
           </div>
       <?php endif; ?>
       
       <div class="card">
           <h3>Student Information</h3>
           <form method="POST" action="" class="mb-4">
               <input type="hidden" name="school_id" value="<?= $currentSchoolId ?>">
               
               <div class="form-group">
                   <label for="name">Full Name*</label>
                   <input type="text" id="name" name="name" required value="<?= htmlspecialchars($student['name']) ?>">
               </div>
               
               <div class="form-group">
                   <label for="admission_number">Admission Number*</label>
                   <input type="text" id="admission_number" name="admission_number" required value="<?= htmlspecialchars($student['admission_number']) ?>">
               </div>
               
               <div class="form-group">
                   <label for="class_id">Class</label>
                   <select id="class_id" name="class_id">
                       <option value="">Select Class</option>
                       <?php foreach ($classes as $class): ?>
                           <option value="<?= $class['id'] ?>" <?= $student['class_id'] == $class['id'] ? 'selected' : '' ?>>
                               <?= htmlspecialchars($class['class_name']) ?>
                           </option>
                       <?php endforeach; ?>
                   </select>
               </div>
               
               <div class="form-group mt-4">
                   <button type="submit" class="btn w-100">Update Student</button>
               </div>
           </form>
       </div>
       
       <div class="mt-4 text-center">
           <a href="students_list.php?school_id=<?= $currentSchoolId ?>" class="btn">Back to Students List</a>
       </div>
   </div>
</body>
</html>
