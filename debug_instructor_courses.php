<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    die('Not logged in as instructor.');
}

$db = getDB();
$instructor_id = $_SESSION['user_id'];

// Get courses
$stmt = $db->prepare("SELECT * FROM courses WHERE instructor_id = ?");
$stmt->execute([$instructor_id]);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<h2>Courses for Instructor ID: $instructor_id</h2>";
echo "<pre>";
print_r($courses);
echo "</pre>";

// Get subjects for each course
foreach ($courses as $course) {
    $stmt = $db->prepare("SELECT * FROM subjects WHERE course_id = ?");
    $stmt->execute([$course['id']]);
    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<h3>Subjects for Course: " . htmlspecialchars($course['course_code']) . " - " . htmlspecialchars($course['course_name']) . "</h3>";
    echo "<pre>";
    print_r($subjects);
    echo "</pre>";
}
?> 