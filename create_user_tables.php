<?php
require_once 'config.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    $db = getDB();
    $db->beginTransaction();
    
    echo "<h2>Creating User Tables</h2>";
    
    // 1. Create students table
    $db->exec("
        CREATE TABLE IF NOT EXISTS students (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNIQUE NOT NULL,
            student_id VARCHAR(20) UNIQUE NOT NULL,
            first_name VARCHAR(50) NOT NULL,
            last_name VARCHAR(50) NOT NULL,
            middle_name VARCHAR(50),
            suffix VARCHAR(10),
            gender ENUM('Male', 'Female', 'Other') NOT NULL,
            date_of_birth DATE NOT NULL,
            address TEXT NOT NULL,
            contact_number VARCHAR(20) NOT NULL,
            year_level ENUM('1st Year', '2nd Year', '3rd Year', '4th Year') NOT NULL,
            course VARCHAR(100) NOT NULL,
            FOREIGN KEY (user_id) REFERENCES user_accounts(id) ON DELETE CASCADE
        )
    ");
    echo "<p style='color: green;'>✓ Students table created/verified</p>";
    
    // 2. Create instructors table
    $db->exec("
        CREATE TABLE IF NOT EXISTS instructors (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNIQUE NOT NULL,
            instructor_id VARCHAR(20) UNIQUE NOT NULL,
            first_name VARCHAR(50) NOT NULL,
            last_name VARCHAR(50) NOT NULL,
            middle_name VARCHAR(50),
            suffix VARCHAR(10),
            gender ENUM('Male', 'Female', 'Other') NOT NULL,
            date_of_birth DATE NOT NULL,
            address TEXT NOT NULL,
            contact_number VARCHAR(20) NOT NULL,
            department VARCHAR(100) NOT NULL,
            specialization VARCHAR(100) NOT NULL,
            FOREIGN KEY (user_id) REFERENCES user_accounts(id) ON DELETE CASCADE
        )
    ");
    echo "<p style='color: green;'>✓ Instructors table created/verified</p>";
    
    // 3. Create admins table
    $db->exec("
        CREATE TABLE IF NOT EXISTS admins (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNIQUE NOT NULL,
            admin_id VARCHAR(20) UNIQUE NOT NULL,
            first_name VARCHAR(50) NOT NULL,
            last_name VARCHAR(50) NOT NULL,
            middle_name VARCHAR(50),
            suffix VARCHAR(10),
            gender ENUM('Male', 'Female', 'Other') NOT NULL,
            date_of_birth DATE NOT NULL,
            address TEXT NOT NULL,
            contact_number VARCHAR(20) NOT NULL,
            department VARCHAR(100) NOT NULL,
            position VARCHAR(100) NOT NULL,
            FOREIGN KEY (user_id) REFERENCES user_accounts(id) ON DELETE CASCADE
        )
    ");
    echo "<p style='color: green;'>✓ Admins table created/verified</p>";
    
    // 4. Create super_admins table (if not already created)
    $db->exec("
        CREATE TABLE IF NOT EXISTS super_admins (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNIQUE NOT NULL,
            first_name VARCHAR(50) NOT NULL,
            last_name VARCHAR(50) NOT NULL,
            middle_name VARCHAR(50),
            suffix VARCHAR(10),
            FOREIGN KEY (user_id) REFERENCES user_accounts(id) ON DELETE CASCADE
        )
    ");
    echo "<p style='color: green;'>✓ Super admins table created/verified</p>";
    
    // 5. Create courses table
    $db->exec("
        CREATE TABLE IF NOT EXISTS courses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            course_code VARCHAR(20) UNIQUE NOT NULL,
            course_name VARCHAR(100) NOT NULL,
            description TEXT,
            units INT NOT NULL,
            department VARCHAR(100) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
    echo "<p style='color: green;'>✓ Courses table created/verified</p>";
    
    // 6. Create enrollments table
    $db->exec("
        CREATE TABLE IF NOT EXISTS enrollments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            course_id INT NOT NULL,
            instructor_id INT NOT NULL,
            academic_year VARCHAR(20) NOT NULL,
            semester ENUM('1st', '2nd', 'Summer') NOT NULL,
            status ENUM('Enrolled', 'Dropped', 'Completed') NOT NULL DEFAULT 'Enrolled',
            enrollment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
            FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
            FOREIGN KEY (instructor_id) REFERENCES instructors(id) ON DELETE CASCADE
        )
    ");
    echo "<p style='color: green;'>✓ Enrollments table created/verified</p>";
    
    // 7. Create grades table
    $db->exec("
        CREATE TABLE IF NOT EXISTS grades (
            id INT AUTO_INCREMENT PRIMARY KEY,
            enrollment_id INT NOT NULL,
            midterm_grade DECIMAL(5,2),
            final_grade DECIMAL(5,2),
            remarks VARCHAR(50),
            graded_by INT NOT NULL,
            graded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (enrollment_id) REFERENCES enrollments(id) ON DELETE CASCADE,
            FOREIGN KEY (graded_by) REFERENCES instructors(id) ON DELETE CASCADE
        )
    ");
    echo "<p style='color: green;'>✓ Grades table created/verified</p>";
    
    $db->commit();
    echo "<p style='color: green;'>All tables created successfully!</p>";
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?> 