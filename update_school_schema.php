<?php
require_once 'includes/db.php';

try {
    // Set PDO to throw exceptions
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Start transaction
    $pdo->beginTransaction();

    // 1. Check if school_id column exists
    $checkColumnStmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'school_id'");
    $columnExists = $checkColumnStmt->rowCount() > 0;

    if (!$columnExists) {
        // Add school_id column if it doesn't exist
        $pdo->exec("ALTER TABLE users ADD COLUMN school_id INT NULL");
        $pdo->exec("ALTER TABLE users ADD CONSTRAINT fk_users_school FOREIGN KEY (school_id) REFERENCES schools(id)");
        echo "Added school_id column and foreign key constraint.\n";
    } else {
        echo "school_id column already exists.\n";
    }

    // 2. Set default school for any users without a school_id
    $defaultSchoolStmt = $pdo->query("SELECT id FROM schools WHERE code = 'DEFAULT' LIMIT 1");
    if ($defaultSchoolId = $defaultSchoolStmt->fetchColumn()) {
        $updateStmt = $pdo->prepare("UPDATE users SET school_id = ? WHERE school_id IS NULL OR school_id = 0");
        $updateStmt->execute([$defaultSchoolId]);
        $updatedCount = $updateStmt->rowCount();
        echo "Updated {$updatedCount} users with default school ID.\n";
    } else {
        echo "Warning: No default school found.\n";
    }

    // 3. Make school_id required if not already
    try {
        $pdo->exec("ALTER TABLE users MODIFY COLUMN school_id INT NOT NULL");
        echo "Made school_id column required.\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), "Data truncated") !== false) {
            echo "Warning: Could not make school_id required - some users have NULL values.\n";
        } else {
            throw $e;
        }
    }

    // Commit transaction
    $pdo->commit();
    echo "Migration completed successfully!\n";

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "Error during migration: " . $e->getMessage() . "\n";
    exit(1);
}