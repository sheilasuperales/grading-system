<?php
require_once 'config.php';

try {
    $db = getDB();
    // Find all instructors in user_accounts not in instructors
    $stmt = $db->query("SELECT * FROM user_accounts WHERE role = 'instructor' AND id NOT IN (SELECT user_id FROM instructors)");
    $missing = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $count = 0;
    foreach ($missing as $row) {
        $insert = $db->prepare("INSERT INTO instructors (user_id, first_name, last_name, middle_name, suffix, department) VALUES (?, ?, ?, ?, ?, ?)");
        $insert->execute([
            $row['id'],
            $row['first_name'] ?? $row['username'],
            $row['last_name'] ?? $row['username'],
            $row['middle_name'] ?? '',
            $row['suffix'] ?? '',
            $row['department'] ?? ''
        ]);
        $count++;
    }
    echo "<p style='color: green;'>Added $count missing instructor(s) to the instructors table.</p>";
    if ($count > 0) {
        echo "<pre>";
        print_r($missing);
        echo "</pre>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?> 