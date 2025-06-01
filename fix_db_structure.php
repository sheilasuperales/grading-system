<?php
require_once 'config.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

try {
    $db = getDB();
    
    // Modify the role column to include super_admin
    $stmt = $db->prepare("ALTER TABLE users MODIFY COLUMN role ENUM('super_admin', 'admin', 'instructor', 'student') NOT NULL");
    if ($stmt->execute()) {
        echo "Successfully modified role column to include super_admin<br>";
        
        // Now update the superadmin user
        $stmt = $db->prepare("UPDATE users SET role = 'super_admin' WHERE username = 'superadmin'");
        if ($stmt->execute()) {
            // Verify the update
            $stmt = $db->prepare("SELECT * FROM users WHERE username = 'superadmin'");
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && $user['role'] === 'super_admin') {
                echo "<br>Super admin account updated successfully:<br>";
                echo "Username: " . htmlspecialchars($user['username']) . "<br>";
                echo "Role: " . htmlspecialchars($user['role']) . "<br>";
                echo "Status: " . ($user['is_active'] ? 'Active' : 'Inactive') . "<br>";
                echo "<br>You can now log in with:<br>";
                echo "Username: superadmin<br>";
                echo "Password: SuperAdmin@123";
            } else {
                echo "Error: Could not verify the super admin account update.";
            }
        } else {
            echo "Error: Failed to update the super admin account.";
        }
    } else {
        echo "Error: Failed to modify the role column.";
    }
} catch (PDOException $e) {
    echo "Database Error: " . htmlspecialchars($e->getMessage());
}
?> 