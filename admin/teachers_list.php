<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/auth.php';

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

requireAdmin();

// Fetch all teachers with error handling
try {
    // Check if $pdo is set and is a valid PDO instance
    if (!isset($pdo) || !$pdo instanceof PDO) {
        throw new Exception("Database connection error.");
    }
    $teachers = $pdo->query("SELECT * FROM users")->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching teachers: " . $e->getMessage());
    echo "An error occurred while fetching the teachers.";
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teachers List</title>
    <link rel="stylesheet" href="../assets/styles.css">
    <!-- <link rel="stylesheet" href="../assets/css/styles.css"> -->
</head>
<body>
    <div class="container">
        <h1>Teachers List</h1>
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>School</th>
                    <th>Email</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($teachers)): ?>
                    <?php foreach ($teachers as $teacher): ?>
                        <tr>
                            <td><?= htmlspecialchars($teacher['name']) ?></td>
                            <td><?= htmlspecialchars($teacher['school_name']) ?></td>
                            <td><?= htmlspecialchars($teacher['email']) ?></td>
                            <td>
                                <a href="view_recordings.php" class="button">View Recordings</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr> 
                        <td colspan="4">No teachers found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>