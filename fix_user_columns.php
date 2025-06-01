<?php
require_once 'config.php';

try {
    $db = getDB();
    
    // Start transaction
    $db->beginTransaction();
    
    // First check current structure
    $stmt = $db->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $columnNames = array_column($columns, 'Field');
    
    echo "<h2>Fixing Users Table Structure</h2>";
    
    // Check if old columns exist and new ones don't
    if (in_array('first_name', $columnNames) && !in_array('firstname', $columnNames)) {
        // Rename columns to match new structure
        $db->exec("ALTER TABLE users 
                  CHANGE COLUMN first_name firstname VARCHAR(50),
                  CHANGE COLUMN last_name lastname VARCHAR(50),
                  CHANGE COLUMN middle_name middlename VARCHAR(50)");
        
        echo "<p>Successfully renamed columns:</p>";
        echo "<ul>";
        echo "<li>first_name → firstname</li>";
        echo "<li>last_name → lastname</li>";
        echo "<li>middle_name → middlename</li>";
        echo "</ul>";
    } else {
        echo "<p>Column names are already correct or structure is different than expected.</p>";
    }
    
    // Verify the changes
    $stmt = $db->query("DESCRIBE users");
    $newColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Current Table Structure:</h3>";
    echo "<pre>";
    foreach ($newColumns as $column) {
        echo "Column: " . $column['Field'] . "\n";
        echo "Type: " . $column['Type'] . "\n";
        echo "Null: " . $column['Null'] . "\n";
        echo "Key: " . $column['Key'] . "\n";
        echo "Default: " . $column['Default'] . "\n";
        echo "Extra: " . $column['Extra'] . "\n";
        echo "-------------------\n";
    }
    echo "</pre>";
    
    // Commit transaction
    $db->commit();
    echo "<p style='color: green;'>Table structure updated successfully!</p>";
    
} catch (PDOException $e) {
    // Rollback transaction on error
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?> 