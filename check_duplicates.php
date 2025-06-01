<?php
require_once 'config.php';

try {
    $db = getDB();
    
    // Check for duplicate courses
    $stmt = $db->query("SELECT course_code, COUNT(*) as count FROM courses GROUP BY course_code HAVING count > 1");
    $duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($duplicates)) {
        echo "No duplicate courses found.\n";
    } else {
        echo "Found duplicate courses:\n";
        print_r($duplicates);
        
        // Get all BSIT entries
        $stmt = $db->query("SELECT * FROM courses WHERE course_code = 'BSIT'");
        $bsit_entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "\nAll BSIT entries:\n";
        print_r($bsit_entries);
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
} 