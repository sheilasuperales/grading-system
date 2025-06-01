<?php
require_once 'config.php';

try {
    $db = getDB();
    
    // Start transaction
    $db->beginTransaction();
    
    echo "<h2>Updating Null Columns in Users Table</h2>";
    
    // First, get count of users with null values
    $stmt = $db->query("
        SELECT COUNT(*) as count 
        FROM users 
        WHERE firstname IS NULL 
           OR lastname IS NULL 
           OR email IS NULL 
           OR role IS NULL 
           OR is_active IS NULL
    ");
    $nullCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    echo "<p>Found {$nullCount} users with null values</p>";
    
    // Update null values with appropriate defaults
    $stmt = $db->prepare("
        UPDATE users 
        SET 
            firstname = COALESCE(firstname, 'Unknown'),
            lastname = COALESCE(lastname, 'User'),
            middlename = COALESCE(middlename, ''),
            suffix = COALESCE(suffix, ''),
            email = COALESCE(email, CONCAT(username, '@example.com')),
            role = COALESCE(role, 'student'),
            year_level = COALESCE(year_level, '1'),
            section = COALESCE(section, 'A'),
            student_id = COALESCE(student_id, CONCAT('2024-', LPAD(id, 4, '0'))),
            department = COALESCE(department, 'General'),
            is_active = COALESCE(is_active, TRUE)
        WHERE firstname IS NULL 
           OR lastname IS NULL 
           OR email IS NULL 
           OR role IS NULL 
           OR is_active IS NULL
    ");
    
    $stmt->execute();
    $updatedCount = $stmt->rowCount();
    
    // Verify the updates
    $stmt = $db->query("
        SELECT COUNT(*) as count 
        FROM users 
        WHERE firstname IS NULL 
           OR lastname IS NULL 
           OR email IS NULL 
           OR role IS NULL 
           OR is_active IS NULL
    ");
    $remainingNulls = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Show current table structure
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
    
    // Show some sample data
    $stmt = $db->query("SELECT id, username, firstname, lastname, email, role, student_id FROM users LIMIT 5");
    $sampleUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Sample Updated Users:</h3>";
    echo "<pre>";
    foreach ($sampleUsers as $user) {
        echo "ID: " . $user['id'] . "\n";
        echo "Username: " . $user['username'] . "\n";
        echo "Name: " . $user['firstname'] . " " . $user['lastname'] . "\n";
        echo "Email: " . $user['email'] . "\n";
        echo "Role: " . $user['role'] . "\n";
        echo "Student ID: " . $user['student_id'] . "\n";
        echo "-------------------\n";
    }
    echo "</pre>";
    
    // Commit transaction
    $db->commit();
    
    echo "<p style='color: green;'>Successfully updated {$updatedCount} users</p>";
    echo "<p>Remaining null values: {$remainingNulls}</p>";
    
} catch (PDOException $e) {
    // Rollback transaction on error
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?> 