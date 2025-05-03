<?php
session_start();
require_once 'includes/db.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'];
    header("Location: {$role}/dashboard.php");
    exit;
}

$error = '';
$success = '';

// Get schools list for dropdown
$stmt = $pdo->query("SELECT id, name, code FROM schools ORDER BY name");
$schools = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    $schoolId = $_POST['school_id'];
    $schoolCode = trim($_POST['school_code']);
    
    // Validate inputs
    if (empty($name) || empty($email) || empty($password) || empty($confirmPassword) || empty($schoolId) || empty($schoolCode)) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } else {
        // Verify school code and get school name
        $stmt = $pdo->prepare("SELECT name FROM schools WHERE id = ? AND code = ?");
        $stmt->execute([$schoolId, $schoolCode]);
        $school = $stmt->fetch();
        
        if (!$school) {
            $error = 'Invalid school code. Please verify with your school administrator.';
        } else {
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'Email address is already registered.';
            } else {
                // Create new user with teacher role and school ID
                $stmt = $pdo->prepare("
                    INSERT INTO users (name, email, password, role, school_id) 
                    VALUES (?, ?, ?, 'teacher', ?)
                ");
                
                try {
                    $stmt->execute([
                        $name,
                        $email,
                        password_hash($password, PASSWORD_DEFAULT),
                        $schoolId
                    ]);
                    
                    $success = 'Registration successful! You can now login.';
                    $_POST = [];
                    
                } catch (PDOException $e) {
                    $error = 'Registration failed. Please try again.';
                    error_log($e->getMessage());
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Registration - School MIS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- <link rel="stylesheet" href="assets/clean-styles.css"> -->
    <style>
        :root {
            --primary: #4a90e2;
            --secondary: #67B26F;
            --text: #2c3e50;
            --text-light: #95a5a6;
            --background: #f8f9fa;
            --spacing-xs: 0.25rem;
            --spacing-sm: 0.5rem;
            --spacing-md: 1rem;
            --spacing-lg: 1.5rem;
            --radius: 0.5rem;
        }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: var(--spacing-md);
            background-color: var(--background);
            color: var(--text);
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }
        
        .register-container {
            width: 100%;
            max-width: 500px;
        }
        
        .brand {
            text-align: center;
            margin-bottom: var(--spacing-lg);
        }
        
        .brand-icon {
            font-size: 3rem;
            color: var(--primary);
            margin-bottom: var(--spacing-sm);
        }

        .card {
            background: white;
            padding: var(--spacing-lg);
            border-radius: var(--radius);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .footer-links {
            text-align: center;
            margin-top: var(--spacing-lg);
        }
        
        .footer-links a {
            color: var(--text-light);
            text-decoration: none;
            margin: 0 var(--spacing-sm);
        }
        
        .footer-links a:hover {
            color: var(--primary);
        }
        
        .password-field {
            position: relative;
        }
        
        .toggle-password {
            position: absolute;
            right: var(--spacing-md);
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-light);
            cursor: pointer;
            padding: var(--spacing-xs);
        }
        
        .toggle-password:hover {
            color: var(--primary);
        }
        
        .password-requirements {
            font-size: 0.875rem;
            color: var(--text-light);
            margin-top: var(--spacing-xs);
        }

        .form-group {
            margin-bottom: var(--spacing-md);
        }

        label {
            display: block;
            margin-bottom: var(--spacing-xs);
            color: var(--text);
            font-weight: 500;
        }

        input:not([type="submit"]), select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: var(--radius);
            font-size: 1rem;
            transition: border-color 0.2s;
        }

        input:focus, select:focus {
            outline: none;
            border-color: var(--primary);
        }

        .btn {
            width: 100%;
            padding: 0.75rem;
            border: none;
            border-radius: var(--radius);
            background: linear-gradient(to right, var(--primary), var(--secondary));
            color: white;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: opacity 0.2s;
        }

        .btn:hover {
            opacity: 0.9;
        }

        .alert {
            padding: var(--spacing-md);
            border-radius: var(--radius);
            margin-bottom: var(--spacing-md);
        }

        .alert-danger {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .alert-success {
            background-color: #dcfce7;
            color: #166534;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <!-- Brand Header -->
        <div class="brand">
            <i class="fas fa-school brand-icon"></i>
            <h1>School MIS</h1>
            <p>Teacher Registration</p>
        </div>
        
        <!-- Register Form -->
        <div class="card">
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?= htmlspecialchars($success) ?>
                    <p class="mt-2 mb-0">
                        <a href="login.php" class="btn btn-sm">Login Now</a>
                    </p>
                </div>
            <?php endif; ?>
            
            <form method="post" action="" class="d-flex flex-column gap-3">
                <div class="form-group">
                    <label for="name">Full Name</label>
                    <input type="text" 
                           id="name" 
                           name="name" 
                           value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                           required 
                           placeholder="Enter your full name">
                </div>
                
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" 
                           id="email" 
                           name="email" 
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                           required 
                           autocomplete="email"
                           placeholder="Enter your email">
                </div>
                
                <div class="form-group">
                    <label for="school_id">School</label>
                    <select name="school_id" id="school_id" required>
                        <option value="">Select your school...</option>
                        <?php foreach ($schools as $school): ?>
                            <option value="<?= $school['id'] ?>" 
                                    <?= isset($_POST['school_id']) && $_POST['school_id'] == $school['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($school['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="school_code">School Code</label>
                    <input type="text" 
                           id="school_code" 
                           name="school_code" 
                           value="<?= htmlspecialchars($_POST['school_code'] ?? '') ?>"
                           required 
                           placeholder="Enter the school code provided by your administrator">
                    <small class="password-requirements">Contact your school administrator for the code</small>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-field">
                        <input type="password" 
                               id="password" 
                               name="password" 
                               required 
                               minlength="6"
                               autocomplete="new-password"
                               placeholder="Create a password">
                        <button type="button" 
                                class="toggle-password" 
                                onclick="togglePassword('password')"
                                aria-label="Toggle password visibility">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="password-requirements">
                        Must be at least 6 characters long
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <div class="password-field">
                        <input type="password" 
                               id="confirm_password" 
                               name="confirm_password" 
                               required 
                               minlength="6"
                               autocomplete="new-password"
                               placeholder="Confirm your password">
                        <button type="button" 
                                class="toggle-password" 
                                onclick="togglePassword('confirm_password')"
                                aria-label="Toggle password visibility">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <button type="submit" class="btn">
                    <i class="fas fa-user-plus"></i> Create Teacher Account
                </button>
            </form>
        </div>
        
        <!-- Footer Links -->
        <div class="footer-links">
            <a href="login.php">Already have an account?</a>
            <span>•</span>
            <a href="index.php">Home</a>
        </div>
    </div>

    <script>
    function togglePassword(inputId) {
        const passwordInput = document.getElementById(inputId);
        const toggleBtn = passwordInput.nextElementSibling.querySelector('i');
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            toggleBtn.classList.remove('fa-eye');
            toggleBtn.classList.add('fa-eye-slash');
        } else {
            passwordInput.type = 'password';
            toggleBtn.classList.remove('fa-eye-slash');
            toggleBtn.classList.add('fa-eye');
        }
    }
    </script>
</body>
</html>