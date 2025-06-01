<?php
require_once 'config.php';

try {
    $db = getDB();
    
    // Get all foreign key constraints referencing the courses table with their DELETE rule
    $query = "SELECT 
        kcu.TABLE_NAME,
        kcu.COLUMN_NAME,
        kcu.CONSTRAINT_NAME,
        kcu.REFERENCED_TABLE_NAME,
        kcu.REFERENCED_COLUMN_NAME,
        rc.DELETE_RULE
    FROM information_schema.REFERENTIAL_CONSTRAINTS rc
    JOIN information_schema.KEY_COLUMN_USAGE kcu 
        ON rc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME 
        AND rc.CONSTRAINT_SCHEMA = kcu.CONSTRAINT_SCHEMA
    WHERE kcu.REFERENCED_TABLE_NAME = 'courses' 
    AND kcu.TABLE_SCHEMA = 'school_grading'";
    
    $constraints = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Foreign key constraints referencing the courses table:\n";
    print_r($constraints);
    
    // Get all foreign key constraints in the database
    $query = "SELECT 
        TABLE_NAME,
        COLUMN_NAME,
        CONSTRAINT_NAME,
        REFERENCED_TABLE_NAME,
        REFERENCED_COLUMN_NAME
    FROM information_schema.KEY_COLUMN_USAGE 
    WHERE REFERENCED_TABLE_NAME IS NOT NULL 
    AND TABLE_SCHEMA = 'school_grading'";
    
    $all_constraints = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\nAll foreign key constraints in the database:\n";
    print_r($all_constraints);
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?> 