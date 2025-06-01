<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check if logs directory exists
if (!file_exists(__DIR__ . '/logs')) {
    die("Logs directory not found.");
}

// Get the error log file
$log_file = __DIR__ . '/logs/error.log';

if (!file_exists($log_file)) {
    die("Error log file not found.");
}

// Read the last 50 lines of the log file
$logs = array_slice(file($log_file), -50);

echo "<pre>";
foreach ($logs as $log) {
    echo htmlspecialchars($log);
}
echo "</pre>";
?> 