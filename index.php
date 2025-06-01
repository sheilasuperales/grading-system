<?php
session_start();
require_once 'config.php';

// If user is already logged in, redirect to appropriate dashboard
if (isset($_SESSION['user_id'])) {
    switch ($_SESSION['role']) {
        case 'super_admin':
            header("Location: super_admin_dashboard.php");
            break;
        case 'admin':
            header("Location: admin_dashboard.php");
            break;
        case 'instructor':
            header("Location: instructor_dashboard.php");
            break;
        case 'student':
            header("Location: student_dashboard.php");
            break;
        default:
            header("Location: dashboard.php");
            break;
    }
    exit();
}

$error = '';
$username = '';

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password.";
    } else {
        try {
            $db = getDB();
            error_log("Login attempt - Username: $username");
            $stmt = $db->prepare("
                SELECT ua.*, sa.first_name, sa.last_name
                FROM user_accounts ua
                LEFT JOIN super_admins sa ON ua.id = sa.user_id
                WHERE ua.username = ? AND ua.is_active = 1
            ");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            error_log("User row: " . print_r($user, true));

            if ($user) {
                $verify = password_verify($password, $user['password']);
                error_log("Password verify result: " . ($verify ? 'true' : 'false'));
                error_log("Input password: $password");
                error_log("Stored hash: " . $user['password']);

                if ($verify) {
                    // Update last login time
                    $updateStmt = $db->prepare("UPDATE user_accounts SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
                    $updateStmt->execute([$user['id']]);

                    // Set session variables
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['fullname'] = ($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '');

                    // Redirect based on role
                    switch ($user['role']) {
                        case 'super_admin':
                            header("Location: super_admin_dashboard.php");
                            break;
                        case 'admin':
                            header("Location: admin_dashboard.php");
                            break;
                        case 'instructor':
                            header("Location: instructor_dashboard.php");
                            break;
                        case 'student':
                            header("Location: student_dashboard.php");
                            break;
                        default:
                            header("Location: dashboard.php");
                            break;
                    }
                    exit();
                } else {
                    $error = "Invalid username or password.";
                }
            } else {
                $error = "Invalid username or password.";
            }
        } catch (PDOException $e) {
            $error = "Login failed. Please try again.";
            error_log("Login error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>School Grading System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background: #f5f7fa;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .hero {
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            color: white;
            padding: 60px 20px;
            text-align: center;
        }
        .hero h1 {
            font-size: 2.5em;
            margin: 0 0 20px 0;
        }
        .hero p {
            font-size: 1.2em;
            max-width: 600px;
            margin: 0 auto;
            opacity: 0.9;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            align-items: start;
        }
        .login-container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .features {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .features h2 {
            color: #2c3e50;
            margin-top: 0;
        }
        .feature-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .feature-list li {
            margin-bottom: 15px;
            padding-left: 25px;
            position: relative;
        }
        .feature-list li:before {
            content: "✓";
            color: #2ecc71;
            position: absolute;
            left: 0;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #2c3e50;
            font-weight: 600;
        }
        .form-group input {
            width: 100%;
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        .form-group input:focus {
            border-color: #3498db;
            outline: none;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .btn {
            background: #3498db;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            width: 100%;
            transition: background 0.3s;
        }
        .btn:hover {
            background: #2980b9;
        }
        .login-link {
            text-align: center;
            margin-top: 20px;
        }
        .login-link a {
            color: #3498db;
            text-decoration: none;
        }
        .login-link a:hover {
            text-decoration: underline;
        }
        footer {
            background: #2c3e50;
            color: white;
            text-align: center;
            padding: 20px;
            margin-top: auto;
        }
        @media (max-width: 768px) {
            .container {
                grid-template-columns: 1fr;
            }
            .features {
                order: 2;
            }
            .login-container {
                order: 1;
            }
        }
        .password-container {
            position: relative;
            display: flex;
            align-items: center;
        }
        .password-container input {
            width: 100%;
            padding-right: 45px;
        }
        .password-toggle {
            position: absolute;
            right: 10px;
            background: none;
            border: none;
            cursor: pointer;
            padding: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #666;
            transition: all 0.3s ease;
            z-index: 2;
            border-radius: 50%;
        }
        .password-toggle:hover {
            color: #3498db;
            background-color: rgba(52, 152, 219, 0.1);
        }
        .password-toggle:focus {
            outline: none;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.3);
        }
        .password-toggle i {
            font-size: 1.2rem;
        }
    </style>
</head>
<body>
    <div class="hero">
        <h1>School Grading System</h1>
        <p>A comprehensive platform for managing courses, assignments, and grades with role-based access for administrators, instructors, and students.</p>
    </div>

    <div class="container">
        <div class="login-container">
            <h2>Login</h2>
            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required value="<?php echo htmlspecialchars($username ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-container">
                        <input type="password" id="password" name="password" required>
                        <button type="button" class="password-toggle" onclick="togglePassword()" title="Show password">
                            <i class="fa-solid fa-eye" id="toggleIcon"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn">Log In</button>
            </form>

            <div class="login-link">
                Don't have an account? <a href="register.php" class="register-link">Register here</a>
            </div>
        </div>

        <div class="features">
            <h2>System Features</h2>
            <ul class="feature-list">
                <li>Secure role-based access control</li>
                <li>Course management and enrollment</li>
                <li>Assignment creation and grading</li>
                <li>Student progress tracking</li>
                <li>Grade calculation and reporting</li>
                <li>Instructor course management</li>
                <li>Administrative user management</li>
                <li>Real-time grade updates</li>
            </ul>
        </div>
    </div>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> School Grading System. All rights reserved.</p>
    </footer>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            const button = toggleIcon.parentElement;
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                button.title = 'Hide password';
                toggleIcon.className = 'fa-solid fa-eye-slash';
            } else {
                passwordInput.type = 'password';
                button.title = 'Show password';
                toggleIcon.className = 'fa-solid fa-eye';
            }
        }

        // Add keyboard support for accessibility
        document.querySelector('.password-toggle').addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                togglePassword();
            }
        });
    </script>
</body>
</html>

