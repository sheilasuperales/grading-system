<?php
// Application Settings
define('APP_NAME', 'School Grading System');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost:8000');
define('BASE_PATH', '');
define('TIMEZONE', 'UTC');

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'school_grading');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Security Settings
define('SESSION_LIFETIME', 3600); // 1 hour
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_TIMEOUT', 900); // 15 minutes
define('PASSWORD_MIN_LENGTH', 8);
define('REQUIRE_STRONG_PASSWORD', true);

// Grade Settings
define('GRADE_SCALE', [
    'A' => ['min' => 90, 'max' => 100],
    'B' => ['min' => 80, 'max' => 89.99],
    'C' => ['min' => 70, 'max' => 79.99],
    'D' => ['min' => 60, 'max' => 69.99],
    'F' => ['min' => 0, 'max' => 59.99]
]);

// Error Reporting
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');
date_default_timezone_set(TIMEZONE);

// Database Connection Function
function getDB() {
    static $db = null;
    
    if ($db === null) {
        try {
            // Create DSN for PDO
            $dsn = "mysql:host=" . DB_HOST;
            if (DB_NAME) {
                $dsn .= ";dbname=" . DB_NAME;
            }
            $dsn .= ";charset=" . DB_CHARSET;
            
            // PDO options
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET,
                PDO::ATTR_PERSISTENT => true
            ];
            
            // Create PDO instance
            $db = new PDO($dsn, DB_USER, DB_PASS, $options);
            
            // Test the connection
            $db->query("SELECT 1");
            
        } catch (PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            // Retry connection once if it fails
            try {
                $db = new PDO($dsn, DB_USER, DB_PASS, $options);
                $db->query("SELECT 1");
            } catch (PDOException $retryError) {
                error_log("Database Connection Retry Error: " . $retryError->getMessage());
                die("Database connection failed after retry. Error: " . $retryError->getMessage());
            }
        }
    }
    
    return $db;
}

// Grade Calculation Functions
function calculateLetterGrade($numericalGrade) {
    foreach (GRADE_SCALE as $letter => $range) {
        if ($numericalGrade >= $range['min'] && $numericalGrade <= $range['max']) {
            return $letter;
        }
    }
    return 'F';
}

function calculateGPA($letterGrade) {
    $gpaScale = [
        'A' => 4.0,
        'B' => 3.0,
        'C' => 2.0,
        'D' => 1.0,
        'F' => 0.0
    ];
    
    return $gpaScale[$letterGrade] ?? 0.0;
}

// Security Functions
function isLoginBlocked($username) {
    $db = getDB();
    $stmt = $db->prepare("SELECT login_attempts, last_login FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if (!$user) return false;
    
    if ($user['login_attempts'] >= MAX_LOGIN_ATTEMPTS) {
        $timeElapsed = time() - strtotime($user['last_login']);
        if ($timeElapsed < LOGIN_TIMEOUT) {
            return true;
        }
        
        // Reset attempts if timeout has passed
        $stmt = $db->prepare("UPDATE users SET login_attempts = 0 WHERE username = ?");
        $stmt->execute([$username]);
    }
    
    return false;
}

function incrementLoginAttempts($username) {
    $db = getDB();
    $stmt = $db->prepare("UPDATE users SET login_attempts = login_attempts + 1, last_login = CURRENT_TIMESTAMP WHERE username = ?");
    $stmt->execute([$username]);
}

function resetLoginAttempts($username) {
    $db = getDB();
    $stmt = $db->prepare("UPDATE users SET login_attempts = 0 WHERE username = ?");
    $stmt->execute([$username]);
}

// Activity Logging
function logActivity($userId, $action, $details = '') {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO activity_log (user_id, action, details) VALUES (?, ?, ?)");
    $stmt->execute([$userId, $action, $details]);
}

// Session Management
function initSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > SESSION_LIFETIME)) {
        session_unset();
        session_destroy();
        header('Location: login.php?msg=session_expired');
        exit();
    }
    
    $_SESSION['LAST_ACTIVITY'] = time();
}

// Input Validation
function sanitizeInput($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function validatePassword($password) {
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        return false;
    }
    
    if (REQUIRE_STRONG_PASSWORD) {
        // Require at least one uppercase letter, one lowercase letter, one number, and one special character
        if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $password)) {
            return false;
        }
    }
    
    return true;
}

// Create logs directory if it doesn't exist
if (!file_exists(__DIR__ . '/logs')) {
    mkdir(__DIR__ . '/logs', 0755, true);
} 