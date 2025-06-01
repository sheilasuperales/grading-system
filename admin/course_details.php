<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php?msg=unauthorized');
    exit();
}

// Get course code from URL
$course_code = $_GET['course'] ?? '';

// Get course details from database
$db = getDB();

// First verify if the course exists
$stmt = $db->prepare("SELECT id, course_code, course_name FROM courses WHERE course_code = ?");
$stmt->execute([$course_code]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$course) {
    header('Location: ../admin_dashboard.php');
    exit();
}

// Get students in this course
$stmt = $db->prepare("
    SELECT u.*, 
           (SELECT COUNT(*) FROM grades g 
            JOIN assignments a ON g.assignment_id = a.id 
            WHERE g.student_id = u.id) as completed_assignments,
           (SELECT AVG(grade) FROM grades g 
            JOIN assignments a ON g.assignment_id = a.id 
            WHERE g.student_id = u.id) as average_grade
    FROM users u 
    WHERE u.role = 'student' 
    AND u.course = ?
    ORDER BY u.year_level, u.section, u.fullname
");
$stmt->execute([$course_code]);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get course statistics
$total_students = count($students);
$active_students = array_filter($students, function($student) {
    return isset($student['is_active']) && $student['is_active'] == 1;
});
$total_active = count($active_students);

// Calculate average grade
$grades = array_filter(array_column($students, 'average_grade'));
$average_grade = $grades ? round(array_sum($grades) / count($grades), 2) : 0;

// Get subjects for this course grouped by year and semester
$stmt = $db->prepare("
    SELECT 
        year_level,
        semester,
        subject_code,
        subject_name,
        units
    FROM subjects 
    WHERE course_id = ? 
    ORDER BY year_level, semester, subject_code
");
$stmt->execute([$course['id']]);
$subjects_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Organize subjects by year
$course_subjects = [];
foreach ($subjects_raw as $subject) {
    $year_key = $subject['year_level'] . ordinal_suffix($subject['year_level']) . ' Year';
    if (!isset($course_subjects[$year_key])) {
        $course_subjects[$year_key] = [];
    }
    $course_subjects[$year_key][] = $subject;
}

// Helper function to add ordinal suffix
function ordinal_suffix($number) {
    $ends = array('th','st','nd','rd','th','th','th','th','th','th');
    if ((($number % 100) >= 11) && (($number % 100) <= 13)) {
        return 'th';
    } else {
        return $ends[$number % 10];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($course['course_name']); ?> - Course Details</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            color: #333;
            line-height: 1.6;
        }
        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 20px;
        }
        .course-header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .course-title {
            color: #2c3e50;
            margin: 0;
            font-size: 24px;
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
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #3498db;
            margin: 10px 0;
        }
        .stat-label {
            color: #666;
            font-size: 14px;
        }
        .year-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .year-title {
            color: #2c3e50;
            margin: 0 0 15px 0;
            font-size: 20px;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
        }
        .subjects-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .subjects-list li {
            padding: 10px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .subjects-list li:last-child {
            border-bottom: none;
        }
        .subject-info {
            flex-grow: 1;
        }
        .subject-code {
            font-weight: bold;
            color: #2c3e50;
            margin-right: 10px;
        }
        .subject-units {
            color: #666;
            font-size: 0.9em;
        }
        .semester-label {
            color: #3498db;
            font-size: 0.9em;
            margin-left: 10px;
        }
    </style>
</head>
<body>
    <?php require_once '../header.php'; ?>

    <div class="container">
        <div class="course-header">
            <h1 class="course-title"><?php echo htmlspecialchars($course['course_name']); ?> (<?php echo htmlspecialchars($course['course_code']); ?>)</h1>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_students; ?></div>
                <div class="stat-label">Total Students</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_active; ?></div>
                <div class="stat-label">Active Students</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $average_grade; ?></div>
                <div class="stat-label">Average Grade</div>
            </div>
        </div>

        <?php foreach ($course_subjects as $year => $subjects): ?>
        <div class="year-section">
            <h2 class="year-title"><?php echo htmlspecialchars($year); ?></h2>
            <ul class="subjects-list">
                <?php foreach ($subjects as $subject): ?>
                <li>
                    <div class="subject-info">
                        <span class="subject-code"><?php echo htmlspecialchars($subject['subject_code']); ?></span>
                        <?php echo htmlspecialchars($subject['subject_name']); ?>
                        <span class="subject-units">(<?php echo $subject['units']; ?> units)</span>
                    </div>
                    <span class="semester-label">Semester <?php echo $subject['semester']; ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endforeach; ?>

        <div class="students-section">
            <h2 class="section-title">Enrolled Students</h2>
            <table class="students-table">
                <thead>
                    <tr>
                        <th>Student Name</th>
                        <th>Year Level</th>
                        <th>Section</th>
                        <th>Completed Assignments</th>
                        <th>Average Grade</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $student): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($student['fullname']); ?></td>
                            <td><?php echo $student['year_level']; ?></td>
                            <td><?php echo htmlspecialchars($student['section']); ?></td>
                            <td><?php echo $student['completed_assignments']; ?></td>
                            <td><?php echo $student['average_grade'] ? round($student['average_grade'], 2) . '%' : 'N/A'; ?></td>
                            <td>
                                <span class="status-<?php echo $student['is_active'] ? 'active' : 'inactive'; ?>">
                                    <?php echo $student['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html> 