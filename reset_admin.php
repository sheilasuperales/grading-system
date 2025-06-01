<?php
require_once 'config.php';

function resetAdminPassword() {
    try {
        $db = getDB();
        
        // New default password
        $newPassword = 'Admin@123';
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        // Update admin password and ensure account is active
        $stmt = $db->prepare("UPDATE users SET password = ?, is_active = 1 WHERE role = 'admin' AND username = 'admin'");
        $result = $stmt->execute([$hashedPassword]);
        
        if ($result) {
            return "Admin password has been reset successfully!\nNew password: " . $newPassword;
        } else {
            return "No admin account found to reset.";
        }
    } catch (PDOException $e) {
        return "Error resetting password: " . $e->getMessage();
    }
}

// Only run this script directly
if (php_sapi_name() === 'cli') {
    echo resetAdminPassword();
}
?> 