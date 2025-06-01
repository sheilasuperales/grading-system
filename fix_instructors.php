<?php
require_once 'config.php';

try {
    $db = getDB();
    // Find instructors with missing first_name or last_name
    $stmt = $db->query("SELECT i.user_id, ua.username, i.first_name, i.last_name FROM instructors i JOIN user_accounts ua ON i.user_id = ua.id WHERE i.first_name = '' OR i.last_name = '' OR i.first_name IS NULL OR i.last_name IS NULL");
    $to_fix = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $count = 0;
    foreach ($to_fix as $row) {
        $first_name = $row['first_name'] ?: $row['username'];
        $last_name = $row['last_name'] ?: 'Unknown';
        $update = $db->prepare("UPDATE instructors SET first_name = ?, last_name = ? WHERE user_id = ?");
        $update->execute([$first_name, $last_name, $row['user_id']]);
        $count++;
    }
    echo "<p style='color: green;'>Fixed $count instructor(s) with missing names.</p>";
    if ($count > 0) {
        echo "<pre>";
        print_r($to_fix);
        echo "</pre>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?> 