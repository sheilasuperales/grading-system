<?php
require_once 'config.php';

try {
    $db = getDB();
    
    // Start transaction
    $db->beginTransaction();
    
    // Get superadmin account
    $stmt = $db->prepare("SELECT id FROM user_accounts WHERE role = 'super_admin' AND is_active = 1");
    $stmt->execute();
    $superadmin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($superadmin) {
        // Update password
        $new_password = 'admin123';
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        $stmt = $db->prepare("UPDATE user_accounts SET password = ? WHERE id = ?");
        $stmt->execute([$hashed_password, $superadmin['id']]);
        
        // Verify the update
        $stmt = $db->prepare("SELECT password FROM user_accounts WHERE id = ?");
        $stmt->execute([$superadmin['id']]);
        $updated = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (password_verify($new_password, $updated['password'])) {
            echo "<p style='color: green;'>Superadmin password updated successfully!</p>";
            echo "<p>New password: " . $new_password . "</p>";
            echo "<p>New hash: " . $updated['password'] . "</p>";
        } else {
            throw new Exception("Password update verification failed");
        }
        
        $db->commit();
    } else {
        throw new Exception("No superadmin account found");
    }
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?> 