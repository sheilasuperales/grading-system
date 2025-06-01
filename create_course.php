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

// Function to get subjects for a specific program
function getSubjectsByProgram($program) {
    $db = getDB();
    try {
        $stmt = $db->prepare("
            SELECT s.*, c.course_code, c.course_name 
            FROM subjects s
            JOIN courses c ON s.course_id = c.id
            WHERE c.course_code = ?
            ORDER BY s.year_level, s.semester
        ");
        $stmt->execute([$program]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching subjects: " . $e->getMessage());
        return false;
    }
}

// Function to create a new course
function createCourse($instructorId, $subjectId, $section, $schedule, $max_students, $description) {
    $db = getDB();
    try {
        $stmt = $db->prepare("
            INSERT INTO instructor_courses (
                instructor_id, subject_id, section, schedule, 
                max_students, description, academic_year, semester, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')
        ");
        
        // Get current academic year and semester
        $currentYear = date('Y');
        $month = date('n');
        $academicYear = ($month >= 8) ? $currentYear . '-' . ($currentYear + 1) : ($currentYear - 1) . '-' . $currentYear;
        $semester = ($month >= 8 && $month <= 12) ? '1' : (($month >= 1 && $month <= 3) ? '2' : '3');
        
        return $stmt->execute([
            $instructorId, $subjectId, $section, $schedule, 
            $max_students, $description, $academicYear, $semester
        ]);
    } catch (PDOException $e) {
        error_log("Error creating course: " . $e->getMessage());
        return false;
    }
}

$subjects = getSubjectsByProgram($program);
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subjectId = trim($_POST['subject_id']);
    $section = trim($_POST['section']);
    $schedule = trim($_POST['schedule']);
    $max_students = trim($_POST['max_students']);
    $description = trim($_POST['description']);

    // Validate inputs
    if (empty($subjectId) || empty($section) || empty($schedule) || empty($max_students)) {
        $error = "All fields except description are required.";
    } elseif (!is_numeric($max_students) || $max_students < 1) {
        $error = "Maximum students must be a positive number.";
    } else {
        if (createCourse($_SESSION['user_id'], $subjectId, $section, $schedule, $max_students, $description)) {
            $message = "Course created successfully!";
        } else {
            $error = "Failed to create course. Please try again.";
        }
    }
}

include 'header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Course - <?php echo $program; ?> - School Grading System</title>
    <style>
        .course-container {
            max-width: 800px;
            margin: 40px auto;
            padding: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .program-header {
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 2px solid #eee;
        }
        .program-title {
            font-size: 24px;
            color: #2c3e50;
            margin: 0;
        }
        .program-subtitle {
            color: #666;
            margin-top: 5px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }
        input[type="text"],
        input[type="number"],
        textarea,
        select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            box-sizing: border-box;
        }
        textarea {
            height: 100px;
            resize: vertical;
        }
        .btn-create {
            background: #2c3e50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        .btn-create:hover {
            background: #34495e;
        }
        .message {
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .subject-select {
            margin-bottom: 10px;
        }
        .schedule-hint {
            font-size: 14px;
            color: #666;
            margin-top: 5px;
        }
        optgroup {
            font-weight: bold;
            color: #2c3e50;
        }
    </style>
</head>
<body>
    <div class="course-container">
        <div class="program-header">
            <h2 class="program-title"><?php echo $program; ?></h2>
            <p class="program-subtitle"><?php echo $program === 'BSCS' ? 'Bachelor of Science in Computer Science' : 'Bachelor of Science in Information Technology'; ?></p>
        </div>
        
        <?php if ($message): ?>
            <div class="message success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="subject_id">Select Subject:</label>
                <select id="subject_id" name="subject_id" class="subject-select" required>
                    <option value="">Select a subject</option>
                    <?php
                    $current_year = 0;
                    foreach ($subjects as $subject) {
                        if ($current_year !== $subject['year_level']) {
                            if ($current_year !== 0) {
                                echo '</optgroup>';
                            }
                            $current_year = $subject['year_level'];
                            echo '<optgroup label="' . htmlspecialchars($current_year . ' Year') . '">';
                        }
                        $subject_info = sprintf(
                            "%s - %s (Semester %d)",
                            $subject['subject_code'],
                            $subject['subject_name'],
                            $subject['semester']
                        );
                        echo '<option value="' . $subject['id'] . '">' . htmlspecialchars($subject_info) . '</option>';
                    }
                    if ($current_year !== 0) {
                        echo '</optgroup>';
                    }
                    ?>
                </select>
            </div>

            <div class="form-group">
                <label for="section">Section:</label>
                <input type="text" id="section" name="section" placeholder="e.g., A, B, C" required>
            </div>

            <div class="form-group">
                <label for="schedule">Schedule:</label>
                <input type="text" id="schedule" name="schedule" placeholder="e.g., MWF 9:00-10:30 AM" required>
                <div class="schedule-hint">Format: Days Time (e.g., MWF 9:00-10:30 AM, TTH 1:00-2:30 PM)</div>
            </div>

            <div class="form-group">
                <label for="max_students">Maximum Number of Students:</label>
                <input type="number" id="max_students" name="max_students" min="1" value="40" required>
            </div>

            <div class="form-group">
                <label for="description">Course Description (Optional):</label>
                <textarea id="description" name="description" placeholder="Enter course description, special requirements, or additional information"></textarea>
            </div>

            <button type="submit" class="btn-create">Create Course</button>
        </form>
    </div>
</body>
</html> 