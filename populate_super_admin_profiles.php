<?php
require_once 'config.php';

try {
    $db = getDB();
    
    // Get all super admin users
    $stmt = $db->prepare("
        SELECT id, username, email, first_name, last_name, middle_name, suffix, department, fullname 
        FROM users 
        WHERE role = 'super_admin'
    ");
    $stmt->execute();
    $superAdmins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($superAdmins as $admin) {
        try {
            // Check if profile already exists
            $checkStmt = $db->prepare("SELECT id FROM super_admin_profiles WHERE user_id = ?");
            $checkStmt->execute([$admin['id']]);
            
            if (!$checkStmt->fetch()) {
                // Create profile if it doesn't exist
                $stmt = $db->prepare("
                    INSERT INTO super_admin_profiles 
                    (user_id, email, first_name, last_name, middle_name, suffix, department, fullname)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $admin['id'],
                    $admin['email'],
                    $admin['first_name'],
                    $admin['last_name'],
                    $admin['middle_name'],
                    $admin['suffix'],
                    $admin['department'],
                    $admin['fullname']
                ]);
                
                // Log the profile creation
                $logStmt = $db->prepare("
                    INSERT INTO super_admin_activity_logs 
                    (user_id, activity_type, description, ip_address)
                    VALUES (?, 'profile_creation', 'Initial profile creation', 'SYSTEM')
                ");
                $logStmt->execute([$admin['id']]);
                
                $successCount++;
            }
        } catch (PDOException $e) {
            error_log("Error creating profile for super admin {$admin['username']}: " . $e->getMessage());
            $errorCount++;
        }
    }
    
    echo "Profile population completed!\n";
    echo "Successfully created profiles: $successCount\n";
    echo "Errors encountered: $errorCount\n";
    
} catch (PDOException $e) {
    die("Error populating super admin profiles: " . $e->getMessage());
}
?> 