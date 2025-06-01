<?php
session_start();
require_once 'config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php?msg=unauthorized');
    exit();
}

// Get course code from URL
$course_code = $_GET['course'] ?? '';
$valid_courses = ['BSCS', 'BSIT'];

if (!in_array($course_code, $valid_courses)) {
    header('Location: admin_dashboard.php');
    exit();
}

// Define course subjects
$course_subjects = [
    'BSIT' => [
        '1st Year' => [
            'Introduction to Computing',
            'Computer Programming 1',
            'Computer Programming 2',
            'Web Systems and Technologies'
        ],
        '2nd Year' => [
            'Object-Oriented Programming',
            'Data Structure and Algorithms',
            'Platform Technologies',
            'Information Management (Databases)',
            'Networking 1'
        ],
        '3rd Year' => [
            'Networking 2',
            'Application Development 1',
            'System Analysis and Design',
            'Operating Systems',
            'Information Assurance and Security',
            'System Integration and Architecture',
            'IT Electives'
        ],
        '4th Year' => [
            'Capstone Project 2',
            'Internship / Practicum'
        ]
    ],
    'BSCS' => [
        '1st Year' => [
            'Introduction to Computing',
            'Computer Programming 1',
            'Computer Programming 2',
            'Discrete Structures',
            'Data Structures and Algorithms'
        ],
        '2nd Year' => [
            'Object-Oriented Programming',
            'Computer Organization and Architecture',
            'Operating System',
            'Algorithm and Complexity',
            'Software Engineering',
            'Automata and Formal Languages',
            'Human-Computer Interaction',
            'Information Management (Database System)'
        ],
        '3rd Year' => [
            'Programming Language',
            'Web Programming',
            'Artificial Intelligence',
            'Computer Networks',
            'Theory of Computation',
            'Systems Programming',
            'Capstone Project 1'
        ],
        '4th Year' => [
            'Capstone Project 2',
            'Internship / Practicum',
            'Electives'
        ]
    ]
];

// Get course details from database
$db = getDB();

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

$course_names = [
    'BSCS' => 'BS Computer Science',
    'BSIT' => 'BS Information Technology'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $course_names[$course_code]; ?> - Course Details</title>
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
        }
        .subjects-list li:last-child {
            border-bottom: none;
        }
        .students-table {
            width: 100%;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-collapse: collapse;
            margin-top: 20px;
        }
        .students-table th,
        .students-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .students-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
        }
        .students-table tr:last-child td {
            border-bottom: none;
        }
        .status-active {
            color: #27ae60;
            font-weight: 500;
        }
        .status-inactive {
            color: #e74c3c;
            font-weight: 500;
        }
        .curriculum-section {
            margin: 30px 0;
        }
        .curriculum-title {
            color: #2c3e50;
            margin-bottom: 20px;
            font-size: 22px;
        }
        .subject-link {
            color: #3498db;
            text-decoration: none;
        }
        .subject-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <?php require_once 'header.php'; ?>

    <div class="container">
        <div class="course-header">
            <h1 class="course-title"><?php echo $course_names[$course_code]; ?></h1>
            <p>Course Code: <?php echo $course_code; ?></p>
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
                <div class="stat-number"><?php echo $average_grade; ?>%</div>
                <div class="stat-label">Average Grade</div>
            </div>
        </div>

        <div class="curriculum-section">
            <h2 class="curriculum-title">Curriculum Overview</h2>
            <?php foreach ($course_subjects[$course_code] as $year => $subjects): ?>
                <div class="year-section">
                    <h3 class="year-title"><?php echo $year; ?></h3>
                    <ul class="subjects-list">
                        <?php foreach ($subjects as $subject): ?>
                            <li>
                                <a href="subject_details.php?course=<?php echo urlencode($course_code); ?>&subject=<?php echo urlencode($subject); ?>&year=<?php echo urlencode($year); ?>" class="subject-link">
                                    <?php echo htmlspecialchars($subject); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        </div>

        <h2 class="curriculum-title">Enrolled Students</h2>
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
</body>
</html> 