<?php
require_once 'config.php';

try {
    $db = getDB();
    
    // Get superadmin account
    $stmt = $db->prepare("
        SELECT ua.*, sa.first_name, sa.last_name
        FROM user_accounts ua
        LEFT JOIN super_admins sa ON ua.id = sa.user_id
        WHERE ua.role = 'super_admin' AND ua.is_active = 1
    ");
    $stmt->execute();
    $superadmin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($superadmin) {
        echo "<h2>Super Admin Account Details</h2>";
        echo "<pre>";
        $debug = $superadmin;
        $debug['password'] = '***';
        print_r($debug);
        echo "</pre>";
        
        // Test password verification
        $test_password = 'admin123'; // Default password
        $verify_result = password_verify($test_password, $superadmin['password']);
        
        echo "<h3>Password Verification Test</h3>";
        echo "Test password: " . $test_password . "<br>";
        echo "Stored hash: " . $superadmin['password'] . "<br>";
        echo "Verification result: " . ($verify_result ? 'true' : 'false') . "<br>";
        
        // Generate new hash for comparison
        $new_hash = password_hash($test_password, PASSWORD_DEFAULT);
        echo "<br>New hash for comparison: " . $new_hash . "<br>";
        
        // Test new hash
        $new_verify = password_verify($test_password, $new_hash);
        echo "New hash verification: " . ($new_verify ? 'true' : 'false') . "<br>";
        
    } else {
        echo "<p style='color: red;'>No superadmin account found!</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?> 