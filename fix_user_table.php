<?php
require_once 'config.php';

try {
    $db = getDB();
    
    // Start transaction
    $db->beginTransaction();
    
    echo "<h2>Rebuilding Users Table Structure</h2>";
    
    // First, backup existing data
    $stmt = $db->query("SELECT * FROM users");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Drop and recreate the table with correct structure
    $db->exec("DROP TABLE IF EXISTS users");
    
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
        student_id INT AUTO_INCREMENT,
        department VARCHAR(50),
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
    // Restore data with new column names
    if (!empty($users)) {
        $stmt = $db->prepare("INSERT INTO users (
            username, password, role, email, 
            firstname, lastname, middlename, suffix,
            year_level, section, student_id, department, is_active
        ) VALUES (
            :username, :password, :role, :email,
            :firstname, :lastname, :middlename, :suffix,
            :year_level, :section, :student_id, :department, :is_active
        )");
        
        foreach ($users as $user) {
            $stmt->execute([
                'username' => $user['username'],
                'password' => $user['password'],
                'role' => $user['role'],
                'email' => $user['email'],
                'firstname' => $user['first_name'] ?? $user['firstname'] ?? '',
                'lastname' => $user['last_name'] ?? $user['lastname'] ?? '',
                'middlename' => $user['middle_name'] ?? $user['middlename'] ?? '',
                'suffix' => $user['suffix'] ?? '',
                'year_level' => $user['year_level'] ?? '',
                'section' => $user['section'] ?? '',
                'student_id' => $user['student_id'] ?? '',
                'department' => $user['department'] ?? '',
                'is_active' => $user['is_active'] ?? true
            ]);
        }
    }
    
    // Verify the new structure
    $stmt = $db->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>New Table Structure:</h3>";
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
    
    // Show record count
    $stmt = $db->query("SELECT COUNT(*) as count FROM users");
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "<p>Total records restored: " . $count . "</p>";
    
    // Commit transaction
    $db->commit();
    echo "<p style='color: green;'>Users table has been successfully rebuilt!</p>";
    
} catch (PDOException $e) {
    // Rollback transaction on error
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?> 