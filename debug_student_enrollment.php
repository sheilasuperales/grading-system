<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    die('Not logged in as student.');
}

$db = getDB();
$student_id = $_SESSION['user_id'];

echo "<h2>Student ID: $student_id</h2>";

// Enrollments for this student
$stmt = $db->prepare("SELECT * FROM enrollments WHERE student_id = ?");
$stmt->execute([$student_id]);
$enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<h3>Enrollments for this student</h3><pre>";
print_r($enrollments);
echo "</pre>";

// All courses
$stmt = $db->query("SELECT * FROM courses");
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<h3>All Courses</h3><pre>";
print_r($courses);
echo "</pre>";

// All subjects
$stmt = $db->query("SELECT * FROM subjects");
$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<h3>All Subjects</h3><pre>";
print_r($subjects);
echo "</pre>";

// All enrollments
$stmt = $db->query("SELECT * FROM enrollments");
$all_enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<h3>All Enrollments</h3><pre>";
print_r($all_enrollments);
echo "</pre>"; 