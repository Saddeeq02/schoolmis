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
        $user_id = $_SESSION['user_id']; // Current logged-in admin
        
        // Check if admission number already exists
        $checkStmt = $pdo->prepare("SELECT id FROM students WHERE admission_number = ? AND school_id = ?");
        $checkStmt->execute([$admission_number, $currentSchoolId]);
        if ($checkStmt->rowCount() > 0) {
            $errors[] = "Admission number already exists for this school";
        } else {
            // Insert the new student
            $stmt = $pdo->prepare("
                INSERT INTO students 
                (name, admission_number, class_id, school_id, created_by)
                VALUES (?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $name, $admission_number, $class_id, $currentSchoolId, $user_id
            ]);
            
            $_SESSION['success_message'] = "Student added successfully!";
            header("Location: students_list.php?school_id=" . $currentSchoolId);
            exit();
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
   <title>Add Student</title>
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
   <link rel="stylesheet" href="../assets/styles.css">
</head>
<body>
   <div class="container">
       <h2 class="text-white mb-4">Add New Student</h2>
       
       <!-- School Selector Component -->
       <div class="card mb-4">
           <?php renderSchoolSelector($pdo, $_SERVER['PHP_SELF'], $currentSchoolId); ?>
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
                   <input type="text" id="name" name="name" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
               </div>
               
               <div class="form-group">
                   <label for="admission_number">Admission Number*</label>
                   <input type="text" id="admission_number" name="admission_number" required value="<?= htmlspecialchars($_POST['admission_number'] ?? '') ?>">
               </div>
               
               <div class="form-group">
                   <label for="class_id">Class</label>
                   <select id="class_id" name="class_id">
                       <option value="">Select Class</option>
                       <?php foreach ($classes as $class): ?>
                           <option value="<?= $class['id'] ?>" <?= isset($_POST['class_id']) && $_POST['class_id'] == $class['id'] ? 'selected' : '' ?>>
                               <?= htmlspecialchars($class['class_name']) ?>
                           </option>
                       <?php endforeach; ?>
                   </select>
               </div>
               
               <div class="form-group mt-4">
                   <button type="submit" class="btn w-100">Add Student</button>
               </div>
           </form>
       </div>
       
       <div class="mt-4 text-center">
           <a href="students_list.php?school_id=<?= $currentSchoolId ?>" class="btn">Back to Students List</a>
       </div>
   </div>
</body>
</html>
