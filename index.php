<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>School Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
    <div class="container">
        <div class="card text-center">
            <h1>Welcome to School Management System</h1>
            
            <div class="dashboard-grid">
                <a href="login.php" class="card">
                    <i class="fas fa-sign-in-alt mb-3" style="font-size: 2rem; color: var(--primary);"></i>
                    <h3>Login</h3>
                    <p class="text-light">Access your account</p>
                </a>
                
                <a href="register.php" class="card">
                    <i class="fas fa-user-plus mb-3" style="font-size: 2rem; color: var(--secondary);"></i>
                    <h3>Register</h3>
                    <p class="text-light">Create a new account</p>
                </a>
                
                <a href="student_results.php" class="card">
                    <i class="fas fa-graduation-cap mb-3" style="font-size: 2rem; color: var(--success);"></i>
                    <h3>Student Results</h3>
                    <p class="text-light">Check your exam results</p>
                </a>
            </div>
            
            <div class="mt-4">
                <a href="login.php" class="btn btn-lg">Get Started</a>
            </div>
        </div>
    </div>
</body>
</html>
