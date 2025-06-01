<?php
require_once 'config.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
echo "<h2>Testing Super Admin Access</h2>";

try {
    $db = getDB();
    echo "Database connection successful!<br>";
    
    // Check if super admin exists
    $stmt = $db->prepare("SELECT * FROM users WHERE role = 'super_admin'");
    $stmt->execute();
    $superAdmin = $stmt->fetch();
    
    if ($superAdmin) {
        echo "<br>Super Admin account found:<br>";
        echo "Username: " . htmlspecialchars($superAdmin['username']) . "<br>";
        echo "Role: " . htmlspecialchars($superAdmin['role']) . "<br>";
        echo "Is Active: " . ($superAdmin['is_active'] ? 'Yes' : 'No') . "<br>";
    } else {
        echo "<br>No super admin account found!<br>";
    }
    
    // Check current session
    echo "<br>Current Session Data:<br>";
    echo "<pre>";
    print_r($_SESSION);
    echo "</pre>";
    
    // Check if super_admin_dashboard.php exists
    if (file_exists('super_admin_dashboard.php')) {
        echo "<br>super_admin_dashboard.php file exists!<br>";
        echo "File size: " . filesize('super_admin_dashboard.php') . " bytes<br>";
    } else {
        echo "<br>super_admin_dashboard.php file NOT found!<br>";
    }
    
} catch (PDOException $e) {
    echo "Database Error: " . htmlspecialchars($e->getMessage());
}
?> 