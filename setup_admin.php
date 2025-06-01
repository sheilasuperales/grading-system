<?php
require_once 'config.php';

function setupAdmin() {
    try {
        $db = getDB();
        
        // Check if admin already exists
        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin'");
        $stmt->execute();
        if ($stmt->fetchColumn() > 0) {
            return "Admin account already exists.";
        }

        // Create default admin account
        $username = 'admin';
        $password = 'admin123'; // This should be changed immediately after first login
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $db->prepare("INSERT INTO users (username, password, role, fullname, email) 
                             VALUES (:username, :password, 'admin', 'System Administrator', 'admin@school.com')");
        $stmt->execute([
            ':username' => $username,
            ':password' => $hashed
        ]);

        return "Default admin account created successfully!\nUsername: admin\nPassword: admin123\n\nPlease change the password after first login.";
    } catch (PDOException $e) {
        return "Error creating admin account: " . $e->getMessage();
    }
}

// Only run this script directly
if (php_sapi_name() === 'cli' || isset($_GET['setup'])) {
    echo setupAdmin();
}
?> 