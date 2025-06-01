<?php
session_start();
require_once 'config.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$success_message = '';
$error_message = '';

// Handle instructor registration
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $fullname = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if (empty($username) || empty($password)) {
        $error_message = "Username and password are required.";
    } else {
        $db = getDB();
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        
        try {
            $stmt = $db->prepare("INSERT INTO users (username, password, role, fullname, email) VALUES (:username, :password, 'instructor', :fullname, :email)");
            $stmt->execute([
                ':username' => $username,
                ':password' => $hashed,
                ':fullname' => $fullname,
                ':email' => $email
            ]);
            $success_message = "Instructor registered successfully!";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate') !== false) {
                $error_message = "Username already taken";
            } else {
                $error_message = "Registration failed: " . $e->getMessage();
            }
        }
    }
}
// Get list of instructors
function getInstructors() {
    $db = getDB();
    $stmt = $db->prepare("SELECT id, username, fullname, email FROM users WHERE role = 'instructor' ORDER BY username");
    return $stmt->execute() ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
}

$instructors = getInstructors();

require_once 'header.php';
?>

<div style="padding: 20px; max-width: 1200px; margin: 0 auto;">
    <h1>Admin Dashboard - Instructor Management</h1>

    <!-- Register New Instructor Form -->
    <div style="background: white; padding: 20px; border-radius: 8px; margin-bottom: 30px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <h2>Register New Instructor</h2>
        
        <?php if ($success_message): ?>
            <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <form method="post" style="max-width: 500px;">
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: bold;">Username:</label>
                <input type="text" name="username" required 
                       style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            </div>

            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: bold;">Password:</label>
                <input type="password" name="password" required 
                       style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            </div>

            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: bold;">Full Name:</label>
                <input type="text" name="fullname" 
                       style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            </div>

            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: bold;">Email:</label>
                <input type="email" name="email" 
                       style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            </div>

            <button type="submit" 
                    style="background: #4a90e2; color: white; padding: 10px 20px; border: none; 
                           border-radius: 4px; cursor: pointer; font-size: 16px;">
                Register Instructor
            </button>
        </form>
    </div>

    <!-- List of Instructors -->
    <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <h2>Current Instructors</h2>
        <?php if (empty($instructors)): ?>
            <p>No instructors registered yet.</p>
        <?php else: ?>
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f8f9fa;">
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6;">Username</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6;">Full Name</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6;">Email</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($instructors as $instructor): ?>
                        <tr>
                            <td style="padding: 12px; border-bottom: 1px solid #dee2e6;">
                                <?php echo htmlspecialchars($instructor['username']); ?>
                            </td>
                            <td style="padding: 12px; border-bottom: 1px solid #dee2e6;">
                                <?php echo htmlspecialchars($instructor['fullname']); ?>
                            </td>
                            <td style="padding: 12px; border-bottom: 1px solid #dee2e6;">
                                <?php echo htmlspecialchars($instructor['email']); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

</body>
</html>

