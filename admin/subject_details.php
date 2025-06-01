<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php?msg=unauthorized');
    exit();
}

// Get parameters from URL
$course_code = $_GET['course'] ?? '';
$subject = $_GET['subject'] ?? '';
$year = $_GET['year'] ?? '';

$valid_courses = ['BSCS', 'BSIT'];

if (!in_array($course_code, $valid_courses) || empty($subject) || empty($year)) {
    header('Location: ../admin_dashboard.php');
    exit();
}

// Subject descriptions and details
$subject_details = [
    'BSIT' => [
        'Introduction to Computing' => [
            'description' => 'This course introduces students to the basic concepts of computing, including hardware, software, and basic programming concepts.',
            'units' => 3,
            'prerequisites' => 'None',
            'learning_outcomes' => [
                'Understand basic computer concepts and terminology',
                'Learn the fundamentals of computer hardware and software',
                'Introduction to basic programming concepts',
                'Understand the role of computing in various fields'
            ]
        ],
        'Computer Programming 1' => [
            'description' => 'Introduction to programming fundamentals using a high-level programming language.',
            'units' => 3,
            'prerequisites' => 'Introduction to Computing',
            'learning_outcomes' => [
                'Write basic computer programs',
                'Understand programming concepts like variables, loops, and conditions',
                'Debug simple programs',
                'Implement basic algorithms'
            ]
        ]
    ],
    'BSCS' => [
        'Introduction to Computing' => [
            'description' => 'Comprehensive introduction to computer science principles and programming fundamentals.',
            'units' => 3,
            'prerequisites' => 'None',
            'learning_outcomes' => [
                'Understand fundamental computing concepts',
                'Learn basic programming principles',
                'Understand computer architecture basics',
                'Introduction to algorithm development'
            ]
        ],
        'Discrete Structures' => [
            'description' => 'Mathematical foundations of computer science, including logic, sets, relations, and graphs.',
            'units' => 3,
            'prerequisites' => 'None',
            'learning_outcomes' => [
                'Understand mathematical logic and proof techniques',
                'Learn set theory and relations',
                'Study graph theory and its applications',
                'Apply discrete mathematics to computer science problems'
            ]
        ]
    ]
];

// Get subject details
$details = $subject_details[$course_code][$subject] ?? [
    'description' => 'Detailed description will be added soon.',
    'units' => 3,
    'prerequisites' => 'To be determined',
    'learning_outcomes' => ['To be updated']
];

// Get enrolled students in this course taking this subject
$db = getDB();
$stmt = $db->prepare("
    SELECT u.*, 
           g.grade_value as current_grade
    FROM users u 
    LEFT JOIN grades g ON u.id = g.student_id
    WHERE u.role = 'student' 
    AND u.course = ?
    AND u.year_level = ?
    ORDER BY u.section, u.fullname
");
$year_level = substr($year, 0, 1); // Extract year number from "1st Year", etc.
$stmt->execute([$course_code, $year_level]);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($subject); ?> - Subject Details</title>
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
        .subject-header {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .subject-title {
            color: #2c3e50;
            margin: 0 0 10px 0;
            font-size: 28px;
        }
        .subject-meta {
            color: #666;
            font-size: 16px;
        }
        .subject-meta span {
            margin-right: 20px;
        }
        .content-section {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .section-title {
            color: #2c3e50;
            margin: 0 0 15px 0;
            font-size: 20px;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
        }
        .learning-outcomes {
            list-style-type: none;
            padding: 0;
        }
        .learning-outcomes li {
            padding: 10px 0;
            border-bottom: 1px solid #eee;
            position: relative;
            padding-left: 25px;
        }
        .learning-outcomes li:before {
            content: "•";
            color: #3498db;
            font-weight: bold;
            position: absolute;
            left: 0;
        }
        .learning-outcomes li:last-child {
            border-bottom: none;
        }
        .students-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .students-table th,
        .students-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .students-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
        }
        .grade {
            font-weight: 600;
        }
        .grade-good {
            color: #27ae60;
        }
        .grade-average {
            color: #f39c12;
        }
        .grade-poor {
            color: #e74c3c;
        }
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #3498db;
            text-decoration: none;
        }
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <?php require_once '../header.php'; ?>

    <div class="container">
        <a href="course_details.php?course=<?php echo urlencode($course_code); ?>" class="back-link">
            ← Back to <?php echo $course_code; ?> Course Details
        </a>

        <div class="subject-header">
            <h1 class="subject-title"><?php echo htmlspecialchars($subject); ?></h1>
            <div class="subject-meta">
                <span><strong>Course:</strong> <?php echo $course_code; ?></span>
                <span><strong>Year Level:</strong> <?php echo $year; ?></span>
                <span><strong>Units:</strong> <?php echo $details['units']; ?></span>
            </div>
        </div>

        <div class="content-section">
            <h2 class="section-title">Course Description</h2>
            <p><?php echo htmlspecialchars($details['description']); ?></p>
        </div>

        <div class="content-section">
            <h2 class="section-title">Prerequisites</h2>
            <p><?php echo htmlspecialchars($details['prerequisites']); ?></p>
        </div>

        <div class="content-section">
            <h2 class="section-title">Learning Outcomes</h2>
            <ul class="learning-outcomes">
                <?php foreach ($details['learning_outcomes'] as $outcome): ?>
                    <li><?php echo htmlspecialchars($outcome); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="content-section">
            <h2 class="section-title">Enrolled Students</h2>
            <?php if (empty($students)): ?>
                <p>No students currently enrolled in this subject.</p>
            <?php else: ?>
                <table class="students-table">
                    <thead>
                        <tr>
                            <th>Student Name</th>
                            <th>Section</th>
                            <th>Current Grade</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($student['fullname']); ?></td>
                                <td><?php echo htmlspecialchars($student['section']); ?></td>
                                <td>
                                    <?php if ($student['current_grade']): ?>
                                        <span class="grade <?php 
                                            if ($student['current_grade'] >= 85) echo 'grade-good';
                                            elseif ($student['current_grade'] >= 75) echo 'grade-average';
                                            else echo 'grade-poor';
                                        ?>">
                                            <?php echo $student['current_grade']; ?>%
                                        </span>
                                    <?php else: ?>
                                        <span>Not yet graded</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>