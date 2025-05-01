// register.php - Add school selection
<?php
session_start();
include 'includes/db.php';
include 'includes/functions.php';

// Get all schools for the dropdown
$stmt = $pdo->query("SELECT id, name FROM schools ORDER BY name ASC");
$schools = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $role = $_POST['role'];
    $schoolId = $_POST['school_id'];
    
    // Get school name from school_id
    $stmt = $pdo->prepare("SELECT name FROM schools WHERE id = ?");
    $stmt->execute([$schoolId]);
    $school = $stmt->fetch();
    $schoolName = $school['name'] ?? 'Default School';

    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, school_name) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$name, $email, $password, $role, $schoolName]);

    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Register</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
    <div class="container" style="max-width: 400px; margin-top: 10vh;">
        <div class="card">
            <h2 class="text-center text-white mb-4">Register</h2>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-error">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="name">Full Name</label>
                    <input type="text" id="name" name="name" required placeholder="Enter your full name">
                </div>
                
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required placeholder="Enter your email">
                </div>
                
                <div class="form-group">
                    <label for="school_id">School</label>
                    <select id="school_id" name="school_id" required>
                        <option value="">Select School</option>
                        <?php foreach ($schools as $school): ?>
                            <option value="<?= $school['id'] ?>"><?= htmlspecialchars($school['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="role">Role</label>
                    <select id="role" name="role" required>
                        <option value="teacher">Teacher</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required placeholder="Enter your password">
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required placeholder="Confirm your password">
                </div>
                
                <button type="submit" class="btn w-100">Register</button>
            </form>
            
            <div class="text-center mt-3">
                <p class="text-white">Already have an account? <a href="login.php" class="text-white">Login</a></p>
            </div>
        </div>
    </div>
</body>
</html>