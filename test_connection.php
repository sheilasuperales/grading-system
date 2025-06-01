<?php
require_once 'config.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

echo "<h1>Database Connection Test</h1>";

try {
    // Test database connection
    $db = getDB();
    echo "<p style='color: green;'>✓ Database connection successful!</p>";
    
    // Test users table
    $stmt = $db->query("SELECT COUNT(*) as count FROM users");
    $count = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p>Found {$count['count']} total users in database.</p>";
    
    // Check super admin account
    $stmt = $db->prepare("SELECT * FROM users WHERE role = 'super_admin'");
    $stmt->execute();
    $superAdmin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($superAdmin) {
        echo "<p style='color: green;'>✓ Super admin account exists:</p>";
        echo "<ul>";
        echo "<li>Username: " . htmlspecialchars($superAdmin['username']) . "</li>";
        echo "<li>Role: " . htmlspecialchars($superAdmin['role']) . "</li>";
        echo "<li>Status: " . ($superAdmin['is_active'] ? 'Active' : 'Inactive') . "</li>";
        echo "<li>Last Login: " . ($superAdmin['last_login'] ? $superAdmin['last_login'] : 'Never') . "</li>";
        echo "</ul>";
    } else {
        echo "<p style='color: red;'>✗ No super admin account found!</p>";
        echo "<p>Would you like to create one? <a href='setup_super_admin.php'>Click here</a></p>";
    }
    
    // Test session handling
    session_start();
    echo "<h2>Current Session Data:</h2>";
    echo "<pre>";
    print_r($_SESSION);
    echo "</pre>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>✗ Database Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Please check your database configuration in config.php</p>";
}
?> 