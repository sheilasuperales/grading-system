<?php
session_start();
require_once 'config.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Clear any existing session data
session_unset();
session_destroy();
session_start();

// Debug log for session and server info
error_log("Session data at start: " . print_r($_SESSION, true));
error_log("Server variables: " . print_r($_SERVER, true));

// Get the base URL for the application
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$base_url = $protocol . $_SERVER['HTTP_HOST'];
error_log("Base URL: " . $base_url);

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$debug_info = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = getDB();
        
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        error_log("Login attempt - Username: " . $username);
        error_log("Password length: " . strlen($password));

        if (empty($username) || empty($password)) {
            throw new Exception("Please enter both username and password.");
        }

        // Check if login is blocked
        if (isLoginBlocked($username)) {
            throw new Exception("Too many failed login attempts. Please try again later.");
        }

        // First check user_accounts table
        $stmt = $db->prepare("
            SELECT ua.*, sa.first_name, sa.last_name
            FROM user_accounts ua
            LEFT JOIN super_admins sa ON ua.id = sa.user_id
            WHERE ua.username = ? AND ua.is_active = 1
        ");
        
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Debug log
        if ($user) {
            $debug_row = $user;
            $debug_row['password'] = (strlen($debug_row['password']) > 0) ? '***' : 'empty';
            error_log("Login query returned user row (masked): " . print_r($debug_row, true));
            
            // Test password verification
            $password_verify_result = password_verify($password, $user['password']);
            error_log("Password verification result: " . ($password_verify_result ? 'true' : 'false'));
            error_log("Stored password hash: " . $user['password']);
            error_log("Input password length: " . strlen($password));
        } else {
            error_log("Login query did not return a row for username: " . $username);
        }

        if ($user && password_verify($password, $user['password'])) {
            // Successful login
            resetLoginAttempts($username);
            logActivity($user['id'], 'login', 'Successful login');
            
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['fullname'] = ($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '');
            
            // Update last login
            $stmt = $db->prepare("UPDATE user_accounts SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$user['id']]);

            // Debug log successful login
            error_log("Successful login for user: " . $username . " with role: " . $user['role']);
            error_log("Session data after login: " . print_r($_SESSION, true));

            // Redirect based on role
            switch ($user['role']) {
                case 'super_admin':
                    header('Location: super_admin_dashboard.php');
                    break;
                case 'admin':
                    header('Location: admin_dashboard.php');
                    break;
                case 'instructor':
                    header('Location: instructor_dashboard.php');
                    break;
                case 'student':
                    header('Location: student_dashboard.php');
                    break;
                default:
                    throw new Exception("Invalid user role.");
            }
            exit();
        } else {
            incrementLoginAttempts($username);
            logActivity(null, 'login_failed', 'Failed login for username: ' . $username);
            throw new Exception("Invalid username or password.");
        }

    } catch (Exception $e) {
        $error = $e->getMessage();
        error_log("Login error: " . $e->getMessage());
    }
}

// Debug information
if (isset($user)) {
    $debug_info = "User Role: " . ($user['role'] ?? 'Not set') . "\n";
    $debug_info .= "Session Role: " . ($_SESSION['role'] ?? 'Not set') . "\n";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .login-container {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
        }
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .login-header h1 {
            color: #2c3e50;
            margin: 0;
            font-size: 1.8rem;
        }
        .form-group {
            margin-bottom: 1rem;
        }
        label {
            display: block;
            margin-bottom: 0.5rem;
            color: #34495e;
        }
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
            box-sizing: border-box;
        }
        input[type="text"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
        }
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 0.75rem;
            border-radius: 4px;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }
        .submit-btn {
            width: 100%;
            padding: 0.75rem;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 1rem;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .submit-btn:hover {
            background: #2980b9;
        }
        .submit-btn:active {
            transform: translateY(1px);
        }
        .forgot-password {
            text-align: center;
            margin-top: 1rem;
        }
        .forgot-password a {
            color: #7f8c8d;
            text-decoration: none;
            font-size: 0.9rem;
        }
        .forgot-password a:hover {
            color: #34495e;
        }
        .password-container {
            position: relative;
        }
        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #666;
        }
        .password-toggle:hover {
            color: #3498db;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1><?php echo APP_NAME; ?></h1>
        </div>
        
        <?php if ($error): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required 
                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <div class="password-container">
                    <input type="password" id="password" name="password" required>
                    <button type="button" class="password-toggle" onclick="togglePassword()">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>
            
            <button type="submit" class="submit-btn">Login</button>
        </form>
        
        <div class="forgot-password">
            <a href="forgot_password.php">Forgot Password?</a>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleButton = document.querySelector('.password-toggle i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleButton.classList.remove('fa-eye');
                toggleButton.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleButton.classList.remove('fa-eye-slash');
                toggleButton.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html> 