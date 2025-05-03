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
        echo "Added school_id column.\n";
    }

    // 2. Drop existing foreign key constraints if they exist
    try {
        $pdo->exec("ALTER TABLE users DROP FOREIGN KEY fk_users_school");
        echo "Dropped fk_users_school constraint.\n";
    } catch (PDOException $e) {
        // Ignore error if constraint doesn't exist
        echo "Note: fk_users_school constraint not found.\n";
    }
    
    try {
        $pdo->exec("ALTER TABLE users DROP FOREIGN KEY fk_users_school_id");
        echo "Dropped fk_users_school_id constraint.\n";
    } catch (PDOException $e) {
        // Ignore error if constraint doesn't exist
        echo "Note: fk_users_school_id constraint not found.\n";
    }

    // 3. Check if school_ids column exists before trying to transfer data
    $checkSchoolIdsStmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'school_ids'");
    if ($checkSchoolIdsStmt->rowCount() > 0) {
        // Transfer data from school_ids to school_id
        $pdo->exec("UPDATE users SET school_id = school_ids WHERE school_id IS NULL AND school_ids IS NOT NULL");
        echo "Transferred data from school_ids to school_id.\n";
        
        // Drop the old school_ids column
        $pdo->exec("ALTER TABLE users DROP COLUMN school_ids");
        echo "Dropped old school_ids column.\n";
    } else {
        echo "Note: school_ids column already removed.\n";
    }

    // 4. Add new foreign key constraint
    $pdo->exec("ALTER TABLE users ADD CONSTRAINT fk_users_school_id FOREIGN KEY (school_id) REFERENCES schools(id)");
    echo "Added new foreign key constraint.\n";

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