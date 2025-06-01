<?php
// Prevent running setup if already configured
if (file_exists('setup_complete.txt')) {
    die("Setup has already been completed. For security reasons, please remove setup.php file.");
}

// Function to check database connection
function checkDatabaseConnection($host, $user, $pass) {
    try {
        new PDO("mysql:host=$host", $user, $pass);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

// Function to create database and tables
function setupDatabase($host, $user, $pass) {
    try {
        // Read and execute SQL file
        $sql = file_get_contents('setup_database.sql');
        if ($sql === false) {
            throw new Exception("Could not read setup_database.sql");
        }

        $pdo = new PDO("mysql:host=$host", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Execute each statement separately
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        foreach ($statements as $statement) {
            if (!empty($statement)) {
                $pdo->exec($statement);
            }
        }

        return true;
    } catch (Exception $e) {
        error_log("Setup error: " . $e->getMessage());
        return false;
    }
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate inputs
    $host = $_POST['db_host'] ?? 'localhost';
    $user = $_POST['db_user'] ?? '';
    $pass = $_POST['db_pass'] ?? '';

    if (empty($user)) {
        $error = "Database username is required.";
    } else {
        // Test connection
        if (!checkDatabaseConnection($host, $user, $pass)) {
            $error = "Could not connect to database. Please check your credentials.";
        } else {
            // Setup database
            if (setupDatabase($host, $user, $pass)) {
                // Create config file
                $config = "<?php
define('DB_HOST', " . var_export($host, true) . ");
define('DB_NAME', 'school_grading');
define('DB_USER', " . var_export($user, true) . ");
define('DB_PASS', " . var_export($pass, true) . ");
define('DB_CHARSET', 'utf8mb4');

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', isset(\$_SERVER['HTTPS']));

function getDB() {
    static \$db = null;
    
    if (\$db === null) {
        try {
            \$dsn = \"mysql:host=\" . DB_HOST . \";dbname=\" . DB_NAME . \";charset=\" . DB_CHARSET;
            \$options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            \$db = new PDO(\$dsn, DB_USER, DB_PASS, \$options);
        } catch (PDOException \$e) {
            error_log(\"Database connection failed: \" . \$e->getMessage());
            throw new PDOException(\"Connection failed. Please try again later.\");
        }
    }
    
    return \$db;
}

function sanitize(\$text) {
    return htmlspecialchars(\$text, ENT_QUOTES, 'UTF-8');
}

function generateCSRFToken() {
    if (empty(\$_SESSION['csrf_token'])) {
        \$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return \$_SESSION['csrf_token'];
}

function verifyCSRFToken(\$token) {
    return isset(\$_SESSION['csrf_token']) && hash_equals(\$_SESSION['csrf_token'], \$token);
}

function isLoggedIn() {
    return isset(\$_SESSION['user_id']);
}

function hasRole(\$role) {
    return isset(\$_SESSION['role']) && \$_SESSION['role'] === \$role;
}

function redirectWithError(\$message, \$location = 'index.php') {
    \$_SESSION['error'] = \$message;
    header(\"Location: \$location\");
    exit();
}

function redirectWithSuccess(\$message, \$location = 'index.php') {
    \$_SESSION['success'] = \$message;
    header(\"Location: \$location\");
    exit();
}";

                // Write config file
                if (file_put_contents('config.php', $config) === false) {
                    $error = "Could not write config file.";
                } else {
                    // Create setup complete file
                    file_put_contents('setup_complete.txt', date('Y-m-d H:i:s'));
                    $success = "Setup completed successfully! Default admin credentials:<br>Username: admin<br>Password: admin123<br><br>Please delete setup.php for security.";
                }
            } else {
                $error = "Could not set up database. Check error logs for details.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>School Grading System Setup</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background: #f5f7fa;
        }
        .container {
            max-width: 600px;
            margin: 40px auto;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2c3e50;
            margin-bottom: 20px;
            text-align: center;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            color: #2c3e50;
            font-weight: 600;
        }
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .btn {
            background: #3498db;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            width: 100%;
            font-size: 16px;
        }
        .btn:hover {
            background: #2980b9;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>School Grading System Setup</h1>
        
        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php else: ?>
            <form method="post">
                <div class="form-group">
                    <label for="db_host">Database Host:</label>
                    <input type="text" id="db_host" name="db_host" value="localhost" required>
                </div>
                
                <div class="form-group">
                    <label for="db_user">Database Username:</label>
                    <input type="text" id="db_user" name="db_user" required>
                </div>
                
                <div class="form-group">
                    <label for="db_pass">Database Password:</label>
                    <input type="password" id="db_pass" name="db_pass">
                </div>
                
                <button type="submit" class="btn">Run Setup</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html> 