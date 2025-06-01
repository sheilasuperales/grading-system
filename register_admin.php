<?php
session_start();
require_once 'config.php';
require_once 'header.php';

// Check if user is logged in and is a super admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: index.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = getDB();
        
        // Get and sanitize form data
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $email = trim($_POST['email'] ?? '');
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $middle_name = trim($_POST['middle_name'] ?? '');
        $suffix = trim($_POST['suffix'] ?? '');
        $department = trim($_POST['department'] ?? '');

        // Validation
        if (empty($username) || empty($password) || empty($confirm_password) || empty($email) || 
            empty($first_name) || empty($last_name) || empty($department)) {
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
                    :username, :password, :email, 'admin', TRUE
                )
            ");

            $stmt->execute([
                ':username' => $username,
                ':password' => password_hash($password, PASSWORD_DEFAULT),
                ':email' => $email
            ]);

            $user_id = $db->lastInsertId();

            // Then insert into admins table
            $stmt = $db->prepare("
                INSERT INTO admins (
                    user_id, admin_id, first_name, last_name, middle_name, suffix,
                    gender, date_of_birth, address, contact_number, department, position
                ) VALUES (
                    :user_id, :admin_id, :first_name, :last_name, :middle_name, :suffix,
                    :gender, :date_of_birth, :address, :contact_number, :department, :position
                )
            ");

            $stmt->execute([
                ':user_id' => $user_id,
                ':admin_id' => 'ADM' . $user_id,
                ':first_name' => $first_name,
                ':last_name' => $last_name,
                ':middle_name' => $middle_name,
                ':suffix' => $suffix,
                ':gender' => $_POST['gender'] ?? 'Other',
                ':date_of_birth' => $_POST['date_of_birth'] ?? date('Y-m-d'),
                ':address' => $_POST['address'] ?? '',
                ':contact_number' => $_POST['contact_number'] ?? '',
                ':department' => $department,
                ':position' => $_POST['position'] ?? 'Administrator'
            ]);

            $db->commit();
            $success = "Admin registered successfully!";
            $_POST = array(); // Clear form data
            
        } catch (PDOException $e) {
            $db->rollBack();
            throw new Exception("Registration failed. Please try again.");
        }

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Admin - School Grading System</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <div class="form-container">
            <h2>Register New Admin</h2>
            
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
                        <input type="password" id="password" name="password" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password *</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name">First Name *</label>
                        <input type="text" id="first_name" name="first_name" required 
                               value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="last_name">Last Name *</label>
                        <input type="text" id="last_name" name="last_name" required 
                               value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="middle_name">Middle Name</label>
                        <input type="text" id="middle_name" name="middle_name" 
                               value="<?php echo htmlspecialchars($_POST['middle_name'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="suffix">Suffix</label>
                        <input type="text" id="suffix" name="suffix" placeholder="e.g., Jr., Sr., III" 
                               value="<?php echo htmlspecialchars($_POST['suffix'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="department">Department *</label>
                    <select id="department" name="department" required>
                        <option value="">Select Department</option>
                        <option value="CS" <?php echo (isset($_POST['department']) && $_POST['department'] === 'CS') ? 'selected' : ''; ?>>Computer Science</option>
                        <option value="IT" <?php echo (isset($_POST['department']) && $_POST['department'] === 'IT') ? 'selected' : ''; ?>>Information Technology</option>
                    </select>
                </div>

                <button type="submit" class="btn">Register Admin</button>
            </form>

            <div class="back-link">
                <a href="super_admin_dashboard.php">Back to Dashboard</a>
            </div>
        </div>
    </div>
</body>
</html> 