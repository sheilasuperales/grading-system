<?php
require_once 'config.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

function setupSuperAdmin() {
    try {
        $db = getDB();
        echo "Connected to database successfully!\n\n";
        
        // First, check if the users table exists
        try {
            $db->query("SELECT 1 FROM users LIMIT 1");
            echo "Users table exists.\n\n";
        } catch (PDOException $e) {
            die("Users table does not exist. Please run setup_database.php first.\n");
        }
        
        // Check if super admin already exists
        $stmt = $db->prepare("SELECT * FROM users WHERE role = 'super_admin'");
        $stmt->execute();
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            echo "Existing super admin account found:\n";
            echo "Username: " . $existing['username'] . "\n";
            echo "Status: " . ($existing['is_active'] ? 'Active' : 'Inactive') . "\n";
            echo "Created at: " . $existing['created_at'] . "\n\n";
            
            // Update the password for existing super admin
            $password = 'SuperAdmin@123';
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            
            $updateStmt = $db->prepare("UPDATE users SET password = ?, is_active = 1 WHERE id = ?");
            $updateStmt->execute([$hashed, $existing['id']]);
            
            echo "Password has been reset to: SuperAdmin@123\n";
            return "You can now log in with these credentials.";
        }

        // Create new super admin account
        $username = 'superadmin';
        $password = 'SuperAdmin@123';
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        
        echo "Creating new super admin account...\n";
        
        $stmt = $db->prepare("INSERT INTO users (username, password, role, fullname, email, is_active) 
                             VALUES (:username, :password, 'super_admin', 'Super Administrator', 'superadmin@school.com', 1)");
        $result = $stmt->execute([
            ':username' => $username,
            ':password' => $hashed
        ]);

        if ($result) {
            // Verify the account was created
            $verifyStmt = $db->prepare("SELECT * FROM users WHERE username = ?");
            $verifyStmt->execute([$username]);
            $user = $verifyStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                echo "\nSuper admin account created successfully!\n";
                echo "Account details:\n";
                echo "----------------\n";
                echo "Username: superadmin\n";
                echo "Password: SuperAdmin@123\n";
                echo "Role: " . $user['role'] . "\n";
                echo "Status: " . ($user['is_active'] ? 'Active' : 'Inactive') . "\n";
                echo "ID: " . $user['id'] . "\n";
                
                // Verify password hash
                if (password_verify($password, $user['password'])) {
                    echo "Password hash verification: SUCCESS\n";
                } else {
                    echo "Password hash verification: FAILED\n";
                }
                
                return "\nYou can now log in with these credentials.";
            } else {
                return "Error: Account created but verification failed.";
            }
        } else {
            return "Error: Failed to create super admin account.";
        }
    } catch (PDOException $e) {
        return "Database error: " . $e->getMessage();
    }
}

// Only run this script directly
if (php_sapi_name() === 'cli' || isset($_GET['setup'])) {
    echo "<pre>";
    echo setupSuperAdmin();
    echo "</pre>";
}
?> 