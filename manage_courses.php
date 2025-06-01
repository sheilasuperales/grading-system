<?php
session_start();
require_once 'config.php';

// Strict super admin access control
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    $_SESSION['error'] = "Access Denied. This area is restricted to super administrators only.";
    header("Location: login.php");
    exit();
}

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

try {
    $db = getDB();

    // Handle course operations
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add_course':
                    if (empty($_POST['course_code']) || empty($_POST['course_name'])) {
                        $error = "Course code and name are required.";
                    } else {
                        $stmt = $db->prepare("INSERT INTO courses (course_code, course_name, description) VALUES (?, ?, ?)");
                        if ($stmt->execute([$_POST['course_code'], $_POST['course_name'], $_POST['description'] ?? null])) {
                            $_SESSION['success'] = "Course added successfully.";
                            header("Location: manage_courses.php");
                            exit();
                        } else {
                            $error = "Failed to add course.";
                        }
                    }
                    break;

                case 'edit_course':
                    if (empty($_POST['course_id']) || empty($_POST['course_code']) || empty($_POST['course_name'])) {
                        $error = "Course ID, code, and name are required.";
                    } else {
                        $stmt = $db->prepare("UPDATE courses SET course_code = ?, course_name = ?, description = ? WHERE id = ?");
                        if ($stmt->execute([$_POST['course_code'], $_POST['course_name'], $_POST['description'] ?? null, $_POST['course_id']])) {
                            $success = "Course updated successfully.";
                        } else {
                            $error = "Failed to update course.";
                        }
                    }
                    break;

                case 'delete_course':
                    if (empty($_POST['course_id'])) {
                        $error = "Course ID is required.";
                    } else {
                        try {
                            $db->beginTransaction();
                            
                            // First delete all subjects associated with the course
                            $stmt = $db->prepare("DELETE FROM subjects WHERE course_id = ?");
                            $stmt->execute([$_POST['course_id']]);
                            
                            // Then delete the course
                            $stmt = $db->prepare("DELETE FROM courses WHERE id = ?");
                            $stmt->execute([$_POST['course_id']]);
                            
                            $db->commit();
                            $_SESSION['success'] = "Course and all related subjects deleted successfully.";
                            header("Location: manage_courses.php");
                            exit();
                        } catch (PDOException $e) {
                            $db->rollBack();
                            $_SESSION['error'] = "Failed to delete course: " . $e->getMessage();
                            header("Location: manage_courses.php");
                            exit();
                        }
                    }
                    break;

                case 'add_subject':
                    if (empty($_POST['course_id']) || empty($_POST['subject_code']) || empty($_POST['subject_name']) || 
                        !isset($_POST['year_level']) || !isset($_POST['semester'])) {
                        $error = "All subject fields are required.";
                    } else {
                        try {
                            $stmt = $db->prepare("INSERT INTO subjects (course_id, subject_code, subject_name, description, units, year_level, semester, prerequisites) 
                                                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                            if ($stmt->execute([
                                $_POST['course_id'],
                                $_POST['subject_code'],
                                $_POST['subject_name'],
                                $_POST['description'] ?? null,
                                $_POST['units'] ?? 3,
                                $_POST['year_level'],
                                $_POST['semester'],
                                $_POST['prerequisites'] ?? null
                            ])) {
                                $_SESSION['success'] = "Subject added successfully.";
                                header("Location: manage_courses.php");
                                exit();
                            } else {
                                $error = "Failed to add subject.";
                            }
                        } catch (PDOException $e) {
                            $error = "Error adding subject: " . $e->getMessage();
                        }
                    }
                    break;

                case 'delete_subject':
                    if (empty($_POST['subject_id'])) {
                        $error = "Subject ID is required.";
                    } else {
                        try {
                            $stmt = $db->prepare("DELETE FROM subjects WHERE id = ?");
                            if ($stmt->execute([$_POST['subject_id']])) {
                                $_SESSION['success'] = "Subject deleted successfully.";
                                header("Location: manage_courses.php");
                                exit();
                            } else {
                                $error = "Failed to delete subject.";
                            }
                        } catch (PDOException $e) {
                            $error = "Error deleting subject: " . $e->getMessage();
                        }
                    }
                    break;
            }
        }
    }

    // Get all courses with their subjects using a single query
    $courses = [];
    $stmt = $db->query("
        SELECT 
            c.id as course_id,
            c.course_code,
            c.course_name,
            c.description as course_description,
            s.id as subject_id,
            s.subject_code,
            s.subject_name,
            s.description as subject_description,
            s.units,
            s.year_level,
            s.semester,
            s.prerequisites
        FROM courses c
        LEFT JOIN subjects s ON c.id = s.course_id
        ORDER BY c.course_code, s.year_level, s.semester, s.subject_code
    ");

    // Process the results
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $course_id = $row['course_id'];
        
        // If this is a new course, initialize it
        if (!isset($courses[$course_id])) {
            $courses[$course_id] = [
                'id' => $course_id,
                'course_code' => $row['course_code'],
                'course_name' => $row['course_name'],
                'description' => $row['course_description'],
                'subjects' => []
            ];
        }
        
        // Add subject if it exists
        if ($row['subject_id']) {
            $courses[$course_id]['subjects'][] = [
                'id' => $row['subject_id'],
                'subject_code' => $row['subject_code'],
                'subject_name' => $row['subject_name'],
                'description' => $row['subject_description'],
                'units' => $row['units'],
                'year_level' => $row['year_level'],
                'semester' => $row['semester'],
                'prerequisites' => $row['prerequisites'],
                'course_id' => $course_id
            ];
        }
    }
    
    // Convert to indexed array
    $courses = array_values($courses);

    // Debug logging
    error_log("Found " . count($courses) . " courses");
    foreach ($courses as $course) {
        error_log("Course: {$course['course_code']} has " . count($course['subjects']) . " subjects");
    }

} catch (PDOException $e) {
    error_log("Error in manage_courses.php: " . $e->getMessage());
    $error = "A system error occurred. Please try again later.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Courses - Super Admin Dashboard</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .content-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }
        input[type="text"], input[type="number"], select, textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .btn {
            background: #3498db;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn:hover {
            background: #2980b9;
        }
        .course-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .course-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
        }
        .course-title {
            margin: 0;
            color: #2c3e50;
            font-size: 1.5em;
        }
        .subject-section {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        .subject-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .subject-item {
            background: white;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .year-section {
            margin-bottom: 20px;
        }
        .year-title {
            color: #2c3e50;
            margin-bottom: 10px;
            border-bottom: 1px solid #e1e8ed;
            padding-bottom: 5px;
        }
        .semester-section {
            margin-left: 20px;
            margin-bottom: 15px;
        }
        .semester-title {
            color: #3498db;
            margin-bottom: 8px;
        }
        .subject-list {
            margin-left: 20px;
        }
        .subject-controls {
            float: right;
        }
        .btn-delete {
            background-color: #dc3545;
            color: white;
            padding: 4px 8px;
            font-size: 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn-delete:hover {
            background-color: #c82333;
        }
        .subject-count {
            color: #666;
            font-size: 0.9em;
            margin-left: 10px;
        }
        .message {
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .success {
            background: #d4edda;
            color: #155724;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
        }
        .modal-content {
            background: white;
            margin: 10% auto;
            padding: 20px;
            width: 80%;
            max-width: 600px;
            border-radius: 8px;
        }
        .close {
            float: right;
            cursor: pointer;
            font-size: 24px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Manage Courses and Subjects</h1>
            <a href="super_admin_dashboard.php" class="btn">Back to Dashboard</a>
        </div>

        <?php if ($success): ?>
            <div class="message success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="content-section">
            <h2>Add New Course</h2>
            <form method="post" action="">
                <input type="hidden" name="action" value="add_course">
                <div class="form-group">
                    <label for="course_code">Course Code:</label>
                    <input type="text" id="course_code" name="course_code" required>
                </div>
                <div class="form-group">
                    <label for="course_name">Course Name:</label>
                    <input type="text" id="course_name" name="course_name" required>
                </div>
                <div class="form-group">
                    <label for="description">Description:</label>
                    <textarea id="description" name="description" rows="3"></textarea>
                </div>
                <button type="submit" class="btn">Add Course</button>
            </form>
        </div>

        <div class="content-section">
            <h2>Existing Courses and Subjects</h2>
            <?php foreach ($courses as $course): ?>
                <div class="course-card">
                    <div class="course-header">
                        <h3 class="course-title">
                            <?php echo htmlspecialchars($course['course_code']); ?> - 
                            <?php echo htmlspecialchars($course['course_name']); ?>
                            <span class="subject-count">(<?php echo count($course['subjects']); ?> subjects)</span>
                        </h3>
                        <form method="post" action="" style="display: inline;">
                            <input type="hidden" name="action" value="delete_course">
                            <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                            <button type="submit" class="btn btn-delete" onclick="return confirm('Are you sure you want to delete this course? This will also delete all subjects under this course.')">Delete Course</button>
                        </form>
                    </div>

                    <div class="subject-section">
                        <form method="post" action="" class="subject-form">
                            <input type="hidden" name="action" value="add_subject">
                            <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                            
                            <div class="form-group">
                                <label for="subject_code">Subject Code:</label>
                                <input type="text" name="subject_code" required>
                            </div>
                            <div class="form-group">
                                <label for="subject_name">Subject Name:</label>
                                <input type="text" name="subject_name" required>
                            </div>
                            <div class="form-group">
                                <label for="year_level">Year Level:</label>
                                <select name="year_level" required>
                                    <option value="1">1st Year</option>
                                    <option value="2">2nd Year</option>
                                    <option value="3">3rd Year</option>
                                    <option value="4">4th Year</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="semester">Semester:</label>
                                <select name="semester" required>
                                    <option value="1">1st Semester</option>
                                    <option value="2">2nd Semester</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="units">Units:</label>
                                <input type="number" name="units" value="3" min="1" max="6" required>
                            </div>
                            <div class="form-group">
                                <label for="prerequisites">Prerequisites:</label>
                                <input type="text" name="prerequisites">
                            </div>
                            <div style="grid-column: 1 / -1;">
                                <button type="submit" class="btn">Add Subject</button>
                            </div>
                        </form>

                        <?php
                        // Group subjects by year and semester
                        $grouped_subjects = [];
                        foreach ($course['subjects'] as $subject) {
                            $year = $subject['year_level'];
                            $sem = $subject['semester'];
                            if (!isset($grouped_subjects[$year])) {
                                $grouped_subjects[$year] = [];
                            }
                            if (!isset($grouped_subjects[$year][$sem])) {
                                $grouped_subjects[$year][$sem] = [];
                            }
                            $grouped_subjects[$year][$sem][] = $subject;
                        }
                        
                        // Display subjects by year and semester
                        ksort($grouped_subjects);
                        foreach ($grouped_subjects as $year => $semesters):
                        ?>
                            <div class="year-section">
                                <h4 class="year-title">Year <?php echo $year; ?></h4>
                                <?php 
                                ksort($semesters);
                                foreach ($semesters as $sem => $subjects): 
                                ?>
                                    <div class="semester-section">
                                        <h5 class="semester-title">Semester <?php echo $sem; ?></h5>
                                        <div class="subject-list">
                                            <?php foreach ($subjects as $subject): ?>
                                                <div class="subject-item">
                                                    <strong><?php echo htmlspecialchars($subject['subject_code']); ?></strong> - 
                                                    <?php echo htmlspecialchars($subject['subject_name']); ?>
                                                    <span style="color: #666;">(<?php echo $subject['units']; ?> units)</span>
                                                    <?php if ($subject['prerequisites']): ?>
                                                        <br>
                                                        <small style="color: #666;">Prerequisites: <?php echo htmlspecialchars($subject['prerequisites']); ?></small>
                                                    <?php endif; ?>
                                                    <div class="subject-controls">
                                                        <form method="post" action="" style="display: inline;">
                                                            <input type="hidden" name="action" value="delete_subject">
                                                            <input type="hidden" name="subject_id" value="<?php echo $subject['id']; ?>">
                                                            <button type="submit" class="btn-delete" onclick="return confirm('Are you sure you want to delete this subject?')">Delete</button>
                                                        </form>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html> 