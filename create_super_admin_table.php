<?php
require_once 'config.php';

try {
    $db = getDB();
    
    // Create super_admin_profiles table
    $db->exec("CREATE TABLE IF NOT EXISTS super_admin_profiles (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        email VARCHAR(255) NOT NULL,
        first_name VARCHAR(100) NOT NULL,
        last_name VARCHAR(100) NOT NULL,
        middle_name VARCHAR(100),
        suffix VARCHAR(10),
        department VARCHAR(50) NOT NULL,
        fullname VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_email (email)
    )");

    // Create super_admin_activity_logs table for tracking changes
    $db->exec("CREATE TABLE IF NOT EXISTS super_admin_activity_logs (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        activity_type VARCHAR(50) NOT NULL,
        description TEXT,
        ip_address VARCHAR(45),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    echo "Super admin tables created successfully!";
} catch (PDOException $e) {
    die("Error creating tables: " . $e->getMessage());
}
?> 