<?php
require_once 'config.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    $db = getDB();
    $db->beginTransaction();
    
    echo "<h2>Starting Database Migration</h2>";
    
    // 1. Backup existing data
    echo "<p>Backing up existing data...</p>";
    
    // Get all users except super admin (we'll handle super admin separately)
    $stmt = $db->query("SELECT * FROM users WHERE role != 'super_admin'");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all courses
    $stmt = $db->query("SELECT * FROM courses");
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all subjects
    $stmt = $db->query("SELECT * FROM subjects");
    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 2. Create new tables if they don't exist
    echo "<p>Creating new table structure...</p>";
    
    // Create user_accounts table
    $db->exec("
        CREATE TABLE IF NOT EXISTS user_accounts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            role ENUM('student', 'instructor', 'admin', 'super_admin') NOT NULL,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
    
    // Create students table
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
    
    // Create instructors table
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
    
    // Create admins table
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
    
    // Create super_admins table
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
    
    // Create courses table (updated structure)
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
    
    // Create enrollments table (updated structure)
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
    
    // Create grades table (updated structure)
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
    
    // 3. Handle super admin first
    echo "<p>Checking super admin account...</p>";
    
    // Check if super admin exists in old users table
    $stmt = $db->query("SELECT * FROM users WHERE role = 'super_admin'");
    $oldSuperAdmin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($oldSuperAdmin) {
        // Check if super admin exists in new user_accounts table
        $stmt = $db->prepare("SELECT * FROM user_accounts WHERE username = ?");
        $stmt->execute([$oldSuperAdmin['username']]);
        $newSuperAdmin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$newSuperAdmin) {
            // Migrate super admin to new structure
            $stmt = $db->prepare("
                INSERT INTO user_accounts (
                    username, password, email, role, is_active, created_at, updated_at
                ) VALUES (
                    :username, :password, :email, 'super_admin', :is_active, :created_at, :updated_at
                )
            ");
            
            $stmt->execute([
                ':username' => $oldSuperAdmin['username'],
                ':password' => $oldSuperAdmin['password'],
                ':email' => $oldSuperAdmin['email'],
                ':is_active' => $oldSuperAdmin['is_active'],
                ':created_at' => $oldSuperAdmin['created_at'],
                ':updated_at' => $oldSuperAdmin['updated_at']
            ]);
            
            $new_user_id = $db->lastInsertId();
            
            // Insert into super_admins
            $stmt = $db->prepare("
                INSERT INTO super_admins (
                    user_id, first_name, last_name, middle_name, suffix
                ) VALUES (
                    :user_id, :first_name, :last_name, :middle_name, :suffix
                )
            ");
            
            $stmt->execute([
                ':user_id' => $new_user_id,
                ':first_name' => $oldSuperAdmin['first_name'] ?? 'Super',
                ':last_name' => $oldSuperAdmin['last_name'] ?? 'Admin',
                ':middle_name' => $oldSuperAdmin['middle_name'] ?? null,
                ':suffix' => $oldSuperAdmin['suffix'] ?? null
            ]);
            
            echo "<p style='color: green;'>Super admin account migrated successfully!</p>";
        } else {
            echo "<p>Super admin account already exists in new structure.</p>";
        }
    } else {
        // Create new super admin if none exists
        echo "<p>Creating new super admin account...</p>";
        
        $username = 'superadmin';
        $password = 'Admin@123456';
        $email = 'superadmin@school.edu';
        
        // Insert into user_accounts
        $stmt = $db->prepare("
            INSERT INTO user_accounts (
                username, password, email, role, is_active
            ) VALUES (
                :username, :password, :email, 'super_admin', TRUE
            )
        ");
        
        $stmt->execute([
            ':username' => $username,
            ':password' => password_hash($password, PASSWORD_DEFAULT),
            ':email' => $email
        ]);
        
        $user_id = $db->lastInsertId();
        
        // Insert into super_admins
        $stmt = $db->prepare("
            INSERT INTO super_admins (
                user_id, first_name, last_name
            ) VALUES (
                :user_id, 'Super', 'Admin'
            )
        ");
        
        $stmt->execute([
            ':user_id' => $user_id
        ]);
        
        echo "<p style='color: green;'>New super admin account created successfully!</p>";
        echo "<p>Username: " . htmlspecialchars($username) . "</p>";
        echo "<p>Password: " . htmlspecialchars($password) . "</p>";
    }
    
    // 4. Migrate other users
    echo "<p>Migrating other users...</p>";
    
    foreach ($users as $user) {
        // Check if user already exists in new structure
        $stmt = $db->prepare("SELECT * FROM user_accounts WHERE username = ?");
        $stmt->execute([$user['username']]);
        if ($stmt->fetch()) {
            echo "<p>Skipping existing user: " . htmlspecialchars($user['username']) . "</p>";
            continue;
        }
        
        // Insert into user_accounts
        $stmt = $db->prepare("
            INSERT INTO user_accounts (
                username, password, email, role, is_active, created_at, updated_at
            ) VALUES (
                :username, :password, :email, :role, :is_active, :created_at, :updated_at
            )
        ");
        
        $stmt->execute([
            ':username' => $user['username'],
            ':password' => $user['password'],
            ':email' => $user['email'],
            ':role' => $user['role'],
            ':is_active' => $user['is_active'],
            ':created_at' => $user['created_at'],
            ':updated_at' => $user['updated_at']
        ]);
        
        $new_user_id = $db->lastInsertId();
        
        // Insert into appropriate role table
        switch ($user['role']) {
            case 'student':
                $stmt = $db->prepare("
                    INSERT INTO students (
                        user_id, student_id, first_name, last_name, middle_name, suffix,
                        gender, date_of_birth, address, contact_number, year_level, course
                    ) VALUES (
                        :user_id, :student_id, :first_name, :last_name, :middle_name, :suffix,
                        'Other', CURRENT_DATE, 'To be updated', 'To be updated', '1st Year', 'To be updated'
                    )
                ");
                $stmt->execute([
                    ':user_id' => $new_user_id,
                    ':student_id' => $user['student_id'] ?? 'STU' . $new_user_id,
                    ':first_name' => $user['first_name'] ?? 'First',
                    ':last_name' => $user['last_name'] ?? 'Last',
                    ':middle_name' => $user['middle_name'] ?? null,
                    ':suffix' => $user['suffix'] ?? null
                ]);
                break;
                
            case 'instructor':
                $stmt = $db->prepare("
                    INSERT INTO instructors (
                        user_id, instructor_id, first_name, last_name, middle_name, suffix,
                        gender, date_of_birth, address, contact_number, department, specialization
                    ) VALUES (
                        :user_id, :instructor_id, :first_name, :last_name, :middle_name, :suffix,
                        'Other', CURRENT_DATE, 'To be updated', 'To be updated', 'To be updated', 'To be updated'
                    )
                ");
                $stmt->execute([
                    ':user_id' => $new_user_id,
                    ':instructor_id' => 'INS' . $new_user_id,
                    ':first_name' => $user['first_name'] ?? 'First',
                    ':last_name' => $user['last_name'] ?? 'Last',
                    ':middle_name' => $user['middle_name'] ?? null,
                    ':suffix' => $user['suffix'] ?? null
                ]);
                break;
                
            case 'admin':
                $stmt = $db->prepare("
                    INSERT INTO admins (
                        user_id, admin_id, first_name, last_name, middle_name, suffix,
                        gender, date_of_birth, address, contact_number, department, position
                    ) VALUES (
                        :user_id, :admin_id, :first_name, :last_name, :middle_name, :suffix,
                        'Other', CURRENT_DATE, 'To be updated', 'To be updated', 'To be updated', 'Administrator'
                    )
                ");
                $stmt->execute([
                    ':user_id' => $new_user_id,
                    ':admin_id' => 'ADM' . $new_user_id,
                    ':first_name' => $user['first_name'] ?? 'First',
                    ':last_name' => $user['last_name'] ?? 'Last',
                    ':middle_name' => $user['middle_name'] ?? null,
                    ':suffix' => $user['suffix'] ?? null
                ]);
                break;
        }
    }
    
    // 5. Migrate courses
    echo "<p>Migrating courses...</p>";
    
    foreach ($courses as $course) {
        // Check if course already exists
        $stmt = $db->prepare("SELECT * FROM courses WHERE course_code = ?");
        $stmt->execute([$course['course_code']]);
        if ($stmt->fetch()) {
            echo "<p>Skipping existing course: " . htmlspecialchars($course['course_code']) . "</p>";
            continue;
        }
        
        $stmt = $db->prepare("
            INSERT INTO courses (
                course_code, course_name, description, units, department
            ) VALUES (
                :code, :name, :description, 3, :department
            )
        ");
        
        $stmt->execute([
            ':code' => $course['course_code'],
            ':name' => $course['course_name'],
            ':description' => $course['description'] ?? null,
            ':department' => 'To be updated'
        ]);
    }
    
    $db->commit();
    echo "<p style='color: green;'>Database migration completed successfully!</p>";
    echo "<p>Please verify your data and update any missing information.</p>";
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?> 