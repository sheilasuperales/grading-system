<?php
require_once 'config.php';

try {
    $db = getDB();
    
    // Start transaction
    $db->beginTransaction();
    
    echo "<h2>Restructuring User Tables</h2>";
    
    // First, backup existing data
    $stmt = $db->query("SELECT * FROM users");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Create new tables
    $db->exec("
        -- Common user fields table
        CREATE TABLE IF NOT EXISTS user_accounts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            role ENUM('student', 'instructor', 'admin', 'super_admin') NOT NULL,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        );

        -- Students table
        CREATE TABLE IF NOT EXISTS students (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNIQUE NOT NULL,
            first_name VARCHAR(50) NOT NULL,
            last_name VARCHAR(50) NOT NULL,
            middle_name VARCHAR(50),
            suffix VARCHAR(10),
            year_level VARCHAR(20) NOT NULL,
            section VARCHAR(20) NOT NULL,
            student_id INT AUTO_INCREMENT,
            FOREIGN KEY (user_id) REFERENCES user_accounts(id) ON DELETE CASCADE
        );

        -- Instructors table
        CREATE TABLE IF NOT EXISTS instructors (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNIQUE NOT NULL,
            first_name VARCHAR(50) NOT NULL,
            last_name VARCHAR(50) NOT NULL,
            middle_name VARCHAR(50),
            suffix VARCHAR(10),
            department VARCHAR(50),
            FOREIGN KEY (user_id) REFERENCES user_accounts(id) ON DELETE CASCADE
        );

        -- Admins table
        CREATE TABLE IF NOT EXISTS admins (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNIQUE NOT NULL,
            first_name VARCHAR(50) NOT NULL,
            last_name VARCHAR(50) NOT NULL,
            middle_name VARCHAR(50),
            suffix VARCHAR(10),
            department VARCHAR(50),
            FOREIGN KEY (user_id) REFERENCES user_accounts(id) ON DELETE CASCADE
        );

        -- Super Admins table
        CREATE TABLE IF NOT EXISTS super_admins (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNIQUE NOT NULL,
            first_name VARCHAR(50) NOT NULL,
            last_name VARCHAR(50) NOT NULL,
            middle_name VARCHAR(50),
            suffix VARCHAR(10),
            FOREIGN KEY (user_id) REFERENCES user_accounts(id) ON DELETE CASCADE
        );
    ");

    // Migrate existing data
    if (!empty($users)) {
        foreach ($users as $user) {
            // Insert into user_accounts
            $stmt = $db->prepare("
                INSERT INTO user_accounts (
                    username, password, email, role, is_active
                ) VALUES (
                    :username, :password, :email, :role, :is_active
                )
            ");

            $stmt->execute([
                ':username' => $user['username'],
                ':password' => $user['password'],
                ':email' => $user['email'],
                ':role' => $user['role'],
                ':is_active' => $user['is_active']
            ]);

            $user_id = $db->lastInsertId();

            // Insert into appropriate role table
            switch ($user['role']) {
                case 'student':
                    $stmt = $db->prepare("
                        INSERT INTO students (
                            user_id, first_name, last_name, middle_name, 
                            suffix, year_level, section
                        ) VALUES (
                            :user_id, :first_name, :last_name, :middle_name,
                            :suffix, :year_level, :section
                        )
                    ");
                    $stmt->execute([
                        ':user_id' => $user_id,
                        ':first_name' => $user['first_name'],
                        ':last_name' => $user['last_name'],
                        ':middle_name' => $user['middle_name'],
                        ':suffix' => $user['suffix'],
                        ':year_level' => $user['year_level'],
                        ':section' => $user['section']
                    ]);
                    break;

                case 'instructor':
                    $stmt = $db->prepare("
                        INSERT INTO instructors (
                            user_id, first_name, last_name, middle_name,
                            suffix, department
                        ) VALUES (
                            :user_id, :first_name, :last_name, :middle_name,
                            :suffix, :department
                        )
                    ");
                    $stmt->execute([
                        ':user_id' => $user_id,
                        ':first_name' => $user['first_name'],
                        ':last_name' => $user['last_name'],
                        ':middle_name' => $user['middle_name'],
                        ':suffix' => $user['suffix'],
                        ':department' => $user['department']
                    ]);
                    break;

                case 'admin':
                    $stmt = $db->prepare("
                        INSERT INTO admins (
                            user_id, first_name, last_name, middle_name,
                            suffix, department
                        ) VALUES (
                            :user_id, :first_name, :last_name, :middle_name,
                            :suffix, :department
                        )
                    ");
                    $stmt->execute([
                        ':user_id' => $user_id,
                        ':first_name' => $user['first_name'],
                        ':last_name' => $user['last_name'],
                        ':middle_name' => $user['middle_name'],
                        ':suffix' => $user['suffix'],
                        ':department' => $user['department']
                    ]);
                    break;

                case 'super_admin':
                    $stmt = $db->prepare("
                        INSERT INTO super_admins (
                            user_id, first_name, last_name, middle_name,
                            suffix
                        ) VALUES (
                            :user_id, :first_name, :last_name, :middle_name,
                            :suffix
                        )
                    ");
                    $stmt->execute([
                        ':user_id' => $user_id,
                        ':first_name' => $user['first_name'],
                        ':last_name' => $user['last_name'],
                        ':middle_name' => $user['middle_name'],
                        ':suffix' => $user['suffix']
                    ]);
                    break;
            }
        }
    }

    // Drop the old users table
    $db->exec("DROP TABLE IF EXISTS users");

    // Verify the new structure
    $tables = ['user_accounts', 'students', 'instructors', 'admins', 'super_admins'];
    foreach ($tables as $table) {
        $stmt = $db->query("DESCRIBE $table");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h3>Table Structure for $table:</h3>";
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
    }

    // Show record counts
    foreach ($tables as $table) {
        $stmt = $db->query("SELECT COUNT(*) as count FROM $table");
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        echo "<p>Total records in $table: " . $count . "</p>";
    }

    // Commit transaction
    $db->commit();
    echo "<p style='color: green;'>Database has been successfully restructured!</p>";
    
} catch (PDOException $e) {
    // Rollback transaction on error
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?> 