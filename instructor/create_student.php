<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is instructor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    header('Location: ../login.php?msg=unauthorized');
    exit();
}

function createStudent($username, $password, $fullname, $email, $course, $section, $year_level) {
    $db = getDB();
    $hashed = password_hash($password, PASSWORD_DEFAULT);
    
    try {
        $stmt = $db->prepare("INSERT INTO users (username, password, role, fullname, email, course, section, year_level) 
                             VALUES (:username, :password, 'student', :fullname, :email, :course, :section, :year_level)");
        $result = $stmt->execute([
            ':username' => $username,
            ':password' => $hashed,
            ':fullname' => $fullname,
            ':email' => $email,
            ':course' => $course,
            ':section' => $section,
            ':year_level' => $year_level
        ]);

        if (!$result) {
            return "Failed to create student account";
        }

        $studentId = $db->lastInsertId();
        logActivity($_SESSION['user_id'], 'create_student', "Created student account for: {$username}");
        return true;
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate') !== false) {
            return "Username or email already exists";
        }
        return "Error creating student: " . $e->getMessage();
    }
}

// Get instructor's department
$db = getDB();
$stmt = $db->prepare("SELECT department FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$instructor_dept = $stmt->fetchColumn();

// Get courses for this department
$stmt = $db->prepare("SELECT code, name FROM departments WHERE code = ?");
$stmt->execute([$instructor_dept]);
$department = $stmt->fetch();

$errors = [];
$messages = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';
    $fullname = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $course = trim($_POST['course'] ?? '');
    $section = trim($_POST['section'] ?? '');
    $year_level = trim($_POST['year_level'] ?? '');

    if (empty($username) || empty($password) || empty($password2) || empty($fullname) || 
        empty($email) || empty($course) || empty($section) || empty($year_level)) {
        $errors[] = "All fields are required.";
    } elseif ($password !== $password2) {
        $errors[] = "Passwords do not match.";
    } elseif (!validatePassword($password)) {
        $errors[] = "Password must be at least 8 characters long and include uppercase, lowercase, number, and special character.";
    } elseif ($course !== $instructor_dept) {
        $errors[] = "You can only create students for your department.";
    } else {
        $result = createStudent($username, $password, $fullname, $email, $course, $section, $year_level);
        if ($result === true) {
            $messages[] = "Student account created successfully!";
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
    <title>Create Student Account - <?php echo APP_NAME; ?></title>
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
        .department-info {
            background: #e8f4f8;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="nav">
            <a href="index.php">Dashboard</a>
            <a href="manage_students.php">Manage Students</a>
            <a href="../logout.php">Logout</a>
        </div>
        
        <h1>Create Student Account</h1>
        
        <div class="department-info">
            <strong>Department:</strong> <?php echo htmlspecialchars($department['name']); ?>
        </div>

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

            <input type="hidden" name="course" value="<?php echo htmlspecialchars($instructor_dept); ?>">

            <div class="form-group">
                <label for="year_level">Year Level:</label>
                <select id="year_level" name="year_level" required>
                    <option value="">Select Year Level</option>
                    <option value="1" <?php echo (isset($_POST['year_level']) && $_POST['year_level'] === '1') ? 'selected' : ''; ?>>1st Year</option>
                    <option value="2" <?php echo (isset($_POST['year_level']) && $_POST['year_level'] === '2') ? 'selected' : ''; ?>>2nd Year</option>
                    <option value="3" <?php echo (isset($_POST['year_level']) && $_POST['year_level'] === '3') ? 'selected' : ''; ?>>3rd Year</option>
                    <option value="4" <?php echo (isset($_POST['year_level']) && $_POST['year_level'] === '4') ? 'selected' : ''; ?>>4th Year</option>
                </select>
            </div>

            <div class="form-group">
                <label for="section">Section:</label>
                <input type="text" id="section" name="section" placeholder="e.g., A, B, C" value="<?php echo isset($_POST['section']) ? htmlspecialchars($_POST['section']) : ''; ?>" required>
            </div>

            <button type="submit">Create Student Account</button>
        </form>
    </div>
</body>
</html> 