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

// Function to get all subjects for a program
function getSubjectsByProgram($program) {
    $db = getDB();
    try {
        $stmt = $db->prepare("
            SELECT s.*, c.course_code, c.course_name,
                   (SELECT COUNT(*) FROM instructor_courses ic WHERE ic.subject_id = s.id) as active_classes
            FROM subjects s
            JOIN courses c ON s.course_id = c.id
            WHERE c.course_code = ?
            ORDER BY s.year_level, s.semester
        ");
        $stmt->execute([$program]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching subjects: " . $e->getMessage());
        return [];
    }
}

$subjects = getSubjectsByProgram($program);

// Group subjects by year level
$subjects_by_year = [];
foreach ($subjects as $subject) {
    $year = $subject['year_level'];
    if (!isset($subjects_by_year[$year])) {
        $subjects_by_year[$year] = [];
    }
    $subjects_by_year[$year][] = $subject;
}

include 'header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $program; ?> Subjects - School Grading System</title>
    <style>
        .container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }
        .program-header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .program-title {
            font-size: 28px;
            color: #2c3e50;
            margin: 0;
        }
        .program-subtitle {
            color: #666;
            margin-top: 5px;
            font-size: 16px;
        }
        .year-section {
            margin-bottom: 40px;
        }
        .year-title {
            font-size: 24px;
            color: #2c3e50;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #eee;
        }
        .subjects-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        .subject-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        .subject-card:hover {
            transform: translateY(-5px);
        }
        .subject-code {
            font-size: 18px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 10px;
        }
        .subject-name {
            color: #34495e;
            margin-bottom: 15px;
            font-size: 16px;
        }
        .subject-details {
            font-size: 14px;
            color: #666;
            margin-top: 15px;
        }
        .subject-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        .semester-badge {
            background: #3498db;
            color: white;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 12px;
        }
        .active-classes {
            color: #666;
            font-size: 14px;
        }
        .no-subjects {
            text-align: center;
            padding: 40px;
            background: white;
            border-radius: 10px;
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
    </style>
</head>
<body>
    <div class="container">
        <a href="instructor_dashboard.php" class="back-btn">← Back to Dashboard</a>
        
        <div class="program-header">
            <h1 class="program-title"><?php echo $program; ?> Subjects</h1>
            <p class="program-subtitle">
                <?php echo $program === 'BSCS' ? 'Bachelor of Science in Computer Science' : 'Bachelor of Science in Information Technology'; ?>
            </p>
        </div>

        <?php if (empty($subjects)): ?>
            <div class="no-subjects">
                <h2>No subjects found for <?php echo $program; ?></h2>
                <p>Please contact the administrator to add subjects for this program.</p>
            </div>
        <?php else: ?>
            <?php foreach ($subjects_by_year as $year => $year_subjects): ?>
                <div class="year-section">
                    <h2 class="year-title"><?php echo $year; ?> Year</h2>
                    <div class="subjects-grid">
                        <?php foreach ($year_subjects as $subject): ?>
                            <div class="subject-card">
                                <div class="subject-code"><?php echo htmlspecialchars($subject['subject_code']); ?></div>
                                <div class="subject-name"><?php echo htmlspecialchars($subject['subject_name']); ?></div>
                                <div class="subject-details">
                                    <div>Units: <?php echo $subject['units']; ?></div>
                                    <?php if (!empty($subject['prerequisites'])): ?>
                                        <div>Prerequisites: <?php echo htmlspecialchars($subject['prerequisites']); ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="subject-meta">
                                    <span class="semester-badge">Semester <?php echo $subject['semester']; ?></span>
                                    <span class="active-classes">
                                        <?php echo $subject['active_classes']; ?> Active <?php echo $subject['active_classes'] == 1 ? 'Class' : 'Classes'; ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html> 