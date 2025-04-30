<?php
session_start();

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Check if user is an admin
function isAdmin() {
    return isLoggedIn() && $_SESSION['role'] === 'admin';
}

// Check if user is a teacher
function isTeacher() {
    return isLoggedIn() && $_SESSION['role'] === 'teacher';
}

// Set up user session data
function setUserSession($user) {
    if (!$user) return false;
    
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['school_name'] = $user['school_name'] ?? 'Default School';
    
    // Get school_id from the schools table
    global $pdo;
    $stmt = $pdo->prepare("SELECT id FROM schools WHERE name = :name");
    $stmt->bindParam(':name', $_SESSION['school_name'], PDO::PARAM_STR);
    $stmt->execute();
    $school = $stmt->fetch();
    
    if ($school) {
        $_SESSION['school_id'] = $school['id'];
    } else {
        // If school doesn't exist, use the first school as default
        $stmt = $pdo->query("SELECT id FROM schools LIMIT 1");
        $defaultSchool = $stmt->fetch();
        $_SESSION['school_id'] = $defaultSchool['id'] ?? 1;
    }
    
    return true;
}

// Add a function to get current school ID
function getCurrentSchoolId() {
    return $_SESSION['school_id'] ?? 1;
}

// Get current user's school name
function getUserSchool() {
    return $_SESSION['school_name'] ?? null;
}

// Redirect to login if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: ../index.php");
        exit();
    }
}

// Redirect to login if not admin
function requireAdmin() {
    if (!isAdmin()) {
        header("Location: ../index.php");
        exit();
    }
}

// Redirect to login if not teacher
function requireTeacher() {
    if (!isTeacher()) {
        header("Location: ../index.php");
        exit();
    }
}
?>
