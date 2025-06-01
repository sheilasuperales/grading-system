<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php?msg=unauthorized');
    exit();
}

function createInstructor($username, $password, $fullname, $email, $department) {
    $db = getDB();
    $hashed = password_hash($password, PASSWORD_DEFAULT);
    
    try {
        $stmt = $db->prepare("INSERT INTO users (username, password, role, fullname, email, department) 
                             VALUES (:username, :password, 'instructor', :fullname, :email, :department)");
        $result = $stmt->execute([
            ':username' => $username,
            ':password' => $hashed,
            ':fullname' => $fullname,
            ':email' => $email,
            ':department' => $department
        ]);

        if (!$result) {
            return "Failed to create instructor account";
        }

        $instructorId = $db->lastInsertId();
        logActivity($_SESSION['user_id'], 'create_instructor', "Created instructor account for: {$username}");
        return true;
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate') !== false) {
            return "Username or email already exists";
        }
        return "Error creating instructor: " . $e->getMessage();
    }
}

$departments = [
    'CS' => 'Computer Science',
    'IT' => 'Information Technology',
    'IS' => 'Information Systems',
    'EMC' => 'Entertainment and Multimedia Computing'
];

$errors = [];
$messages = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';
    $fullname = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $department = trim($_POST['department'] ?? '');

    if (empty($username) || empty($password) || empty($password2) || empty($fullname) || empty($email) || empty($department)) {
        $errors[] = "All fields are required.";
    } elseif ($password !== $password2) {
        $errors[] = "Passwords do not match.";
    } elseif (!validatePassword($password)) {
        $errors[] = "Password must be at least 8 characters long and include uppercase, lowercase, number, and special character.";
    } else {
        $result = createInstructor($username, $password, $fullname, $email, $department);
        if ($result === true) {
            $messages[] = "Instructor account created successfully!";
        } else {
            $errors[] = $result;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create Instructor Account - <?php echo APP_NAME; ?></title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            color: #333;
            padding: 30px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 25px 35px;
            border-radius: 12px;
            box-shadow: 0 6px 15px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2c3e50;
            margin-bottom: 30px;
            text-align: center;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }
        input, select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 16px;
            box-sizing: border-box;
        }
        button {
            background: #3498db;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
        }
        button:hover {
            background: #2980b9;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        .nav {
            margin-bottom: 30px;
            text-align: right;
        }
        .nav a {
            color: #3498db;
            text-decoration: none;
            margin-left: 20px;
        }
        .nav a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="nav">
            <a href="index.php">Dashboard</a>
            <a href="manage_instructors.php">Manage Instructors</a>
            <a href="../logout.php">Logout</a>
        </div>
        
        <h1>Create Instructor Account</h1>
        
        <?php if (!empty($errors)): ?>
            <div class="error">
                <?php foreach ($errors as $error): ?>
                    <div><?php echo htmlspecialchars($error); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($messages)): ?>
            <div class="success">
                <?php foreach ($messages as $message): ?>
                    <div><?php echo htmlspecialchars($message); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" novalidate>
            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
            </div>

            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>

            <div class="form-group">
                <label for="password2">Confirm Password:</label>
                <input type="password" id="password2" name="password2" required>
            </div>

            <div class="form-group">
                <label for="fullname">Full Name:</label>
                <input type="text" id="fullname" name="fullname" value="<?php echo isset($_POST['fullname']) ? htmlspecialchars($_POST['fullname']) : ''; ?>" required>
            </div>

            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
            </div>

            <div class="form-group">
                <label for="department">Department:</label>
                <select id="department" name="department" required>
                    <option value="">Select Department</option>
                    <?php foreach ($departments as $code => $name): ?>
                        <option value="<?php echo htmlspecialchars($code); ?>" <?php echo (isset($_POST['department']) && $_POST['department'] === $code) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit">Create Instructor Account</button>
        </form>
    </div>
</body>
</html> 