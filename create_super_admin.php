<?php
require_once 'config.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    $db = getDB();
    
    // Start transaction
    $db->beginTransaction();
    
    // Check if super admin already exists
    $stmt = $db->prepare("SELECT COUNT(*) FROM user_accounts WHERE role = 'super_admin'");
    $stmt->execute();
    if ($stmt->fetchColumn() > 0) {
        echo "<p style='color: red;'>Super admin already exists!</p>";
        exit();
    }

    // Create super admin account
    $stmt = $db->prepare("
        INSERT INTO user_accounts (
            username, password, email, role, is_active
        ) VALUES (
            :username, :password, :email, 'super_admin', TRUE
        )
    ");

    $username = 'superadmin';
    $password = 'Admin@123456'; // You should change this password after first login
    $email = 'superadmin@school.edu';

    $stmt->execute([
        ':username' => $username,
        ':password' => password_hash($password, PASSWORD_DEFAULT),
        ':email' => $email
    ]);

    $user_id = $db->lastInsertId();

    // Add super admin details
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

    $db->commit();
    
    echo "<h2>Super Admin Account Created Successfully!</h2>";
    echo "<p>Username: " . htmlspecialchars($username) . "</p>";
    echo "<p>Password: " . htmlspecialchars($password) . "</p>";
    echo "<p style='color: red;'>IMPORTANT: Please change the password after your first login!</p>";
    
} catch (PDOException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?> 