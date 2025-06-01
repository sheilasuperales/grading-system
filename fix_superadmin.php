<?php
require_once 'config.php';

// Set credentials
$username = 'superadmin';
$password = 'superadmin123';
$email = 'superadmin@example.com';
$role = 'super_admin';

try {
    $db = getDB();
    // Check if superadmin exists
    $stmt = $db->prepare("SELECT id FROM user_accounts WHERE username = ? AND role = ?");
    $stmt->execute([$username, $role]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $hashed = password_hash($password, PASSWORD_DEFAULT);
    if ($row) {
        // Update password
        $update = $db->prepare("UPDATE user_accounts SET password = ? WHERE id = ?");
        $update->execute([$hashed, $row['id']]);
        echo "Superadmin password reset to 'superadmin123'.<br>";
    } else {
        // Insert new superadmin
        $insert = $db->prepare("INSERT INTO user_accounts (username, password, email, role, is_active) VALUES (?, ?, ?, ?, 1)");
        $insert->execute([$username, $hashed, $email, $role]);
        echo "Superadmin account created with username 'superadmin' and password 'superadmin123'.<br>";
    }
    echo "<b>Delete this script after use for security.</b>";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
} 