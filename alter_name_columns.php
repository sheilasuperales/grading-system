<?php
require_once 'config.php';

try {
    $db = getDB();
    
    // Start transaction
    $db->beginTransaction();
    
    // First, check if the new columns already exist
    $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'firstname'");
    if ($stmt->rowCount() == 0) {
        // Add new columns
        $db->exec("ALTER TABLE users 
                  ADD COLUMN firstname VARCHAR(50) AFTER email,
                  ADD COLUMN lastname VARCHAR(50) AFTER firstname,
                  ADD COLUMN middlename VARCHAR(50) AFTER lastname,
                  ADD COLUMN suffix VARCHAR(10) AFTER middlename");
        
        // Update the new columns with data from existing columns
        $db->exec("UPDATE users SET 
                  firstname = first_name,
                  lastname = last_name,
                  middlename = middle_name,
                  suffix = suffix");
        
        // Drop the old columns
        $db->exec("ALTER TABLE users 
                  DROP COLUMN fullname,
                  DROP COLUMN first_name,
                  DROP COLUMN last_name,
                  DROP COLUMN middle_name");
        
        echo "Successfully updated name columns in users table.<br>";
        echo "Changes made:<br>";
        echo "1. Added new columns: firstname, lastname, middlename, suffix<br>";
        echo "2. Migrated data from old columns to new ones<br>";
        echo "3. Removed old columns: fullname, first_name, last_name, middle_name<br>";
    } else {
        echo "New name columns already exist in users table.";
    }
    
    // Commit transaction
    $db->commit();
    
} catch (PDOException $e) {
    // Rollback transaction on error
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo "Error: " . $e->getMessage();
}
?> 