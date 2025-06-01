<?php
// Force error display
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>PHP Test Page</h1>";
echo "<p>PHP is working if you can see this message.</p>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<h2>Session Test:</h2>";
session_start();
echo "<pre>";
print_r($_SESSION);
echo "</pre>";
echo "<h2>Database Test:</h2>";
try {
    require_once 'config.php';
    $db = getDB();
    echo "<p style='color: green;'>Database connection successful!</p>";
    
    $stmt = $db->query("SELECT COUNT(*) as count FROM users");
    $result = $stmt->fetch();
    echo "<p>Number of users in database: " . $result['count'] . "</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>Database error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?> 