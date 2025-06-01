<?php
// Security Middleware
session_start();

// Session Security Settings
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
ini_set('session.use_strict_mode', 1);

// CSRF Protection
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        logActivity($_SESSION['user_id'] ?? 0, 'security_warning', 'CSRF token verification failed');
        die('Invalid security token. Please try again.');
    }
    return true;
}

// XSS Protection Headers
header('X-XSS-Protection: 1; mode=block');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
if (isset($_SERVER['HTTPS'])) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
}

// Role-based Access Control
function checkRole($allowed_roles) {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        header('Location: /login.php?msg=session_expired');
        exit();
    }

    if (!in_array($_SESSION['role'], (array)$allowed_roles)) {
        logActivity($_SESSION['user_id'], 'security_warning', 'Unauthorized access attempt');
        header('Location: /login.php?msg=unauthorized');
        exit();
    }

    // Check if user is active
    $db = getDB();
    $stmt = $db->prepare("SELECT is_active FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $is_active = $stmt->fetchColumn();

    if (!$is_active) {
        session_destroy();
        header('Location: /login.php?msg=account_disabled');
        exit();
    }

    return true;
}

// IP-based Security
function checkIPSecurity() {
    $db = getDB();
    $ip = $_SERVER['REMOTE_ADDR'];
    
    // Clean up old attempts
    $db->exec("DELETE FROM login_attempts WHERE timestamp < DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
    
    // Check if IP is blocked
    $stmt = $db->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND timestamp > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
    $stmt->execute([$ip]);
    $attempts = $stmt->fetchColumn();
    
    if ($attempts >= MAX_LOGIN_ATTEMPTS) {
        logActivity(0, 'security_warning', "IP blocked due to multiple failed attempts: {$ip}");
        die('Too many login attempts. Please try again later.');
    }
}

// Password Security
function validatePasswordStrength($password) {
    $errors = [];
    
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        $errors[] = "Password must be at least " . PASSWORD_MIN_LENGTH . " characters long";
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must include at least one uppercase letter";
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Password must include at least one lowercase letter";
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must include at least one number";
    }
    
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = "Password must include at least one special character";
    }
    
    return empty($errors) ? true : $errors;
}

// Input Sanitization
function sanitizeInput($data, $type = 'string') {
    switch ($type) {
        case 'email':
            $data = filter_var($data, FILTER_SANITIZE_EMAIL);
            break;
        case 'url':
            $data = filter_var($data, FILTER_SANITIZE_URL);
            break;
        case 'int':
            $data = filter_var($data, FILTER_SANITIZE_NUMBER_INT);
            break;
        case 'float':
            $data = filter_var($data, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            break;
        default:
            $data = htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }
    return $data;
}

// Activity Monitoring
function monitorActivity($user_id, $action) {
    $db = getDB();
    $ip = $_SERVER['REMOTE_ADDR'];
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    
    $stmt = $db->prepare("INSERT INTO activity_log (user_id, action, ip_address, user_agent) VALUES (?, ?, ?, ?)");
    $stmt->execute([$user_id, $action, $ip, $user_agent]);
    
    // Check for suspicious activity
    $stmt = $db->prepare("
        SELECT COUNT(*) 
        FROM activity_log 
        WHERE user_id = ? 
        AND timestamp > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    ");
    $stmt->execute([$user_id]);
    $recent_activities = $stmt->fetchColumn();
    
    if ($recent_activities > 50) { // Threshold for suspicious activity
        logActivity($user_id, 'security_warning', 'Suspicious activity detected: High frequency of actions');
        return false;
    }
    
    return true;
}

// File Upload Security
function validateFileUpload($file, $allowed_types = ['jpg', 'jpeg', 'png', 'pdf'], $max_size = 5242880) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return "Upload failed with error code: " . $file['error'];
    }
    
    if ($file['size'] > $max_size) {
        return "File is too large. Maximum size is " . ($max_size / 1024 / 1024) . "MB";
    }
    
    $file_info = pathinfo($file['name']);
    $extension = strtolower($file_info['extension']);
    
    if (!in_array($extension, $allowed_types)) {
        return "Invalid file type. Allowed types: " . implode(', ', $allowed_types);
    }
    
    // Verify MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    $allowed_mimes = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'pdf' => 'application/pdf'
    ];
    
    if (!in_array($mime_type, array_values($allowed_mimes))) {
        return "Invalid file type detected";
    }
    
    return true;
}

// Initialize security measures
checkIPSecurity();
generateCSRFToken(); 