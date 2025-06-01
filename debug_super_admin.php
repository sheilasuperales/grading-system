<?php
require_once 'config.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    $db = getDB();
    
    echo "<h2>Checking Super Admin Account</h2>";
    
    // Check user_accounts table
    echo "<h3>Checking user_accounts table:</h3>";
    $stmt = $db->query("SELECT * FROM user_accounts WHERE role = 'super_admin'");
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        echo "<pre>";
        echo "User Account Details:\n";
        print_r($user);
        echo "</pre>";
        
        // Check super_admins table
        echo "<h3>Checking super_admins table:</h3>";
        $stmt = $db->prepare("SELECT * FROM super_admins WHERE user_id = ?");
        $stmt->execute([$user['id']]);
        $super_admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($super_admin) {
            echo "<pre>";
            echo "Super Admin Details:\n";
            print_r($super_admin);
            echo "</pre>";
        } else {
            echo "<p style='color: red;'>No matching record found in super_admins table!</p>";
        }
        
        // Test password verification
        echo "<h3>Testing password verification:</h3>";
        $test_password = 'Admin@123456';
        if (password_verify($test_password, $user['password'])) {
            echo "<p style='color: green;'>Password verification successful!</p>";
        } else {
            echo "<p style='color: red;'>Password verification failed!</p>";
            echo "<p>Stored hash: " . $user['password'] . "</p>";
        }
        
    } else {
        echo "<p style='color: red;'>No super admin account found in user_accounts table!</p>";
        
        // Check if table exists
        $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        echo "<h3>Available tables:</h3>";
        echo "<pre>";
        print_r($tables);
        echo "</pre>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Database Error: " . $e->getMessage() . "</p>";
}
?> 