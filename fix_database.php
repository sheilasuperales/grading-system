<?php
require_once 'config.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    $db = getDB();
    
    echo "<h2>Fixing Database Structure</h2>";
    
    // 1. Create user_accounts table if it doesn't exist
    $db->exec("
        CREATE TABLE IF NOT EXISTS user_accounts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            role ENUM('student', 'instructor', 'admin', 'super_admin') NOT NULL,
            is_active BOOLEAN DEFAULT TRUE,
            last_login TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
    
    // 2. Create super_admins table if it doesn't exist
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
    
    // 3. Check if super admin exists in user_accounts
    $stmt = $db->query("SELECT * FROM user_accounts WHERE role = 'super_admin'");
    $superAdmin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$superAdmin) {
        echo "<p>Creating super admin account...</p>";
        
        // Create super admin account
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
        
        echo "<p style='color: green;'>Super admin account created successfully!</p>";
        echo "<p>Username: " . htmlspecialchars($username) . "</p>";
        echo "<p>Password: " . htmlspecialchars($password) . "</p>";
    } else {
        echo "<p>Super admin account already exists.</p>";
        
        // Verify super admin details
        $stmt = $db->prepare("
            SELECT ua.*, sa.first_name, sa.last_name 
            FROM user_accounts ua 
            JOIN super_admins sa ON ua.id = sa.user_id 
            WHERE ua.id = ?
        ");
        $stmt->execute([$superAdmin['id']]);
        $adminDetails = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($adminDetails) {
            echo "<p style='color: green;'>Super admin details verified.</p>";
            
            // Test password
            $testPassword = 'Admin@123456';
            if (password_verify($testPassword, $adminDetails['password'])) {
                echo "<p style='color: green;'>Password verification successful!</p>";
            } else {
                echo "<p style='color: red;'>Password verification failed. Updating password...</p>";
                
                // Update password
                $stmt = $db->prepare("
                    UPDATE user_accounts 
                    SET password = :password 
                    WHERE id = :id
                ");
                
                $stmt->execute([
                    ':password' => password_hash($testPassword, PASSWORD_DEFAULT),
                    ':id' => $superAdmin['id']
                ]);
                
                echo "<p style='color: green;'>Password updated successfully!</p>";
            }
        } else {
            echo "<p style='color: red;'>Super admin details missing. Creating details...</p>";
            
            // Create super admin details
            $stmt = $db->prepare("
                INSERT INTO super_admins (
                    user_id, first_name, last_name
                ) VALUES (
                    :user_id, 'Super', 'Admin'
                )
            ");
            
            $stmt->execute([
                ':user_id' => $superAdmin['id']
            ]);
            
            echo "<p style='color: green;'>Super admin details created successfully!</p>";
        }
    }
    
    echo "<p style='color: green;'>Database structure fixed successfully!</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?> 