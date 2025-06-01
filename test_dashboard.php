<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

session_start();
require_once 'config.php';

echo "<h1>Dashboard Test Page</h1>";

// Test Session
echo "<h2>Session Data:</h2>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

// Test Database
try {
    $db = getDB();
    echo "<p style='color: green;'>Database connection successful!</p>";
    
    // Test users table
    $stmt = $db->query("SELECT COUNT(*) as count FROM users");
    $count = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p>Total users in database: " . $count['count'] . "</p>";
    
    // Show current user details
    if (isset($_SESSION['user_id'])) {
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "<h2>Current User Details:</h2>";
        echo "<p>Username: " . htmlspecialchars($user['username']) . "</p>";
        echo "<p>Role: " . htmlspecialchars($user['role']) . "</p>";
        echo "<p>Full Name: " . htmlspecialchars($user['fullname']) . "</p>";
        echo "<p>Is Active: " . ($user['is_active'] ? 'Yes' : 'No') . "</p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Database Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Add a link to the dashboard
echo "<p><a href='super_admin_dashboard.php'>Go to Super Admin Dashboard</a></p>";
?> 