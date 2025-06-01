<?php
require_once 'config.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    $db = getDB();
    
    // Start transaction
    $db->beginTransaction();
    
    echo "<h2>Recreating Super Admin Account</h2>";
    
    // First, delete any existing super admin accounts
    echo "<p>Cleaning up existing super admin accounts...</p>";
    
    // Delete from super_admins first (due to foreign key)
    $stmt = $db->prepare("DELETE sa FROM super_admins sa 
                         INNER JOIN user_accounts ua ON sa.user_id = ua.id 
                         WHERE ua.role = 'super_admin'");
    $stmt->execute();
    
    // Then delete from user_accounts
    $stmt = $db->prepare("DELETE FROM user_accounts WHERE role = 'super_admin'");
    $stmt->execute();
    
    echo "<p>Creating new super admin account...</p>";
    
    // Create super admin account
    $username = 'superadmin';
    $password = 'Admin@123456';
    $email = 'superadmin@school.edu';
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
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
        ':password' => $hashed_password,
        ':email' => $email
    ]);

    $user_id = $db->lastInsertId();
    echo "<p>Created user account with ID: " . $user_id . "</p>";

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
    
    echo "<p>Created super admin details</p>";

    // Verify the account was created correctly
    $stmt = $db->prepare("
        SELECT ua.*, sa.first_name, sa.last_name 
        FROM user_accounts ua 
        JOIN super_admins sa ON ua.id = sa.user_id 
        WHERE ua.id = ?
    ");
    $stmt->execute([$user_id]);
    $new_admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($new_admin) {
        echo "<p style='color: green;'>Super admin account created successfully!</p>";
        echo "<pre>";
        echo "Account Details:\n";
        print_r($new_admin);
        echo "</pre>";
        
        // Test password verification
        if (password_verify($password, $new_admin['password'])) {
            echo "<p style='color: green;'>Password verification successful!</p>";
        } else {
            echo "<p style='color: red;'>Password verification failed!</p>";
        }
    } else {
        throw new Exception("Failed to verify super admin account creation");
    }

    $db->commit();
    
    echo "<h3>Super Admin Login Credentials:</h3>";
    echo "<p>Username: " . htmlspecialchars($username) . "</p>";
    echo "<p>Password: " . htmlspecialchars($password) . "</p>";
    echo "<p style='color: red;'>IMPORTANT: Please change the password after your first login!</p>";
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?> 