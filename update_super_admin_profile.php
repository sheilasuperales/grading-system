<?php
require_once 'config.php';

try {
    $db = getDB();
    
    // Begin transaction
    $db->beginTransaction();
    
    // Update user table
    $stmt = $db->prepare("
        UPDATE users 
        SET username = 'superadmin',
            email = 'superadmin@gmail.com',
            first_name = 'Super',
            last_name = 'Admin',
            middle_name = NULL,
            suffix = NULL,
            department = 'BSIT',
            fullname = 'Super Admin'
        WHERE role = 'super_admin'
    ");
    $stmt->execute();
    
    // Update super_admin_profiles table
    $stmt = $db->prepare("
        UPDATE super_admin_profiles 
        SET email = 'superadmin@gmail.com',
            first_name = 'Super',
            last_name = 'Admin',
            middle_name = NULL,
            suffix = NULL,
            department = 'BSIT',
            fullname = 'Super Admin'
        WHERE user_id IN (SELECT id FROM users WHERE role = 'super_admin')
    ");
    $stmt->execute();
    
    // Log the update
    $stmt = $db->prepare("
        INSERT INTO super_admin_activity_logs 
        (user_id, activity_type, description, ip_address)
        SELECT id, 'profile_update', 'Updated super admin profile information', 'SYSTEM'
        FROM users 
        WHERE role = 'super_admin'
    ");
    $stmt->execute();
    
    // Commit transaction
    $db->commit();
    
    echo "Super admin profile updated successfully!";
    
} catch (PDOException $e) {
    // Rollback transaction on error
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    die("Error updating super admin profile: " . $e->getMessage());
}
?> 