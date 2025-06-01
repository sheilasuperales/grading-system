<?php
require_once 'config.php';

try {
    $db = getDB();
    
    // Check courses table
    $stmt = $db->query("SELECT * FROM courses");
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Current courses in database:\n";
    foreach ($courses as $course) {
        echo "ID: " . $course['id'] . "\n";
        echo "Course Code: " . $course['course_code'] . "\n";
        echo "Course Name: " . $course['course_name'] . "\n";
        echo "Description: " . $course['description'] . "\n";
        echo "Instructor ID: " . $course['instructor_id'] . "\n";
        echo "-------------------\n";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?> 