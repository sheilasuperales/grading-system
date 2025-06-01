<?php
session_start();
require_once 'config.php';

// Ensure only student can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    $_SESSION['error'] = "Access Denied. This area is restricted to students only.";
    header("Location: index.php");
    exit();
}

// Initialize variables to safe defaults
$student = ["fullname" => "Student"];
$courses = [];
$upcoming_assignments = [];
$total_courses = 0;
$overall_grade = 0;
$error = '';

try {
    $db = getDB();
    
    // Get student details
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND role = 'student'");
    $stmt->execute([$_SESSION['user_id']]);
    $student_row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($student_row) {
        $student = $student_row;
    }

    // Get enrolled courses
    $stmt = $db->prepare("
        SELECT c.*, u.fullname as instructor_name,
            (SELECT COUNT(*) FROM assignments WHERE course_id = c.id) as assignment_count,
            (SELECT AVG(g.grade) FROM grades g 
             JOIN assignments a ON g.assignment_id = a.id 
             WHERE g.student_id = ? AND a.course_id = c.id) as average_grade
        FROM courses c
        JOIN enrollments e ON c.id = e.course_id
        LEFT JOIN users u ON c.instructor_id = u.id
        WHERE e.student_id = ?
        ORDER BY c.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch subjects for each course
    foreach ($courses as $i => $course) {
        $stmt2 = $db->prepare("SELECT subject_code, subject_name FROM subjects WHERE course_id = ? ORDER BY year_level, semester, subject_code");
        $stmt2->execute([$course['id']]);
        $courses[$i]['subjects'] = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    }

    // Fetch instructors for each course (many-to-many)
    foreach ($courses as $i => $course) {
        $stmt2 = $db->prepare("SELECT ua.id, ua.username, ua.email, ua.fullname FROM course_instructors ci JOIN user_accounts ua ON ci.instructor_id = ua.id WHERE ci.course_id = ?");
        $stmt2->execute([$course['id']]);
        $courses[$i]['instructors'] = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get upcoming assignments
    $stmt = $db->prepare("
        SELECT a.*, c.course_name, c.course_code,
            (SELECT grade FROM grades WHERE student_id = ? AND assignment_id = a.id) as student_grade
        FROM assignments a
        JOIN courses c ON a.course_id = c.id
        JOIN enrollments e ON c.id = e.course_id
        WHERE e.student_id = ? AND (a.due_date >= CURRENT_DATE OR a.due_date IS NULL)
        ORDER BY a.due_date ASC
        LIMIT 5
    ");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
    $upcoming_assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate overall statistics
    $total_courses = count($courses);
    $completed_assignments = 0;
    $total_assignments = 0;
    $grade_count = 0;

    foreach ($courses as $course) {
        if ($course['average_grade']) {
            $overall_grade += $course['average_grade'];
            $grade_count++;
        }
    }
    $overall_grade = $grade_count ? round($overall_grade / $grade_count, 2) : 0;

} catch (PDOException $e) {
    error_log("Error in student dashboard: " . $e->getMessage());
    $error = "An error occurred while loading the dashboard.";
    $courses = [];
    $upcoming_assignments = [];
    $total_courses = 0;
    $overall_grade = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - School Grading System</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 0;
            background: #f5f7fa;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .dashboard-header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .welcome-text h1 {
            margin: 0;
            color: #2c3e50;
        }
        .welcome-text p {
            margin: 5px 0 0;
            color: #666;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #3498db;
            margin: 10px 0;
        }
        .stat-label {
            color: #666;
        }
        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
        }
        .courses-section, .assignments-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .section-header {
            margin-bottom: 20px;
        }
        .section-header h2 {
            margin: 0;
            color: #2c3e50;
        }
        .course-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        .course-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            border: 1px solid #dee2e6;
        }
        .course-card h3 {
            margin: 0 0 10px 0;
            color: #2c3e50;
        }
        .course-info {
            font-size: 0.9em;
            color: #666;
            margin-bottom: 10px;
        }
        .course-grade {
            font-size: 1.2em;
            font-weight: bold;
            color: #27ae60;
            margin-top: 10px;
        }
        .assignment-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .assignment-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
        }
        .assignment-item:last-child {
            border-bottom: none;
        }
        .assignment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .assignment-title {
            font-weight: 600;
            color: #2c3e50;
        }
        .assignment-course {
            font-size: 0.9em;
            color: #666;
        }
        .due-date {
            font-size: 0.9em;
            color: #e74c3c;
        }
        .btn {
            display: inline-block;
            padding: 8px 16px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 0.9em;
            transition: background 0.3s;
        }
        .btn:hover {
            background: #2980b9;
        }
        .grade-pending {
            color: #f39c12;
        }
        .grade-submitted {
            color: #27ae60;
        }
    </style>
