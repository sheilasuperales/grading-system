<?php
require_once 'config.php';

try {
    $db = getDB();
    
    // Check login attempts table
    echo "<h2>Login Attempts</h2>";
    
    // Get all login attempts
    $stmt = $db->query("SELECT * FROM login_attempts ORDER BY attempt_time DESC LIMIT 10");
    $attempts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<pre>";
    print_r($attempts);
    echo "</pre>";
    
    // Check if superadmin is blocked
    $stmt = $db->prepare("
        SELECT COUNT(*) as attempt_count 
        FROM login_attempts 
        WHERE username = ? 
        AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
    ");
    $stmt->execute(['superadmin']);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<h3>Superadmin Login Status</h3>";
    echo "Recent attempts: " . $result['attempt_count'] . "<br>";
    echo "Blocked: " . ($result['attempt_count'] >= MAX_LOGIN_ATTEMPTS ? 'Yes' : 'No') . "<br>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?> 