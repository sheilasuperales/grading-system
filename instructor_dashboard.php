<?php
session_start();
require_once 'config.php';

// Ensure only instructor can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    $_SESSION['error'] = "Access Denied. This area is restricted to instructors only.";
    header("Location: index.php");
    exit();
}

// Function to get instructor's courses
function getInstructorCourses($db, $instructor_id) {
    try {
        error_log("Starting getInstructorCourses function for instructor_id: " . $instructor_id);
        
        // First check if courses table exists
        $check_table = $db->query("SHOW TABLES LIKE 'courses'");
        if ($check_table->rowCount() == 0) {
            error_log("Courses table does not exist!");
            return [];
        }

        // Check if there are any courses for this instructor
        $check_courses = $db->prepare("SELECT COUNT(*) as count FROM courses WHERE instructor_id = ?");
        $check_courses->execute([$instructor_id]);
        $course_count = $check_courses->fetch(PDO::FETCH_ASSOC)['count'];
        error_log("Number of courses for instructor: " . $course_count);

        // Get all courses for debugging
        $debug_stmt = $db->prepare("SELECT * FROM courses");
        $debug_stmt->execute();
        $all_courses = $debug_stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("All courses in database: " . print_r($all_courses, true));

        $stmt = $db->prepare("
            SELECT 
                c.*,
                (SELECT COUNT(*) FROM subjects WHERE course_id = c.id) as subject_count,
                (
                    SELECT JSON_ARRAYAGG(
                        JSON_OBJECT(
                            'id', s.id,
                            'subject_code', s.subject_code,
                            'subject_name', s.subject_name,
                            'units', s.units,
                            'year_level', s.year_level,
                            'semester', s.semester,
                            'prerequisites', s.prerequisites
                        )
                    )
                    FROM subjects s
                    WHERE s.course_id = c.id
                    ORDER BY s.year_level, s.semester, s.subject_code
                ) as subjects_json
            FROM courses c
            WHERE c.instructor_id = ?
            ORDER BY c.course_name ASC
        ");
        
        error_log("Executing main courses query for instructor_id: " . $instructor_id);
        $stmt->execute([$instructor_id]);
        $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Found " . count($courses) . " courses for instructor");
        error_log("Courses data: " . print_r($courses, true));
        
        // Process the subjects JSON for each course
        foreach ($courses as &$course) {
            if ($course['subjects_json']) {
                $course['subjects'] = json_decode($course['subjects_json'], true);
                // Group subjects by year level and semester
                $year_subjects = [];
                foreach ($course['subjects'] as $subject) {
                    $year = $subject['year_level'];
                    $sem = $subject['semester'];
                    if (!isset($year_subjects[$year])) {
                        $year_subjects[$year] = [];
                    }
                    if (!isset($year_subjects[$year][$sem])) {
                        $year_subjects[$year][$sem] = [];
                    }
                    $year_subjects[$year][$sem][] = $subject;
                }
                $course['year_subjects'] = $year_subjects;
            } else {
                $course['subjects'] = [];
                $course['year_subjects'] = [];
            }
            unset($course['subjects_json']);
        }
        
        return $courses;
    } catch (PDOException $e) {
        error_log("Error in getInstructorCourses: " . $e->getMessage());
        return [];
    }
}

// Function to get upcoming assignments
function getUpcomingAssignments($db, $instructor_id) {
    try {
        $stmt = $db->prepare("
            SELECT 
                a.*,
                c.course_name,
                c.course_code
            FROM assignments a
            JOIN courses c ON a.course_id = c.id
            WHERE c.instructor_id = ? AND a.due_date > NOW()
            ORDER BY a.due_date ASC
            LIMIT 5
        ");
        $stmt->execute([$instructor_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching upcoming assignments: " . $e->getMessage());
        return [];
    }
}

// Function to get enrolled students
function getEnrolledStudents($db, $instructor_id) {
    try {
        $stmt = $db->prepare("
            SELECT DISTINCT
                u.id as student_id,
                u.fullname,
                u.year_level,
                u.section
            FROM users u
            JOIN enrollments e ON u.id = e.student_id
            JOIN courses c ON e.course_id = c.id
            WHERE c.instructor_id = ? AND u.role = 'student'
            GROUP BY u.id
            LIMIT 5
        ");
        $stmt->execute([$instructor_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching enrolled students: " . $e->getMessage());
        return [];
    }
}

// Function to get dashboard statistics
function getDashboardStats($db, $instructor_id) {
    try {
        $stmt = $db->prepare("
            SELECT 
                COUNT(DISTINCT c.id) as total_courses,
                COUNT(DISTINCT e.student_id) as total_students,
                COUNT(DISTINCT a.id) as total_assignments,
                COUNT(DISTINCT CASE WHEN a.due_date > NOW() THEN a.id END) as upcoming_assignments,
                COUNT(DISTINCT CASE WHEN a.due_date < NOW() THEN a.id END) as past_assignments
            FROM courses c
            LEFT JOIN enrollments e ON c.id = e.course_id
            LEFT JOIN assignments a ON c.id = a.course_id
            WHERE c.instructor_id = ?
        ");
        $stmt->execute([$instructor_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching dashboard stats: " . $e->getMessage());
        return [
            'total_courses' => 0,
            'total_students' => 0,
            'total_assignments' => 0,
            'upcoming_assignments' => 0,
            'past_assignments' => 0
        ];
    }
}

// Function to get instructor details
function getInstructorDetails($db, $instructor_id) {
    try {
        $stmt = $db->prepare("
            SELECT ua.*, i.first_name, i.last_name, i.middle_name, i.suffix, i.department
            FROM user_accounts ua
            LEFT JOIN instructors i ON ua.id = i.user_id
            WHERE ua.id = ? AND ua.role = 'instructor'
        ");
        $stmt->execute([$instructor_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching instructor details: " . $e->getMessage());
        return null;
    }
}

// Function to check all courses
function getAllCourses($db) {
    try {
        $stmt = $db->prepare("SELECT id, course_name, instructor_id FROM courses");
        $stmt->execute();
        $all_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Total courses in database: " . count($all_courses));
        foreach ($all_courses as $course) {
            error_log("Course: " . $course['course_name'] . " (ID: " . $course['id'] . ", Instructor ID: " . $course['instructor_id'] . ")");
        }
        return $all_courses;
    } catch (PDOException $e) {
        error_log("Error fetching all courses: " . $e->getMessage());
        return [];
    }
}

// Function to check and insert sample courses
function checkAndInsertSampleCourses($db) {
    try {
        // First check if there are any courses
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM courses");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] == 0) {
            // Insert sample courses
            $sample_courses = [
                [
                    'course_code' => 'BSCS',
                    'course_name' => 'Bachelor of Science in Computer Science',
                    'description' => 'A four-year program focusing on computer science and programming',
                    'instructor_id' => $_SESSION['user_id']
                ],
                [
                    'course_code' => 'BSIT',
                    'course_name' => 'Bachelor of Science in Information Technology',
                    'description' => 'A four-year program focusing on information technology and systems',
                    'instructor_id' => $_SESSION['user_id']
                ]
            ];
            
            $insert_stmt = $db->prepare("
                INSERT INTO courses (course_code, course_name, description, instructor_id, created_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            
            foreach ($sample_courses as $course) {
                $insert_stmt->execute([
                    $course['course_code'],
                    $course['course_name'],
                    $course['description'],
                    $course['instructor_id']
                ]);
                
                // Get the course ID
                $course_id = $db->lastInsertId();
                
                // Insert sample subjects for each course
                $subjects = [
                    [
                        'subject_code' => 'CS101',
                        'subject_name' => 'Introduction to Programming',
                        'units' => 3,
                        'year_level' => 1,
                        'semester' => 1,
                        'prerequisites' => 'None'
                    ],
                    [
                        'subject_code' => 'CS102',
                        'subject_name' => 'Data Structures',
                        'units' => 3,
                        'year_level' => 1,
                        'semester' => 2,
                        'prerequisites' => 'CS101'
                    ],
                    [
                        'subject_code' => 'IT101',
                        'subject_name' => 'Web Development',
                        'units' => 3,
                        'year_level' => 1,
                        'semester' => 1,
                        'prerequisites' => 'None'
                    ],
                    [
                        'subject_code' => 'IT102',
                        'subject_name' => 'Database Management',
                        'units' => 3,
                        'year_level' => 1,
                        'semester' => 2,
                        'prerequisites' => 'IT101'
                    ]
                ];
                
                $subject_stmt = $db->prepare("
                    INSERT INTO subjects (course_id, subject_code, subject_name, units, year_level, semester, prerequisites)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                
                foreach ($subjects as $subject) {
                    $subject_stmt->execute([
                        $course_id,
                        $subject['subject_code'],
                        $subject['subject_name'],
                        $subject['units'],
                        $subject['year_level'],
                        $subject['semester'],
                        $subject['prerequisites']
                    ]);
                }
            }
            
            error_log("Sample courses and subjects inserted successfully");
            return true;
        }
        
        return false;
    } catch (PDOException $e) {
        error_log("Error checking/inserting sample courses: " . $e->getMessage());
        return false;
    }
}

// Function to create necessary tables if they don't exist
function createNecessaryTables($db) {
    try {
        error_log("Checking and creating necessary tables");
        
        // Create courses table if it doesn't exist
        $db->exec("
            CREATE TABLE IF NOT EXISTS courses (
                id INT AUTO_INCREMENT PRIMARY KEY,
                course_code VARCHAR(20) NOT NULL,
                course_name VARCHAR(255) NOT NULL,
                description TEXT,
                instructor_id INT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");
        error_log("Courses table checked/created");

        // Create subjects table if it doesn't exist
        $db->exec("
            CREATE TABLE IF NOT EXISTS subjects (
                id INT AUTO_INCREMENT PRIMARY KEY,
                course_id INT,
                subject_code VARCHAR(20) NOT NULL,
                subject_name VARCHAR(255) NOT NULL,
                units INT NOT NULL,
                year_level INT NOT NULL,
                semester INT NOT NULL,
                prerequisites TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
            )
        ");
        error_log("Subjects table checked/created");

        // Create enrollments table if it doesn't exist
        $db->exec("
            CREATE TABLE IF NOT EXISTS enrollments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                course_id INT,
                student_id INT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
                FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");
        error_log("Enrollments table checked/created");

        // Create assignments table if it doesn't exist
        $db->exec("
            CREATE TABLE IF NOT EXISTS assignments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                course_id INT,
                title VARCHAR(255) NOT NULL,
                description TEXT,
                due_date DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
            )
        ");
        error_log("Assignments table checked/created");

        return true;
    } catch (PDOException $e) {
        error_log("Error creating tables: " . $e->getMessage());
        return false;
    }
}

// Initialize variables
$courses = [];
$recent_activities = [];
$attendance_stats = [];
$recent_activity = [];
$error = '';
$success = $_SESSION['success'] ?? '';
unset($_SESSION['success']);

try {
    // First check if we can connect to the database
    $db = getDB();
    if (!$db) {
        throw new Exception("Database connection failed");
    }
    error_log("Database connection successful");

    // Create necessary tables
    createNecessaryTables($db);
    
    // Check if user is logged in
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        throw new Exception("User not logged in");
    }
    error_log("User is logged in. User ID: " . $_SESSION['user_id'] . ", Role: " . $_SESSION['role']);

    // Check and insert sample courses if needed
    checkAndInsertSampleCourses($db);
    
    // Debug information
    error_log("User ID: " . $_SESSION['user_id']);
    error_log("User Role: " . $_SESSION['role']);

    // Get all courses for debugging
    $all_courses = getAllCourses($db);
    
    // Get instructor details and department
    $instructor = getInstructorDetails($db, $_SESSION['user_id']);
    if (!$instructor) {
        throw new Exception("Instructor not found");
    }
    // Fallback: use department from user_accounts if not set in instructors
    $instructor_department = $instructor['department'];
    if (!$instructor_department || $instructor_department === '') {
        $instructor_department = $instructor['department'] ?? null; // user_accounts.department is also 'department'
    }
    if (!$instructor_department) {
        throw new Exception("Instructor department not set");
    }

    // Get all courses for this instructor (many-to-many)
    $stmt = $db->prepare("SELECT c.* FROM courses c JOIN course_instructors ci ON c.id = ci.course_id WHERE ci.instructor_id = ? ORDER BY c.course_name ASC");
    $stmt->execute([$_SESSION['user_id']]);
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // For each course, fetch subjects and group by year/semester
    foreach ($courses as &$course) {
        $stmt2 = $db->prepare("SELECT * FROM subjects WHERE course_id = ? ORDER BY year_level, semester, subject_code");
        $stmt2->execute([$course['id']]);
        $subjects = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        $course['subjects'] = $subjects;
        // Group by year and semester
        $year_subjects = [];
        foreach ($subjects as $subject) {
            $year = $subject['year_level'];
            $sem = $subject['semester'];
            if (!isset($year_subjects[$year])) {
                $year_subjects[$year] = [];
            }
            if (!isset($year_subjects[$year][$sem])) {
                $year_subjects[$year][$sem] = [];
            }
            $year_subjects[$year][$sem][] = $subject;
        }
        $course['year_subjects'] = $year_subjects;
        $course['subject_count'] = count($subjects);
    }

    // Get all required data
    $upcoming_deadlines = getUpcomingAssignments($db, $_SESSION['user_id']);
    $top_students = getEnrolledStudents($db, $_SESSION['user_id']);
    $stats = getDashboardStats($db, $_SESSION['user_id']);

    // Get recent submissions that need grading
    try {
    $stmt = $db->prepare("
            SELECT 
                a.title as assignment_title,
                a.due_date,
                c.course_name,
                c.course_code,
                u.fullname as student_name,
                u.year_level,
                u.section
        FROM assignments a
        JOIN courses c ON a.course_id = c.id
            JOIN users u ON c.instructor_id = u.id
        WHERE c.instructor_id = ?
            ORDER BY a.due_date DESC
        LIMIT 5
    ");
    $stmt->execute([$_SESSION['user_id']]);
        $pending_submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching pending submissions: " . $e->getMessage());
        $pending_submissions = [];
    }

    // Get recent activity with optimized query
    try {
        $stmt = $db->prepare("
            SELECT 
                u.fullname as user_name,
                c.course_name,
                c.course_code
            FROM courses c
            LEFT JOIN users u ON c.instructor_id = u.id
            WHERE c.instructor_id = ?
            ORDER BY c.created_at DESC
            LIMIT 5
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $recent_activity = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
        error_log("Error fetching activity log: " . $e->getMessage());
        $recent_activity = [];
    }

} catch (Exception $e) {
    error_log("Error in instructor dashboard: " . $e->getMessage());
    $error = "An error occurred: " . $e->getMessage();
    
    // Initialize empty arrays to prevent undefined variable errors
    $courses = [];
    $pending_submissions = [];
    $upcoming_deadlines = [];
    $top_students = [];
    $instructor = [];
    $stats = [
        'total_courses' => 0,
        'total_students' => 0,
        'total_assignments' => 0,
        'upcoming_assignments' => 0,
        'past_assignments' => 0
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Instructor Dashboard - School Grading System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .welcome-text h1 {
            margin: 0;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .welcome-text p {
            margin: 5px 0 0;
            color: #666;
        }
        .action-buttons {
            display: flex;
            gap: 10px;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }
        .btn:hover {
            background: #2980b9;
            transform: translateY(-1px);
        }
        .btn i {
            margin-right: 5px;
        }
        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-top: 20px;
        }
        .courses-section, .assignments-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }
        .section-header h2 {
            margin: 0;
            color: #2c3e50;
            font-size: 1.5em;
        }
        .course-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        .course-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            border: 1px solid #dee2e6;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .course-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .course-card h3 {
            margin: 0 0 10px 0;
            color: #2c3e50;
            font-size: 1.2em;
        }
        .course-stats {
            display: flex;
            gap: 15px;
            margin-top: 15px;
            font-size: 0.9em;
            color: #666;
        }
        .course-stats span {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .assignment-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .assignment-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
            transition: background-color 0.3s ease;
        }
        .assignment-item:hover {
            background-color: #f8f9fa;
        }
        .assignment-item:last-child {
            border-bottom: none;
        }
        .assignment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 5px;
        }
        .assignment-title {
            font-weight: 600;
            color: #2c3e50;
        }
        .assignment-course {
            font-size: 0.9em;
            color: #666;
            margin-bottom: 5px;
        }
        .due-date {
            font-size: 0.9em;
            color: #e74c3c;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-2px);
        }
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #3498db;
            margin: 10px 0;
        }
        .stat-label {
            color: #666;
            font-size: 0.9em;
        }
        .message {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
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
        .loading {
            display: none;
            text-align: center;
            padding: 20px;
        }
        .loading.active {
            display: block;
        }
        .progress-bar {
            width: 100%;
            height: 4px;
            background: #f0f0f0;
            border-radius: 2px;
            margin-top: 10px;
            overflow: hidden;
        }
        .progress-bar-fill {
            height: 100%;
            background: #3498db;
            width: 0%;
            transition: width 0.3s ease;
        }
        .activity-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .activity-item {
            padding: 10px;
            border-bottom: 1px solid #eee;
            font-size: 0.9em;
        }
        .activity-item:last-child {
            border-bottom: none;
        }
        .activity-time {
            color: #666;
            font-size: 0.8em;
        }
        .grade-stats {
            display: flex;
            gap: 10px;
            margin-top: 5px;
            font-size: 0.9em;
            color: #666;
        }
        .grade-stat {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .grade-stat i {
            color: #3498db;
        }
        .grade-stat.high {
            color: #27ae60;
        }
        .grade-stat.low {
            color: #e74c3c;
        }
        .grade-stat.average {
            color: #f39c12;
        }
        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            .action-buttons {
                flex-direction: column;
            }
            .btn {
                width: 100%;
                text-align: center;
            }
        }
        .filter-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .filter-btn {
            padding: 8px 15px;
            border: 1px solid #3498db;
            background: white;
            color: #3498db;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .filter-btn:hover, .filter-btn.active {
            background: #3498db;
            color: white;
        }
        .attendance-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .attendance-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            border: 1px solid #dee2e6;
        }
        .attendance-card h3 {
            margin: 0 0 15px 0;
            color: #2c3e50;
            font-size: 1.1em;
        }
        .attendance-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 15px;
        }
        .attendance-stats .stat {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.9em;
        }
        .attendance-progress {
            margin: 15px 0;
        }
        .attendance-percentage {
            display: block;
            text-align: center;
            margin-top: 5px;
            font-size: 0.9em;
            color: #666;
        }
        .courses-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .course-code {
            color: #7f8c8d;
            margin: 0 0 15px 0;
        }
        .course-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 20px;
        }
        .course-students {
            margin-top: 20px;
        }
        .course-students h4 {
            margin: 0 0 10px 0;
            color: #2c3e50;
        }
        .students-list {
            max-height: 200px;
            overflow-y: auto;
        }
        .student-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px;
            border-bottom: 1px solid #eee;
        }
        .student-name {
            flex: 2;
        }
        .student-year {
            flex: 1;
            color: #7f8c8d;
            font-size: 0.9em;
        }
        .student-grade {
            flex: 1;
            text-align: right;
            font-weight: bold;
            color: #2ecc71;
        }
        .no-students {
            text-align: center;
            color: #7f8c8d;
            padding: 20px;
        }
        .course-details {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-top: 10px;
        }
        .detail-item {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.9em;
            color: #666;
        }
        .detail-item i {
            color: #3498db;
        }
        .course-progress {
            margin-top: 15px;
        }
        .progress-item {
            margin-bottom: 10px;
        }
        .progress-item label {
            display: block;
            font-size: 0.9em;
            color: #666;
            margin-bottom: 5px;
        }
        .course-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        .course-actions .btn {
            flex: 1;
            text-align: center;
            padding: 8px;
            font-size: 0.9em;
        }
        .no-courses {
            text-align: center;
            padding: 40px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px dashed #dee2e6;
        }
        .no-courses p {
            margin-bottom: 20px;
            color: #666;
        }
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .quick-action-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 15px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-decoration: none;
            color: #2c3e50;
            transition: transform 0.3s ease;
        }
        .quick-action-btn:hover {
            transform: translateY(-2px);
        }
        .quick-action-btn i {
            font-size: 24px;
            margin-bottom: 10px;
            color: #3498db;
        }
        .submission-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .submission-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .submission-info {
            flex: 1;
        }
        .submission-meta {
            font-size: 0.9em;
            color: #666;
        }
        .deadline-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .deadline-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
        }
        .deadline-title {
            font-weight: 600;
            color: #2c3e50;
        }
        .deadline-course {
            font-size: 0.9em;
            color: #666;
        }
        .deadline-date {
            color: #e74c3c;
            font-size: 0.9em;
        }
        .student-performance {
            margin-top: 20px;
        }
        .performance-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .performance-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .student-info {
            flex: 1;
        }
        .student-name {
            font-weight: 600;
            color: #2c3e50;
        }
        .student-meta {
            font-size: 0.9em;
            color: #666;
        }
        .grade-info {
            text-align: right;
        }
        .grade-value {
            font-size: 1.2em;
            font-weight: 600;
            color: #27ae60;
        }
        .grade-meta {
            font-size: 0.9em;
            color: #666;
        }
        .profile-link {
            color: #3498db;
            text-decoration: none;
            margin-left: 10px;
            font-size: 0.8em;
            transition: color 0.3s ease;
        }
        .profile-link:hover {
            color: #2980b9;
        }
        .course-overview {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }

        .course-overview h4 {
            color: #2c3e50;
            margin: 0 0 15px 0;
            font-size: 18px;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
        }

        .overview-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }

        .overview-stat {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        .stat-label {
            color: #666;
            font-size: 14px;
            margin-bottom: 5px;
        }

        .stat-value {
            color: #2c3e50;
            font-size: 24px;
            font-weight: bold;
        }

        .course-curriculum {
            margin-top: 30px;
        }

        .course-curriculum h4 {
            color: #2c3e50;
            margin: 0 0 20px 0;
            font-size: 18px;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
        }

        .year-section {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .year-section h5 {
            color: #2c3e50;
            margin: 0 0 15px 0;
            font-size: 16px;
            padding-bottom: 8px;
            border-bottom: 1px solid #eee;
        }

        .semester-section {
            margin-bottom: 20px;
        }

        .semester-section h6 {
            color: #34495e;
            margin: 0 0 10px 0;
            font-size: 14px;
            padding: 5px 10px;
            background: #f8f9fa;
            border-radius: 4px;
        }

        .subjects-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .subject-item {
            display: flex;
            align-items: center;
            padding: 12px;
            border-bottom: 1px solid #eee;
            transition: background-color 0.2s ease;
        }

        .subject-item:hover {
            background-color: #f8f9fa;
        }

        .subject-item:last-child {
            border-bottom: none;
        }

        .subject-code {
            flex: 0 0 100px;
            color: #3498db;
            font-weight: 600;
        }

        .subject-name {
            flex: 1;
            color: #2c3e50;
            margin: 0 15px;
        }

        .subject-units {
            flex: 0 0 80px;
            text-align: center;
            color: #27ae60;
            font-weight: 600;
        }

        .subject-prerequisites {
            flex: 0 0 200px;
            font-size: 12px;
            color: #666;
            text-align: right;
        }

        .no-subjects {
            text-align: center;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            color: #666;
        }
    </style>
</head>
<?php
// Helper function for year suffix
function getYearSuffix($year) {
    if ($year == 1) return 'st';
    if ($year == 2) return 'nd';
    if ($year == 3) return 'rd';
    return 'th';
}
?>
<body>
    <?php require_once 'header.php'; ?>

    <div class="container">
        <div class="dashboard-header">
            <div class="welcome-text">
                <h1>Welcome, <?php echo htmlspecialchars($instructor['fullname'] ?? 'Instructor'); ?> 
                    <a href="profile.php" class="profile-link" title="Edit Profile"><i class="fas fa-user-edit"></i></a>
                    <a href="change_password.php" class="profile-link" title="Change Password"><i class="fas fa-key"></i></a>
                </h1>
            </div>
            <div class="action-buttons">
                <a href="reports.php" class="btn"><i class="fas fa-chart-bar"></i>View Reports</a>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="message success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="message error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="quick-actions">
            <a href="create_report.php" class="quick-action-btn">
                <i class="fas fa-chart-line"></i>
                Create Reports
            </a>
            <a href="manage_course.php" class="quick-action-btn">
                <i class="fas fa-cog"></i>
                Manage Courses
            </a>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <i class="fas fa-book fa-2x" style="color: #3498db;"></i>
                <div class="stat-number"><?php echo $stats['total_courses'] ?? 0; ?></div>
                <div class="stat-label">Active Courses</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-users fa-2x" style="color: #3498db;"></i>
                <div class="stat-number"><?php echo $stats['total_students'] ?? 0; ?></div>
                <div class="stat-label">Total Students</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-tasks fa-2x" style="color: #3498db;"></i>
                <div class="stat-number"><?php echo $stats['total_assignments'] ?? 0; ?></div>
                <div class="stat-label">Total Assignments</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-clock fa-2x" style="color: #3498db;"></i>
                <div class="stat-number"><?php echo $stats['upcoming_assignments'] ?? 0; ?></div>
                <div class="stat-label">Upcoming Assignments</div>
            </div>
        </div>

        <div class="dashboard-grid">
            <div class="courses-section">
                <div class="section-header">
                    <h2><i class="fas fa-book"></i> Your Courses</h2>
                </div>
                <div class="dashboard-section">
                    <?php if (empty($courses)): ?>
                        <div class="no-data">
                            <p>No courses available.</p>
                        </div>
                    <?php else: ?>
                    <?php
                    // Only display one course per unique course_code
                    $displayed_codes = [];
                    foreach ($courses as $course):
                        if (in_array($course['course_code'], $displayed_codes)) continue;
                        $displayed_codes[] = $course['course_code'];
                    ?>
                        <div class="course-card">
                            <div class="course-header">
                                <h3><?php echo htmlspecialchars($course['course_name']); ?></h3>
                                <span class="course-code"><?php echo htmlspecialchars($course['course_code']); ?></span>
                            </div>
                            
                            <div class="course-overview">
                                <h4>Course Overview</h4>
                                <div class="overview-stats">
                                    <div class="overview-stat">
                                        <span class="stat-label">Total Subjects</span>
                                        <span class="stat-value"><?php echo $course['subject_count']; ?></span>
                                    </div>
                                </div>
                            </div>

                            <!-- SUBJECTS OVERVIEW LIST -->
                            <?php if (!empty($course['subjects'])): ?>
                                <div class="subjects-overview">
                                    <strong>Subjects:</strong>
                                    <ul style="margin: 0 0 10px 15px; padding: 0;">
                                        <?php foreach ($course['subjects'] as $subject): ?>
                                            <li><?php echo htmlspecialchars($subject['subject_code'] . ' - ' . $subject['subject_name']); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                            <!-- END SUBJECTS OVERVIEW LIST -->

                            <div class="course-curriculum">
                                <h4>Course Curriculum</h4>
                                <?php if (!empty($course['year_subjects'])): ?>
                                    <?php foreach ($course['year_subjects'] as $year => $semesters): ?>
                                        <div class="year-section">
                                            <h5><?php echo $year; ?> Year</h5>
                                            <?php foreach ($semesters as $semester => $subjects): ?>
                                                <div class="semester-section">
                                                    <h6>Semester <?php echo $semester; ?></h6>
                                                    <ul class="subjects-list">
                                                        <?php foreach ($subjects as $subject): ?>
                                                            <li class="subject-item">
                                                                <span class="subject-code"><?php echo htmlspecialchars($subject['subject_code']); ?></span>
                                                                <span class="subject-name"><?php echo htmlspecialchars($subject['subject_name']); ?></span>
                                                                <span class="subject-units"><?php echo $subject['units']; ?> Units</span>
                                                                <?php if (!empty($subject['prerequisites'])): ?>
                                                                    <span class="subject-prerequisites">
                                                                        Prerequisites: <?php echo htmlspecialchars($subject['prerequisites']); ?>
                                                                    </span>
                                                                <?php endif; ?>
                                                            </li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                </div>
                                            <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                                    <div class="no-subjects">
                                        <p>No subjects assigned to this course yet.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                </div>
            </div>

            <div class="assignments-section">
                <div class="section-header">
                    <h2><i class="fas fa-clock"></i> Upcoming Deadlines</h2>
                </div>
                <ul class="deadline-list">
                    <?php if (empty($upcoming_deadlines)): ?>
                        <li class="deadline-item">No upcoming deadlines</li>
                    <?php else: ?>
                        <?php foreach ($upcoming_deadlines as $deadline): ?>
                            <li class="deadline-item">
                                <div class="deadline-title">
                                    <?php echo htmlspecialchars($deadline['title']); ?>
                                </div>
                                <div class="deadline-course">
                                    <?php echo htmlspecialchars($deadline['course_code']); ?>
                                </div>
                                <div class="deadline-date">
                                    Due: <?php echo date('M d, Y', strtotime($deadline['due_date'])); ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>

                <div class="section-header" style="margin-top: 20px;">
                    <h2><i class="fas fa-users"></i> Enrolled Students</h2>
                </div>
                <div class="student-performance">
                    <ul class="performance-list">
                        <?php if (empty($top_students)): ?>
                            <li class="performance-item">No students enrolled</li>
                    <?php else: ?>
                            <?php foreach ($top_students as $student): ?>
                                <li class="performance-item">
                                    <div class="student-info">
                                        <div class="student-name">
                                            <?php echo htmlspecialchars($student['fullname']); ?>
                                        </div>
                                        <div class="student-meta">
                                            Year <?php echo $student['year_level']; ?> - 
                                            <?php echo htmlspecialchars($student['section']); ?>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
    </div>

    <script>
        // Function to handle course filtering
        function filterCourses(filter) {
            const courseCards = document.querySelectorAll('.course-card');
            courseCards.forEach(card => {
                if (filter === 'all') {
                    card.style.display = 'flex';
                } else if (filter === 'active') {
                    const upcomingCount = parseInt(card.querySelector('.stat:nth-child(3) span').textContent);
                    card.style.display = upcomingCount > 0 ? 'flex' : 'none';
                } else if (filter === 'completed') {
                    const completedCount = parseInt(card.querySelector('.detail-item:nth-child(3) span').textContent);
                    card.style.display = completedCount > 0 ? 'flex' : 'none';
                }
            });
        }

        // Function to handle course search
        function searchCourses(query) {
            const courseCards = document.querySelectorAll('.course-card');
            const searchTerm = query.toLowerCase();
            
            courseCards.forEach(card => {
                const courseName = card.querySelector('h3').textContent.toLowerCase();
                const courseCode = card.querySelector('.course-code').textContent.toLowerCase();
                
                if (courseName.includes(searchTerm) || courseCode.includes(searchTerm)) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        // Function to handle assignment sorting
        function sortAssignments(sortBy) {
            const assignmentList = document.querySelector('.deadline-list');
            const assignments = Array.from(assignmentList.children);
            
            assignments.sort((a, b) => {
                if (sortBy === 'date') {
                    const dateA = new Date(a.querySelector('.deadline-date').textContent.split('Due: ')[1]);
                    const dateB = new Date(b.querySelector('.deadline-date').textContent.split('Due: ')[1]);
                    return dateA - dateB;
                } else if (sortBy === 'course') {
                    const courseA = a.querySelector('.deadline-course').textContent;
                    const courseB = b.querySelector('.deadline-course').textContent;
                    return courseA.localeCompare(courseB);
                }
            });
            
            assignments.forEach(assignment => assignmentList.appendChild(assignment));
        }

        // Function to handle student list sorting
        function sortStudents(sortBy) {
            const studentList = document.querySelector('.performance-list');
            const students = Array.from(studentList.children);
            
            students.sort((a, b) => {
                if (sortBy === 'name') {
                    const nameA = a.querySelector('.student-name').textContent;
                    const nameB = b.querySelector('.student-name').textContent;
                    return nameA.localeCompare(nameB);
                } else if (sortBy === 'year') {
                    const yearA = parseInt(a.querySelector('.student-meta').textContent.split('Year ')[1]);
                    const yearB = parseInt(b.querySelector('.student-meta').textContent.split('Year ')[1]);
                    return yearA - yearB;
                }
            });
            
            students.forEach(student => studentList.appendChild(student));
        }

        // Function to handle quick actions
        document.querySelectorAll('.quick-action-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                const action = this.getAttribute('data-action');
                if (action) {
                    e.preventDefault();
                    // Add loading state
                    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
                    // Redirect after a short delay
                    setTimeout(() => {
                        window.location.href = this.href;
                    }, 500);
                }
            });
        });

        // Function to handle course card interactions
        document.querySelectorAll('.course-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });

        // Function to handle responsive menu
        function toggleMenu() {
            const menu = document.querySelector('.action-buttons');
            menu.classList.toggle('active');
        }

        // Add event listeners for window resize
        window.addEventListener('resize', function() {
            const menu = document.querySelector('.action-buttons');
            if (window.innerWidth > 768) {
                menu.classList.remove('active');
            }
        });

        // Initialize tooltips
        document.querySelectorAll('[title]').forEach(element => {
            element.addEventListener('mouseenter', function(e) {
                const tooltip = document.createElement('div');
                tooltip.className = 'tooltip';
                tooltip.textContent = this.getAttribute('title');
                document.body.appendChild(tooltip);
                
                const rect = this.getBoundingClientRect();
                tooltip.style.top = rect.bottom + 5 + 'px';
                tooltip.style.left = rect.left + (rect.width - tooltip.offsetWidth) / 2 + 'px';
            });
            
            element.addEventListener('mouseleave', function() {
                const tooltip = document.querySelector('.tooltip');
                if (tooltip) {
                    tooltip.remove();
                }
            });
        });

        // Add loading states to all buttons
        document.querySelectorAll('.btn').forEach(button => {
            button.addEventListener('click', function(e) {
                if (!this.classList.contains('no-loading')) {
                    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
                    this.disabled = true;
                }
            });
        });
    </script>
</body>
</html>
