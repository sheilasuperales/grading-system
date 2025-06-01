<?php
require_once 'config.php';

try {
    $db = getDB();
    
    // Start transaction
    $db->beginTransaction();
    
    echo "<h2>Checking and Fixing Users Table Structure</h2>";
    
    // First, check if table exists
    $stmt = $db->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() == 0) {
        // Create table if it doesn't exist
        $db->exec("CREATE TABLE users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            role ENUM('admin', 'instructor', 'student') NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            first_name VARCHAR(50) NOT NULL,
            last_name VARCHAR(50) NOT NULL,
            middle_name VARCHAR(50),
            suffix VARCHAR(10),
            year_level VARCHAR(20),
            section VARCHAR(20),
            student_id VARCHAR(20) UNIQUE,
            department VARCHAR(50),
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
        echo "<p style='color: green;'>Created new users table with correct structure</p>";
    } else {
        // Check current structure
        $stmt = $db->query("DESCRIBE users");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $columnNames = array_column($columns, 'Field');
        
        // Check for old column names
        $needsUpdate = false;
        $alterStatements = [];
        
        if (in_array('first_name', $columnNames) && !in_array('firstname', $columnNames)) {
            $alterStatements[] = "CHANGE COLUMN first_name firstname VARCHAR(50) NOT NULL";
            $needsUpdate = true;
        }
        if (in_array('last_name', $columnNames) && !in_array('lastname', $columnNames)) {
            $alterStatements[] = "CHANGE COLUMN last_name lastname VARCHAR(50) NOT NULL";
            $needsUpdate = true;
        }
        if (in_array('middle_name', $columnNames) && !in_array('middlename', $columnNames)) {
            $alterStatements[] = "CHANGE COLUMN middle_name middlename VARCHAR(50)";
            $needsUpdate = true;
        }
        
        // Add missing columns if they don't exist
        if (!in_array('first_name', $columnNames)) {
            $alterStatements[] = "ADD COLUMN first_name VARCHAR(50) NOT NULL AFTER email";
            $needsUpdate = true;
        }
        if (!in_array('last_name', $columnNames)) {
            $alterStatements[] = "ADD COLUMN last_name VARCHAR(50) NOT NULL AFTER firstname";
            $needsUpdate = true;
        }
        if (!in_array('middle_name', $columnNames)) {
            $alterStatements[] = "ADD COLUMN middle_name VARCHAR(50) AFTER lastname";
            $needsUpdate = true;
        }
        if (!in_array('suffix', $columnNames)) {
            $alterStatements[] = "ADD COLUMN suffix VARCHAR(10) AFTER middlename";
            $needsUpdate = true;
        }
        if (!in_array('year_level', $columnNames)) {
            $alterStatements[] = "ADD COLUMN year_level VARCHAR(20) AFTER suffix";
            $needsUpdate = true;
        }
        if (!in_array('section', $columnNames)) {
            $alterStatements[] = "ADD COLUMN section VARCHAR(20) AFTER year_level";
            $needsUpdate = true;
        }
        if (!in_array('student_id', $columnNames)) {
            $alterStatements[] = "ADD COLUMN student_id VARCHAR(20) UNIQUE AFTER section";
            $needsUpdate = true;
        }
        if (!in_array('department', $columnNames)) {
            $alterStatements[] = "ADD COLUMN department VARCHAR(50) AFTER student_id";
            $needsUpdate = true;
        }
        if (!in_array('is_active', $columnNames)) {
            $alterStatements[] = "ADD COLUMN is_active BOOLEAN DEFAULT TRUE AFTER department";
            $needsUpdate = true;
        }
        if (!in_array('created_at', $columnNames)) {
            $alterStatements[] = "ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER is_active";
            $needsUpdate = true;
        }
        if (!in_array('updated_at', $columnNames)) {
            $alterStatements[] = "ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at";
            $needsUpdate = true;
        }
        
        if ($needsUpdate) {
            // Execute all alter statements
            $alterSQL = "ALTER TABLE users " . implode(", ", $alterStatements);
            $db->exec($alterSQL);
            echo "<p style='color: green;'>Updated table structure successfully</p>";
        } else {
            echo "<p style='color: blue;'>Table structure is already correct</p>";
        }
    }
    
    // Show final table structure
    $stmt = $db->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Current Table Structure:</h3>";
    echo "<pre>";
    foreach ($columns as $column) {
        echo "Column: " . $column['Field'] . "\n";
        echo "Type: " . $column['Type'] . "\n";
        echo "Null: " . $column['Null'] . "\n";
        echo "Key: " . $column['Key'] . "\n";
        echo "Default: " . $column['Default'] . "\n";
        echo "Extra: " . $column['Extra'] . "\n";
        echo "-------------------\n";
    }
    echo "</pre>";
    
    // Commit transaction
    $db->commit();
    echo "<p style='color: green;'>Table structure check and fix completed successfully!</p>";
    
} catch (PDOException $e) {
    // Rollback transaction on error
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?> 