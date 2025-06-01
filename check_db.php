<?php
require_once 'config.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

try {
    $db = getDB();
    
    echo "<h2>Database Structure Check</h2>";
    
    // Check user_accounts table
    echo "<h3>Checking user_accounts table:</h3>";
    $stmt = $db->query("DESCRIBE user_accounts");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>";
    print_r($columns);
    echo "</pre>";
    
    // Check super_admins table
    echo "<h3>Checking super_admins table:</h3>";
    $stmt = $db->query("DESCRIBE super_admins");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>";
    print_r($columns);
    echo "</pre>";
    
    // Check if superadmin exists
    echo "<h3>Checking superadmin account:</h3>";
    $stmt = $db->query("SELECT * FROM user_accounts WHERE role = 'super_admin'");
    $superadmin = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($superadmin) {
        echo "<pre>";
        $debug = $superadmin;
        $debug['password'] = '***';
        print_r($debug);
        echo "</pre>";
        
        // Check super_admins details
        $stmt = $db->prepare("SELECT * FROM super_admins WHERE user_id = ?");
        $stmt->execute([$superadmin['id']]);
        $details = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<h4>Super Admin Details:</h4>";
        echo "<pre>";
        print_r($details);
        echo "</pre>";
    } else {
        echo "<p style='color: red;'>No superadmin account found!</p>";
    }
    
    // Check user data
    $stmt = $db->query("SELECT * FROM users WHERE username = 'superadmin'");
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<h2>Super Admin User Data:</h2>";
    echo "<pre>";
    print_r($user);
    echo "</pre>";
    
    echo "Courses:\n";
    $stmt = $db->query("SELECT * FROM courses");
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    print_r($courses);
    
    echo "\n\nSubjects:\n";
    $stmt = $db->query("SELECT * FROM subjects");
    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    print_r($subjects);
    
    // Check courses table structure
    echo "Courses Table Structure:\n";
    $stmt = $db->query("DESCRIBE courses");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
    
    echo "\nSubjects Table Structure:\n";
    $stmt = $db->query("DESCRIBE subjects");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
    
    // Check course_id foreign key in subjects table
    echo "\nChecking course_id foreign key:\n";
    $stmt = $db->query("
        SELECT 
            TABLE_NAME,
            COLUMN_NAME,
            CONSTRAINT_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
        FROM
            INFORMATION_SCHEMA.KEY_COLUMN_USAGE
        WHERE
            REFERENCED_TABLE_NAME = 'courses'
    ");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
    
    // Check if any subjects have invalid course_ids
    echo "\nChecking for orphaned subjects:\n";
    $stmt = $db->query("
        SELECT s.* 
        FROM subjects s 
        LEFT JOIN courses c ON s.course_id = c.id 
        WHERE c.id IS NULL
    ");
    $orphaned = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($orphaned) {
        echo "Found orphaned subjects:\n";
        print_r($orphaned);
    } else {
        echo "No orphaned subjects found.\n";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?> 