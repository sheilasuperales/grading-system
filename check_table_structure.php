<?php
require_once 'config.php';

try {
    $db = getDB();
    
    // Get table structure
    $stmt = $db->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Current Users Table Structure:</h2>";
    echo "<pre>";
    foreach ($columns as $column) {
        echo "Column: " . $column['Field'] . "\n";
        echo "Type: " . $column['Type'] . "\n";
        echo "Null: " . $column['Null'] . "\n";
        echo "Key: " . $column['Key'] . "\n";
        echo "Default: " . $column['Default'] . "\n";
        echo "Extra: " . $column['Extra'] . "\n";
        echo "-------------------\n";
    }
    echo "</pre>";
    
    // Check if there are any records
    $stmt = $db->query("SELECT COUNT(*) as count FROM users");
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "<p>Total records in users table: " . $count . "</p>";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?> 