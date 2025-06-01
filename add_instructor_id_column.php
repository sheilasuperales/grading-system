<?php
require_once 'config.php';

try {
    $db = getDB();
    // Check if instructor_id column exists
    $stmt = $db->query("SHOW COLUMNS FROM courses LIKE 'instructor_id'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE courses ADD COLUMN instructor_id INT NULL AFTER description");
        echo "Successfully added instructor_id column to courses table.";
    } else {
        echo "instructor_id column already exists in courses table.";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?> 