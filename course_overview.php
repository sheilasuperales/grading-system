<?php
session_start();
require_once 'config.php';

// Check if user is logged in and is instructor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    header("Location: index.php");
    exit();
}

// Get the selected program
$program = isset($_GET['program']) ? $_GET['program'] : '';
if (!in_array($program, ['BSCS', 'BSIT'])) {
    header("Location: instructor_dashboard.php");
    exit();
}

// Function to get course overview data
function getCourseOverview($program) {
    $db = getDB();
    try {
        // Get all subjects for the program
        $stmt = $db->prepare("
            SELECT 
                s.*,
                c.course_name,
                c.course_code,
                (SELECT COUNT(*) FROM instructor_courses ic WHERE ic.subject_id = s.id) as active_classes
            FROM subjects s
            JOIN courses c ON s.course_id = c.id
            WHERE c.course_code = ?
            ORDER BY s.year_level, s.semester
        ");
        $stmt->execute([$program]);
        $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Group subjects by year and semester
        $overview = [];
        foreach ($subjects as $subject) {
            $year = $subject['year_level'];
            $sem = $subject['semester'];
            if (!isset($overview[$year])) {
                $overview[$year] = [];
            }
            if (!isset($overview[$year][$sem])) {
                $overview[$year][$sem] = [];
            }
            $overview[$year][$sem][] = $subject;
        }
        return $overview;
    } catch (PDOException $e) {
        error_log("Error fetching course overview: " . $e->getMessage());
        return [];
    }
}

$course_overview = getCourseOverview($program);
include 'header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $program; ?> Overview - School Grading System</title>
    <style>
        .container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }
        .program-header {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            text-align: center;
        }
        .program-title {
            font-size: 32px;
            color: #2c3e50;
            margin: 0;
        }
        .program-subtitle {
            color: #666;
            margin-top: 10px;
            font-size: 18px;
        }
        .year-section {
            margin-bottom: 40px;
        }
        .year-header {
            background: #2c3e50;
            color: white;
            padding: 15px 25px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .year-title {
            margin: 0;
            font-size: 24px;
        }
        .semester-section {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            overflow: hidden;
        }
        .semester-header {
            background: #f8f9fa;
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
        }
        .semester-title {
            margin: 0;
            color: #2c3e50;
            font-size: 20px;
        }
        .subjects-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            padding: 20px;
        }
        .subject-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            border: 1px solid #eee;
            transition: transform 0.2s;
        }
        .subject-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .subject-code {
            font-weight: bold;
            color: #2c3e50;
            font-size: 18px;
            margin-bottom: 5px;
        }
        .subject-name {
            color: #34495e;
            margin-bottom: 10px;
            font-size: 16px;
        }
        .subject-meta {
            font-size: 14px;
            color: #666;
            margin-top: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 10px;
            border-top: 1px solid #eee;
        }
        .units-badge {
            background: #3498db;
            color: white;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 12px;
        }
        .active-classes {
            color: #666;
        }
        .back-btn {
            display: inline-block;
            padding: 10px 20px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .back-btn:hover {
            background: #2980b9;
        }
        .no-subjects {
            text-align: center;
            padding: 40px;
            background: white;
            border-radius: 10px;
            color: #666;
        }
        .prerequisites {
            font-size: 13px;
            color: #666;
            margin-top: 8px;
        }
        .create-course-btn {
            display: inline-block;
            padding: 12px 25px;
            background: #2ecc71;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 15px;
            transition: background 0.3s;
        }
        .create-course-btn:hover {
            background: #27ae60;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="instructor_dashboard.php" class="back-btn">← Back to Dashboard</a>
        
        <div class="program-header">
            <h1 class="program-title"><?php echo $program; ?></h1>
            <p class="program-subtitle">
                <?php echo $program === 'BSCS' ? 'Bachelor of Science in Computer Science' : 'Bachelor of Science in Information Technology'; ?>
            </p>
            <a href="create_course.php?program=<?php echo $program; ?>" class="create-course-btn">Create New Course</a>
        </div>

        <?php if (empty($course_overview)): ?>
            <div class="no-subjects">
                <h2>No subjects found for <?php echo $program; ?></h2>
                <p>Please contact the administrator to add subjects for this program.</p>
            </div>
        <?php else: ?>
            <?php foreach ($course_overview as $year => $semesters): ?>
                <div class="year-section">
                    <div class="year-header">
                        <h2 class="year-title"><?php echo $year; ?> Year</h2>
                    </div>

                    <?php foreach ($semesters as $semester => $subjects): ?>
                        <div class="semester-section">
                            <div class="semester-header">
                                <h3 class="semester-title">Semester <?php echo $semester; ?></h3>
                            </div>
                            <div class="subjects-grid">
                                <?php foreach ($subjects as $subject): ?>
                                    <div class="subject-card">
                                        <div class="subject-code"><?php echo htmlspecialchars($subject['subject_code']); ?></div>
                                        <div class="subject-name"><?php echo htmlspecialchars($subject['subject_name']); ?></div>
                                        <?php if (!empty($subject['prerequisites'])): ?>
                                            <div class="prerequisites">
                                                Prerequisites: <?php echo htmlspecialchars($subject['prerequisites']); ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="subject-meta">
                                            <span class="units-badge"><?php echo $subject['units']; ?> Units</span>
                                            <span class="active-classes">
                                                <?php echo $subject['active_classes']; ?> Active 
                                                <?php echo $subject['active_classes'] == 1 ? 'Class' : 'Classes'; ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html> 