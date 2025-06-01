<?php
session_start();
require_once './config.php';
require_once './header.php';

// Debug message to confirm the registration page is loaded
echo "<!-- Registration page loaded successfully -->";

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: ./dashboard.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        error_log("=== Starting Registration Process ===");
        $db = getDB();
        if (!$db) {
            throw new Exception("Database connection failed");
        }
        
        // Get and sanitize form data
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $email = trim($_POST['email'] ?? '');
        $firstname = trim($_POST['firstname'] ?? '');
        $lastname = trim($_POST['lastname'] ?? '');
        $middlename = trim($_POST['middlename'] ?? '');
        $suffix = trim($_POST['suffix'] ?? '');
        $year_level = trim($_POST['year_level'] ?? '');
        $course = trim($_POST['course'] ?? '');
        $section = trim($_POST['section'] ?? '');

        // Validation
        if (empty($username) || empty($password) || empty($confirm_password) || empty($email) || 
            empty($firstname) || empty($lastname) || empty($year_level) || empty($course) || empty($section)) {
            throw new Exception("Please fill in all required fields.");
        }

        if ($password !== $confirm_password) {
            throw new Exception("Passwords do not match.");
        }

        if (strlen($password) < 8) {
            throw new Exception("Password must be at least 8 characters long.");
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Please enter a valid email address.");
        }

        // Check for duplicates
        $stmt = $db->prepare("SELECT COUNT(*) FROM user_accounts WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("Username or email already exists.");
        }

        // Begin transaction
        $db->beginTransaction();

        try {
            // First insert into user_accounts
            $stmt = $db->prepare("
                INSERT INTO user_accounts (
                    username, password, email, role, is_active
                ) VALUES (
                    :username, :password, :email, 'student', TRUE
                )
            ");

            $stmt->execute([
                ':username' => $username,
                ':password' => password_hash($password, PASSWORD_DEFAULT),
                ':email' => $email
            ]);

            $user_id = $db->lastInsertId();

            // Then insert into students table
            $stmt = $db->prepare("
                INSERT INTO students (
                    user_id, student_id, first_name, last_name, middle_name, suffix,
                    gender, date_of_birth, address, contact_number, year_level, course, section
                ) VALUES (
                    :user_id, :student_id, :first_name, :last_name, :middle_name, :suffix,
                    :gender, :date_of_birth, :address, :contact_number, :year_level, :course, :section
                )
            ");

            $stmt->execute([
                ':user_id' => $user_id,
                ':student_id' => 'STU' . $user_id,
                ':first_name' => $firstname,
                ':last_name' => $lastname,
                ':middle_name' => $middlename,
                ':suffix' => $suffix,
                ':gender' => $_POST['gender'] ?? 'Other',
                ':date_of_birth' => $_POST['date_of_birth'] ?? date('Y-m-d'),
                ':address' => $_POST['address'] ?? '',
                ':contact_number' => $_POST['contact_number'] ?? '',
                ':year_level' => $year_level,
                ':course' => $course,
                ':section' => $section
            ]);

            // --- AUTO ENROLL TO COURSE ---
            // Map BSIT/BSCS to IT/CS
            $course_code = $course;
            if ($course === 'BSIT') $course_code = 'IT';
            if ($course === 'BSCS') $course_code = 'CS';
            // Find course_id from courses table
            $course_id = null;
            $stmt = $db->prepare("SELECT id FROM courses WHERE course_code = ? OR course_name = ? LIMIT 1");
            $stmt->execute([$course_code, $course_code]);
            $course_row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($course_row) {
                $course_id = $course_row['id'];
                // Insert into enrollments
                $stmt = $db->prepare("INSERT INTO enrollments (student_id, course_id, enrolled_at) VALUES (?, ?, NOW())");
                $stmt->execute([$user_id, $course_id]);
            } else {
                error_log("No matching course found for auto-enroll: $course_code");
            }
            // --- END AUTO ENROLL ---

            $db->commit();
            error_log("Registration successful for student: " . $username);
            $success = "Registration successful! You can now login.";
            
            // Clear form data after successful registration
            $_POST = array();
            
        } catch (PDOException $e) {
            $db->rollBack();
            $error = "Registration failed: " . $e->getMessage();
            error_log("Registration error: " . $e->getMessage());
        }

    } catch (Exception $e) {
        error_log("Registration error: " . $e->getMessage());
        $error = $e->getMessage();
    }
    error_log("=== End Registration Process ===");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration - School Grading System</title>
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
        .container {
            max-width: 800px;
            margin: 40px auto;
            padding: 20px;
        }
        .register-container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        h2 {
            color: #2c3e50;
            margin-top: 0;
            text-align: center;
            margin-bottom: 30px;
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
        .form-group input, .form-group select {
            width: 100%;
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        .form-group input:focus, .form-group select:focus {
            border-color: #3498db;
            outline: none;
        }
        .form-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .success {
            background: #d4edda;
            color: #155724;
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
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="register-container">
            <h2>Student Registration</h2>
            
            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                <div class="form-row">
                    <div class="form-group">
                        <label for="username">Username *</label>
                        <input type="text" id="username" name="username" required 
                               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address *</label>
                        <input type="email" id="email" name="email" required 
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="password">Password *</label>
                        <div class="password-container">
                            <input type="password" id="password" name="password" required>
                            <button type="button" class="password-toggle" onclick="togglePassword('password')">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password *</label>
                        <div class="password-container">
                            <input type="password" id="confirm_password" name="confirm_password" required>
                            <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="firstname">First Name *</label>
                        <input type="text" id="firstname" name="firstname" required 
                               value="<?php echo htmlspecialchars($_POST['firstname'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="lastname">Last Name *</label>
                        <input type="text" id="lastname" name="lastname" required 
                               value="<?php echo htmlspecialchars($_POST['lastname'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="middlename">Middle Name</label>
                        <input type="text" id="middlename" name="middlename" 
                               value="<?php echo htmlspecialchars($_POST['middlename'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="suffix">Suffix</label>
                        <input type="text" id="suffix" name="suffix" placeholder="e.g., Jr., Sr., III" 
                               value="<?php echo htmlspecialchars($_POST['suffix'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="year_level">Year Level *</label>
                        <select id="year_level" name="year_level" required>
                            <option value="">Select Year Level</option>
                            <option value="1st Year" <?php echo (isset($_POST['year_level']) && $_POST['year_level'] === '1st Year') ? 'selected' : ''; ?>>1st Year</option>
                            <option value="2nd Year" <?php echo (isset($_POST['year_level']) && $_POST['year_level'] === '2nd Year') ? 'selected' : ''; ?>>2nd Year</option>
                            <option value="3rd Year" <?php echo (isset($_POST['year_level']) && $_POST['year_level'] === '3rd Year') ? 'selected' : ''; ?>>3rd Year</option>
                            <option value="4th Year" <?php echo (isset($_POST['year_level']) && $_POST['year_level'] === '4th Year') ? 'selected' : ''; ?>>4th Year</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="course">Course *</label>
                        <select id="course" name="course" required>
                            <option value="">Select Course</option>
                            <option value="BSCS" <?php echo (isset($_POST['course']) && $_POST['course'] === 'BSCS') ? 'selected' : ''; ?>>Bachelor of Science in Computer Science</option>
                            <option value="BSIT" <?php echo (isset($_POST['course']) && $_POST['course'] === 'BSIT') ? 'selected' : ''; ?>>Bachelor of Science in Information Technology</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="section">Section *</label>
                    <input type="text" id="section" name="section" required 
                           value="<?php echo htmlspecialchars($_POST['section'] ?? ''); ?>">
                </div>

                <button type="submit" class="btn">Register</button>
            </form>

            <div class="login-link">
                Already have an account? <a href="index.php">Login here</a>
            </div>
        </div>
    </div>

    <script>
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = input.nextElementSibling.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'fa-solid fa-eye-slash';
            } else {
                input.type = 'password';
                icon.className = 'fa-solid fa-eye';
            }
        }
    </script>
</body>
</html>
