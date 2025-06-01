<?php
require_once 'config.php';

try {
    $db = getDB();
    $db->exec("CREATE TABLE IF NOT EXISTS course_instructors (
        id INT AUTO_INCREMENT PRIMARY KEY,
        course_id INT NOT NULL,
        instructor_id INT NOT NULL,
        FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
        FOREIGN KEY (instructor_id) REFERENCES user_accounts(id) ON DELETE CASCADE
    )");
    echo "course_instructors table created or already exists.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
} 