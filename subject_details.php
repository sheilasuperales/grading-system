<?php
session_start();
require_once 'config.php';

// Check if user is logged in and is instructor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    header("Location: index.php");
    exit();
}

// Function to get all subjects from both programs
function getAllSubjects() {
    $db = getDB();
    try {
        $stmt = $db->prepare("
            SELECT 
                s.*,
                c.course_code,
                c.course_name as program_name,
                (SELECT COUNT(*) FROM instructor_courses ic WHERE ic.subject_id = s.id) as active_classes
            FROM subjects s
            JOIN courses c ON s.course_id = c.id
            ORDER BY c.course_code, s.year_level, s.semester
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching subjects: " . $e->getMessage());
        return [];
    }
}

$all_subjects = getAllSubjects();

// Group subjects by program and year
$subjects_by_program = [];
foreach ($all_subjects as $subject) {
    $program = $subject['course_code'];
    $year = $subject['year_level'];
    
    if (!isset($subjects_by_program[$program])) {
        $subjects_by_program[$program] = [];
    }
    if (!isset($subjects_by_program[$program][$year])) {
        $subjects_by_program[$program][$year] = [];
    }
    
    $subjects_by_program[$program][$year][] = $subject;
}

include 'header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Subjects - School Grading System</title>
    <style>
        .container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }
        .page-header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            text-align: center;
        }
        .page-title {
            font-size: 32px;
            color: #2c3e50;
            margin: 0;
        }
        .program-section {
            margin-bottom: 50px;
        }
        .program-header {
            background: #2c3e50;
            color: white;
            padding: 15px 25px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        .program-title {
            font-size: 24px;
            margin: 0;
        }
        .year-section {
            margin-bottom: 40px;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .year-title {
            font-size: 20px;
            color: #2c3e50;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #eee;
        }
        .subjects-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .subjects-table th,
        .subjects-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .subjects-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
        }
        .subjects-table tr:hover {
            background: #f8f9fa;
        }
        .subject-code {
            font-weight: 600;
            color: #2c3e50;
        }
        .semester-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 12px;
            color: white;
        }
        .semester-1 { background: #3498db; }
        .semester-2 { background: #2ecc71; }
        .semester-3 { background: #e74c3c; }
        .prerequisites {
            color: #666;
            font-size: 14px;
        }
        .active-classes {
            font-size: 14px;
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
        .program-stats {
            display: flex;
            justify-content: space-between;
            margin-top: 10px;
            color: rgba(255,255,255,0.8);
            font-size: 14px;
        }
        .no-subjects {
            text-align: center;
            padding: 40px;
            background: white;
            border-radius: 10px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="instructor_dashboard.php" class="back-btn">← Back to Dashboard</a>
        
        <div class="page-header">
            <h1 class="page-title">Complete Subject List</h1>
        </div>

        <?php if (empty($all_subjects)): ?>
            <div class="no-subjects">
                <h2>No subjects found</h2>
                <p>Please contact the administrator to add subjects to the system.</p>
            </div>
        <?php else: ?>
            <?php foreach ($subjects_by_program as $program => $years): ?>
                <div class="program-section">
                    <div class="program-header">
                        <h2 class="program-title">
                            <?php echo $program === 'BSCS' ? 'BS Computer Science' : 'BS Information Technology'; ?>
                        </h2>
                        <div class="program-stats">
                            <span>Total Subjects: <?php echo array_sum(array_map('count', $years)); ?></span>
                            <span>Total Years: <?php echo count($years); ?></span>
                        </div>
                    </div>

                    <?php foreach ($years as $year => $subjects): ?>
                        <div class="year-section">
                            <h3 class="year-title"><?php echo $year; ?> Year</h3>
                            <table class="subjects-table">
                                <thead>
                                    <tr>
                                        <th>Subject Code</th>
                                        <th>Subject Name</th>
                                        <th>Units</th>
                                        <th>Semester</th>
                                        <th>Prerequisites</th>
                                        <th>Active Classes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($subjects as $subject): ?>
                                        <tr>
                                            <td class="subject-code">
                                                <?php echo htmlspecialchars($subject['subject_code']); ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($subject['subject_name']); ?></td>
                                            <td><?php echo $subject['units']; ?></td>
                                            <td>
                                                <span class="semester-badge semester-<?php echo $subject['semester']; ?>">
                                                    Semester <?php echo $subject['semester']; ?>
                                                </span>
                                            </td>
                                            <td class="prerequisites">
                                                <?php echo !empty($subject['prerequisites']) ? 
                                                    htmlspecialchars($subject['prerequisites']) : 
                                                    '<em>None</em>'; ?>
                                            </td>
                                            <td class="active-classes">
                                                <?php echo $subject['active_classes']; ?> 
                                                <?php echo $subject['active_classes'] == 1 ? 'Class' : 'Classes'; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html> 