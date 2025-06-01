<?php
require_once 'config.php';

try {
    $db = getDB();
    
    // Check if column already exists
    $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'student_id'");
    if ($stmt->rowCount() == 0) {
        // Add the new column
        $db->exec("ALTER TABLE users ADD COLUMN student_id VARCHAR(20) UNIQUE AFTER section");
        echo "Successfully added student_id column to users table.";
    } else {
        echo "student_id column already exists in users table.";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?> 