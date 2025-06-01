<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    die('Not logged in as student.');
}

$db = getDB();
$student_id = $_SESSION['user_id'];
echo "<h2>Session user_id: $student_id</h2>";

// Enrollments for this student
$stmt = $db->prepare("SELECT * FROM enrollments WHERE student_id = ?");
$stmt->execute([$student_id]);
$enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<h3>Enrollments for this student</h3><pre>";
print_r($enrollments);
echo "</pre>";

if (empty($enrollments)) {
    echo '<b>No enrollments found for this student.</b>';
    exit;
}

// Courses for these enrollments
$course_ids = array_column($enrollments, 'course_id');
$in = str_repeat('?,', count($course_ids) - 1) . '?';
$stmt = $db->prepare("SELECT * FROM courses WHERE id IN ($in)");
$stmt->execute($course_ids);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<h3>Courses for this student</h3><pre>";
print_r($courses);
echo "</pre>";

// Subjects for these courses
foreach ($courses as $course) {
    echo "<h4>Subjects for course: {$course['course_name']} (ID: {$course['id']})</h4>";
    $stmt2 = $db->prepare("SELECT * FROM subjects WHERE course_id = ?");
    $stmt2->execute([$course['id']]);
    $subjects = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    if ($subjects) {
        echo "<pre>";
        print_r($subjects);
        echo "</pre>";
    } else {
        echo "<em>No subjects found for this course.</em>";
    }
} 