<?php
require_once 'config.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

function setupDatabase() {
    try {
        // First connect without database selected
        $dsn = "mysql:host=" . DB_HOST . ";charset=" . DB_CHARSET;
        $db = new PDO($dsn, DB_USER, DB_PASS);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        echo "Connected to MySQL server successfully!\n\n";
        
        // Create database if it doesn't exist
        $db->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        echo "Database '" . DB_NAME . "' created or already exists.\n\n";
        
        // Select the database
        $db->exec("USE " . DB_NAME);
        
        // Drop existing tables in the correct order
        $db->exec("SET FOREIGN_KEY_CHECKS = 0");
        $db->exec("DROP TABLE IF EXISTS grades");
        $db->exec("DROP TABLE IF EXISTS subjects");
        $db->exec("DROP TABLE IF EXISTS courses");
        $db->exec("SET FOREIGN_KEY_CHECKS = 1");
        
        // Create users table with role as ENUM including super_admin
        $db->exec("CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            role ENUM('super_admin', 'admin', 'instructor', 'student') NOT NULL,
            fullname VARCHAR(100) NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            year_level INT NULL,
            section VARCHAR(10) NULL,
            first_name VARCHAR(50) NULL,
            last_name VARCHAR(50) NULL,
            middle_name VARCHAR(50) NULL,
            suffix VARCHAR(10) NULL,
            is_active BOOLEAN DEFAULT TRUE,
            login_attempts INT DEFAULT 0,
            last_login DATETIME,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_role (role),
            INDEX idx_username (username),
            INDEX idx_email (email)
        ) ENGINE=InnoDB");
        echo "Users table created or already exists.\n\n";
        
        // Create courses table
        $db->exec("CREATE TABLE IF NOT EXISTS courses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            course_code VARCHAR(20) UNIQUE NOT NULL,
            course_name VARCHAR(100) NOT NULL,
            description TEXT,
            instructor_id INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (instructor_id) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_course_code (course_code),
            INDEX idx_instructor (instructor_id)
        ) ENGINE=InnoDB");
        echo "Courses table created.\n\n";
        
        // Create subjects table
        $db->exec("CREATE TABLE IF NOT EXISTS subjects (
            id INT AUTO_INCREMENT PRIMARY KEY,
            course_id INT NOT NULL,
            subject_code VARCHAR(20) NOT NULL,
            subject_name VARCHAR(100) NOT NULL,
            description TEXT,
            units INT NOT NULL DEFAULT 3,
            year_level INT NOT NULL,
            semester INT NOT NULL,
            prerequisites TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
            UNIQUE KEY unique_subject (course_id, subject_code),
            INDEX idx_course_year_sem (course_id, year_level, semester)
        ) ENGINE=InnoDB");
        echo "Subjects table created.\n\n";
        
        // Create activity_log table
        $db->exec("CREATE TABLE IF NOT EXISTS activity_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            action VARCHAR(100) NOT NULL,
            details TEXT,
            ip_address VARCHAR(45),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_user (user_id),
            INDEX idx_action (action)
        ) ENGINE=InnoDB");
        echo "Activity log table created or already exists.\n\n";

        // Insert sample instructor first
        $stmt = $db->prepare("INSERT INTO users (username, password, role, fullname, email, department) 
                             VALUES (?, ?, 'instructor', ?, ?, ?)");
        $stmt->execute([
            'instructor1',
            password_hash('password123', PASSWORD_DEFAULT),
            'John Smith',
            'john.smith@school.edu',
            'CS'
        ]);
        $instructor_id = $db->lastInsertId();
        echo "Sample instructor created with ID: {$instructor_id}\n\n";

        // Insert default courses with instructor
        $default_courses = [
            'BSCS' => 'Bachelor of Science in Computer Science',
            'BSIT' => 'Bachelor of Science in Information Technology'
        ];
        
        $course_ids = [];
        foreach ($default_courses as $code => $name) {
            try {
                $stmt = $db->prepare("INSERT INTO courses (course_code, course_name, instructor_id) VALUES (?, ?, ?)");
                $stmt->execute([$code, $name, $instructor_id]);
                
                $stmt = $db->prepare("SELECT id FROM courses WHERE course_code = ?");
                $stmt->execute([$code]);
                $course_ids[$code] = $stmt->fetchColumn();
                
                echo "Course {$code} inserted/found with ID: {$course_ids[$code]}\n";
            } catch (PDOException $e) {
                echo "Error inserting course {$code}: " . $e->getMessage() . "\n";
                continue;
            }
        }

        // Insert sample students
        $students = [
            ['student1', 'Alice Johnson', 'alice@school.edu', 'CS', 'A', 1],
            ['student2', 'Bob Wilson', 'bob@school.edu', 'CS', 'A', 1],
            ['student3', 'Carol Davis', 'carol@school.edu', 'CS', 'B', 1],
            ['student4', 'David Brown', 'david@school.edu', 'CS', 'B', 1]
        ];

        foreach ($students as $student) {
            $stmt = $db->prepare("INSERT INTO users (username, password, role, fullname, email, course, section, year_level) 
                                 VALUES (?, ?, 'student', ?, ?, ?, ?, ?)");
            $stmt->execute([
                $student[0],
                password_hash('password123', PASSWORD_DEFAULT),
                $student[1],
                $student[2],
                $student[3],
                $student[4],
                $student[5]
            ]);
        }

        // Enroll students in the courses
        $stmt = $db->prepare("INSERT INTO enrollments (student_id, course_id) VALUES (?, ?)");
        foreach ($students as $index => $student) {
            foreach ($course_ids as $course_id) {
                $stmt->execute([$index + 2, $course_id]); // +2 because we have admin and instructor users
            }
        }

        // Insert sample assignments for each course
        $assignments = [
            ['Programming Basics Quiz', 'quiz', '2024-03-20 23:59:59'],
            ['Midterm Exam', 'exam', '2024-03-25 23:59:59'],
            ['Final Project', 'project', '2024-04-15 23:59:59'],
            ['Weekly Assignment 1', 'assignment', '2024-03-18 23:59:59']
        ];

        $stmt = $db->prepare("INSERT INTO assignments (course_id, title, type, due_date) VALUES (?, ?, ?, ?)");
        foreach ($course_ids as $course_id) {
            foreach ($assignments as $assignment) {
                $stmt->execute([$course_id, $assignment[0], $assignment[1], $assignment[2]]);
            }
        }

        // Insert sample attendance records
        $stmt = $db->prepare("INSERT INTO attendance (course_id, student_id, date, status) VALUES (?, ?, ?, ?)");
        $today = date('Y-m-d');
        foreach ($course_ids as $course_id) {
            foreach ($students as $index => $student) {
                $stmt->execute([$course_id, $index + 2, $today, 'present']);
            }
        }

        echo "Sample data inserted successfully.\n\n";

        return "Database setup completed successfully!";
    } catch (PDOException $e) {
        return "Database setup error: " . $e->getMessage();
    }
}

// Only run this script directly
if (php_sapi_name() === 'cli' || isset($_GET['setup'])) {
    echo setupDatabase();
}
?> 