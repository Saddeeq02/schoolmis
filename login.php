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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['school_id'] = $user['school_id'];
            
            // Redirect based on role
            header("Location: {$user['role']}/dashboard.php");
            exit;
        } else {
            $error = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - BI-SMIS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/modern.css">
        <script src="assets/scripts.js"></script>
            <link rel="icon" href="4.png" type="image/x-icon">

    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: var(--spacing-md);
        }
        
        .login-container {
            width: 100%;
            max-width: 400px;
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
        
        @media (max-width: 480px) {
            .login-container {
                padding: 0 var(--spacing-sm);
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <!-- Brand Header -->
        <div class="brand">
            <i class="fas fa-graduation-cap brand-icon"></i>
            <h1>Topspring Gems Comprehensive School - BI:SMIS</h1>
        </div>
        
        <!-- Login Form -->
        <div class="card">
            <h2 class="text-center mb-4">Login to Your Account</h2>
            
            <?php if ($error): ?>
                <div class="alert alert-info mb-4">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <form method="post" action="" class="d-flex flex-column gap-3">
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
                    <label for="password">Password</label>
                    <div class="password-field">
                        <input type="password" 
                               id="password" 
                               name="password" 
                               required 
                               autocomplete="current-password"
                               placeholder="Enter your password">
                        <button type="button" 
                                class="toggle-password" 
                                onclick="togglePassword()"
                                aria-label="Toggle password visibility">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-lg">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>
            </form>
        </div>
        
        <!-- Footer Links -->
        <div class="footer-links">
            <a href="register.php">Create Account</a>
            <span>•</span>
            <a href="student_results.php">Check Results</a>
            <span>•</span>
            <a href="index.php">Home</a>
        </div>
    </div>

    <script>
    function togglePassword() {
        const passwordInput = document.getElementById('password');
        const toggleBtn = document.querySelector('.toggle-password i');
        
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