</head>
<body>
    <?php require_once 'header.php'; ?>

    <div class="container">
        <div class="dashboard-header">
            <div class="welcome-text">
                <h1>Welcome, <?php echo htmlspecialchars($student['fullname'] ?? 'Student'); ?></h1>
                <p>Track your courses and assignments</p>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo isset($total_courses) ? $total_courses : 0; ?></div>
                <div class="stat-label">Enrolled Courses</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count($upcoming_assignments ?? []); ?></div>
                <div class="stat-label">Upcoming Assignments</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo isset($overall_grade) ? $overall_grade : 0; ?>%</div>
                <div class="stat-label">Overall Grade</div>
            </div>
        </div>

        <div class="dashboard-grid">
            <div class="courses-section">
                <div class="section-header">
                    <h2>Your Courses</h2>
                </div>
                <div class="course-grid">
                    <?php foreach ($courses ?? [] as $course): ?>
                        <div class="course-card">
                            <h3><?php echo htmlspecialchars($course['course_name']); ?></h3>
                            <div class="course-info">
                                <strong>Course Code:</strong> <?php echo htmlspecialchars($course['course_code']); ?> <br>
                                <strong>Instructor:</strong> <?php echo htmlspecialchars($course['instructor_name'] ?? 'N/A'); ?> <br>
                            </div>
                            <div class="instructors-section">
                                <strong>Instructors:</strong>
                                <?php if (!empty($course['instructors'])): ?>
                                    <ul>
                                    <?php foreach ($course['instructors'] as $instructor): ?>
                                        <li><?php echo htmlspecialchars($instructor['fullname'] ?? $instructor['username']); ?></li>
                                    <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <em>No instructors assigned for this course.</em>
                                <?php endif; ?>
                            </div>
                            <div class="subjects-section">
                                <strong>Subjects:</strong>
                                <?php if (!empty($course['subjects'])): ?>
                                    <ul>
                                    <?php foreach ($course['subjects'] as $subject): ?>
                                        <li><?php echo htmlspecialchars($subject['subject_code']) . ' - ' . htmlspecialchars($subject['subject_name']); ?></li>
                                    <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <em>No subjects found for this course.</em>
                                <?php endif; ?>
                            </div>
                            <?php if ($course['average_grade']): ?>
                                <div class="course-grade">
                                    Grade: <?php echo round($course['average_grade'], 2); ?>%
                                </div>
                            <?php endif; ?>
                            <div style="margin-top: 15px;">
                                <a href="view_course.php?id=<?php echo $course['id']; ?>" class="btn">View Course</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="assignments-section">
                <div class="section-header">
                    <h2>Upcoming Assignments</h2>
                </div>
                <ul class="assignment-list">
                    <?php foreach ($upcoming_assignments ?? [] as $assignment): ?>
                        <li class="assignment-item">
                            <div class="assignment-header">
                                <div class="assignment-title"><?php echo htmlspecialchars($assignment['title']); ?></div>
                                <div class="due-date">
                                    Due: <?php echo $assignment['due_date'] ? date('M d, Y', strtotime($assignment['due_date'])) : 'No due date'; ?>
                                </div>
                            </div>
                            <div class="assignment-course"><?php echo htmlspecialchars($assignment['course_code']); ?></div>
                            <div style="margin-top: 10px;">
                                <?php if (isset($assignment['student_grade'])): ?>
                                    <span class="grade-submitted">Grade: <?php echo $assignment['student_grade']; ?>%</span>
                                <?php else: ?>
                                    <a href="submit_assignment.php?id=<?php echo $assignment['id']; ?>" class="btn">Submit Assignment</a>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
</body>
</html> 