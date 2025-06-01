<?php
require_once 'config.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

try {
    $db = getDB();
    
    // First, check if the role column exists
    $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'role'");
    $roleColumn = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$roleColumn) {
        echo "Error: Role column does not exist in users table.";
        exit;
    }
    
    // Update the existing superadmin account with explicit role
    $stmt = $db->prepare("UPDATE users SET role = 'super_admin', is_active = 1 WHERE username = ?");
    if ($stmt->execute(['superadmin'])) {
        // Verify the update
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute(['superadmin']);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            echo "Super admin account updated successfully:<br>";
            echo "Username: " . htmlspecialchars($user['username']) . "<br>";
            echo "Role: " . htmlspecialchars($user['role']) . "<br>";
            echo "Status: " . ($user['is_active'] ? 'Active' : 'Inactive') . "<br>";
            
            if ($user['role'] !== 'super_admin') {
                echo "<br>Warning: Role was not properly set. Attempting to fix...<br>";
                
                // Try direct SQL update
                $stmt = $db->prepare("UPDATE users SET role = 'super_admin' WHERE username = ?");
                $stmt->execute(['superadmin']);
                
                // Verify again
                $stmt = $db->prepare("SELECT role FROM users WHERE username = ?");
                $stmt->execute(['superadmin']);
                $updatedRole = $stmt->fetchColumn();
                
                echo "Updated role: " . htmlspecialchars($updatedRole) . "<br>";
            }
            
            echo "<br>You can now log in with:<br>";
            echo "Username: superadmin<br>";
            echo "Password: SuperAdmin@123";
        } else {
            echo "Error: Could not verify the super admin account.";
        }
    } else {
        echo "Error: Failed to update the super admin account.";
    }
} catch (PDOException $e) {
    echo "Database Error: " . htmlspecialchars($e->getMessage());
}
?> 