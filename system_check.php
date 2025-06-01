<?php
require_once 'config.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

function checkTable($db, $tableName) {
    try {
        $stmt = $db->query("SHOW TABLES LIKE '$tableName'");
        $exists = $stmt->rowCount() > 0;
        
        if ($exists) {
            // Get table structure
            $stmt = $db->query("DESCRIBE $tableName");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get row count
            $stmt = $db->query("SELECT COUNT(*) FROM $tableName");
            $count = $stmt->fetchColumn();
            
            return [
                'exists' => true,
                'columns' => $columns,
                'row_count' => $count
            ];
        }
        
        return ['exists' => false];
    } catch (PDOException $e) {
        return ['exists' => false, 'error' => $e->getMessage()];
    }
}

try {
    $db = getDB();
    echo "<h1>System Check Report</h1>";
    
    // 1. Check Database Connection
    echo "<h2>1. Database Connection</h2>";
    try {
        $db->query("SELECT 1");
        echo "<p style='color: green;'>✓ Database connection successful</p>";
    } catch (PDOException $e) {
        echo "<p style='color: red;'>✗ Database connection failed: " . $e->getMessage() . "</p>";
        throw $e;
    }
    
    // 2. Check Required Tables
    echo "<h2>2. Database Tables</h2>";
    $requiredTables = [
        'user_accounts',
        'students',
        'instructors',
        'admins',
        'super_admins',
        'courses',
        'enrollments',
        'grades'
    ];
    
    $tablesStatus = [];
    foreach ($requiredTables as $table) {
        echo "<h3>Checking table: $table</h3>";
        $status = checkTable($db, $table);
        
        if ($status['exists']) {
            echo "<p style='color: green;'>✓ Table exists</p>";
            echo "<p>Row count: " . $status['row_count'] . "</p>";
            echo "<h4>Table Structure:</h4>";
            echo "<pre>";
            print_r($status['columns']);
            echo "</pre>";
            $tablesStatus[$table] = true;
        } else {
            echo "<p style='color: red;'>✗ Table does not exist</p>";
            if (isset($status['error'])) {
                echo "<p style='color: red;'>Error: " . $status['error'] . "</p>";
            }
            $tablesStatus[$table] = false;
        }
    }
    
    // 3. Check User Accounts
    echo "<h2>3. User Accounts Check</h2>";
    
    // Check super admin
    $stmt = $db->query("
        SELECT ua.*, sa.first_name, sa.last_name 
        FROM user_accounts ua 
        LEFT JOIN super_admins sa ON ua.id = sa.user_id 
        WHERE ua.role = 'super_admin'
    ");
    $superAdmin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($superAdmin) {
        echo "<h3>Super Admin Account:</h3>";
        echo "<pre>";
        print_r($superAdmin);
        echo "</pre>";
        
        // Test password
        $testPassword = 'Admin@123456';
        if (password_verify($testPassword, $superAdmin['password'])) {
            echo "<p style='color: green;'>✓ Super admin password verification successful</p>";
        } else {
            echo "<p style='color: red;'>✗ Super admin password verification failed</p>";
        }
    } else {
        echo "<p style='color: red;'>✗ No super admin account found</p>";
    }
    
    // Check other user types
    $roles = ['admin', 'instructor', 'student'];
    foreach ($roles as $role) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM user_accounts WHERE role = ?");
        $stmt->execute([$role]);
        $count = $stmt->fetchColumn();
        echo "<p>$role accounts: $count</p>";
    }
    
    // 4. Check Foreign Key Relationships
    echo "<h2>4. Foreign Key Relationships</h2>";
    
    // Check user_accounts relationships
    $tables = ['students', 'instructors', 'admins', 'super_admins'];
    foreach ($tables as $table) {
        $stmt = $db->prepare("
            SELECT COUNT(*) as orphaned 
            FROM $table t 
            LEFT JOIN user_accounts ua ON t.user_id = ua.id 
            WHERE ua.id IS NULL
        ");
        $stmt->execute();
        $orphaned = $stmt->fetchColumn();
        
        if ($orphaned > 0) {
            echo "<p style='color: red;'>✗ Found $orphaned orphaned records in $table table</p>";
        } else {
            echo "<p style='color: green;'>✓ No orphaned records in $table table</p>";
        }
    }
    
    // 5. System Recommendations
    echo "<h2>5. System Recommendations</h2>";
    
    if (!$superAdmin) {
        echo "<p style='color: orange;'>⚠️ No super admin account found. Run recreate_super_admin.php to create one.</p>";
    }
    
    $missingTables = array_filter($tablesStatus, function($status) { return !$status; });
    if (!empty($missingTables)) {
        echo "<p style='color: orange;'>⚠️ Missing tables: " . implode(', ', array_keys($missingTables)) . "</p>";
        echo "<p>Run restructure_users.php to create missing tables.</p>";
    }
    
    // Check for any inactive accounts
    $stmt = $db->query("SELECT COUNT(*) FROM user_accounts WHERE is_active = FALSE");
    $inactiveCount = $stmt->fetchColumn();
    if ($inactiveCount > 0) {
        echo "<p style='color: orange;'>⚠️ Found $inactiveCount inactive user accounts</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>System Check Failed: " . $e->getMessage() . "</p>";
}
?> 