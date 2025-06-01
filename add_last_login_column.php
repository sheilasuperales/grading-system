<?php
require_once 'config.php';

try {
    $db = getDB();
    // Check if last_login column exists
    $stmt = $db->query("SHOW COLUMNS FROM user_accounts LIKE 'last_login'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE user_accounts ADD COLUMN last_login TIMESTAMP NULL AFTER is_active");
        echo "Successfully added last_login column to user_accounts table.";
    } else {
        echo "last_login column already exists in user_accounts table.";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?> 