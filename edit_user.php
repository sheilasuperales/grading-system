<?php
session_start();
require_once 'config.php';

// Only allow super_admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: index.php');
    exit();
}

$db = getDB();
$error = '';
$success = '';

// Get user ID from query
$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$user_id) {
    header('Location: super_admin_dashboard.php');
    exit();
}

// Fetch user info
$stmt = $db->prepare('SELECT * FROM user_accounts WHERE id = ?');
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    $error = 'User not found.';
} else {
    $role = $user['role'];
    // Fetch profile info
    $profile = [];
    if ($role === 'student') {
        $stmt = $db->prepare('SELECT * FROM students WHERE user_id = ?');
        $stmt->execute([$user_id]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);
    } elseif ($role === 'instructor') {
        $stmt = $db->prepare('SELECT * FROM instructors WHERE user_id = ?');
        $stmt->execute([$user_id]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);
    } elseif ($role === 'admin') {
        $stmt = $db->prepare('SELECT * FROM admins WHERE user_id = ?');
        $stmt->execute([$user_id]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $role_new = $user['role']; // For now, role is not editable
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');

    try {
        // Update user_accounts
        $stmt = $db->prepare('UPDATE user_accounts SET username=?, email=?, is_active=? WHERE id=?');
        $stmt->execute([$username, $email, $is_active, $user_id]);

        // Update profile table
        if ($role === 'student') {
            $stmt = $db->prepare('UPDATE students SET first_name=?, last_name=? WHERE user_id=?');
            $stmt->execute([$first_name, $last_name, $user_id]);
        } elseif ($role === 'instructor') {
            $stmt = $db->prepare('UPDATE instructors SET first_name=?, last_name=? WHERE user_id=?');
            $stmt->execute([$first_name, $last_name, $user_id]);
        } elseif ($role === 'admin') {
            $stmt = $db->prepare('UPDATE admins SET first_name=?, last_name=? WHERE user_id=?');
            $stmt->execute([$first_name, $last_name, $user_id]);
        }
        $success = 'User updated successfully!';
        // Refresh user/profile data
        header('Location: super_admin_dashboard.php?success=1');
        exit();
    } catch (PDOException $e) {
        $error = 'Error updating user: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <style>
        body {
            background: linear-gradient(120deg, #e0eafc, #cfdef3 100%);
            min-height: 100vh;
            font-family: 'Roboto', Arial, sans-serif;
            margin: 0;
        }
        .container {
            max-width: 420px;
            margin: 60px auto;
            background: #fff;
            padding: 0;
            border-radius: 18px;
            box-shadow: 0 8px 32px rgba(52, 152, 219, 0.15);
            overflow: hidden;
        }
        .form-header {
            background: linear-gradient(90deg, #3498db 60%, #6dd5fa 100%);
            color: #fff;
            padding: 32px 30px 18px 30px;
            text-align: center;
        }
        .form-header h2 {
            margin: 0;
            font-size: 2rem;
            font-weight: 700;
            letter-spacing: 1px;
        }
        form {
            padding: 28px 30px 24px 30px;
        }
        .form-group {
            margin-bottom: 18px;
        }
        label {
            display: block;
            margin-bottom: 7px;
            color: #34495e;
            font-weight: 500;
            font-size: 1rem;
        }
        input[type="text"], input[type="email"] {
            width: 100%;
            padding: 10px 12px;
            border: 1.5px solid #d0d7e2;
            border-radius: 6px;
            font-size: 1rem;
            background: #f8fafc;
            transition: border 0.2s;
        }
        input[type="text"]:focus, input[type="email"]:focus {
            border: 1.5px solid #3498db;
            outline: none;
            background: #fff;
        }
        input[disabled] {
            background: #f0f3f7;
            color: #888;
        }
        .form-group input[type="checkbox"] {
            width: auto;
            margin-right: 7px;
        }
        .btn {
            background: #3498db;
            color: #fff;
            border: none;
            padding: 11px 28px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1.08rem;
            font-weight: 600;
            margin-top: 8px;
            transition: background 0.2s, box-shadow 0.2s;
            box-shadow: 0 2px 8px rgba(52,152,219,0.08);
        }
        .btn:hover {
            background: #217dbb;
        }
        .btn.cancel {
            background: #b2bec3;
            color: #fff;
            margin-left: 10px;
        }
        .btn.cancel:hover {
            background: #636e72;
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
            text-align: center;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
            text-align: center;
        }
        @media (max-width: 600px) {
            .container {
                max-width: 98vw;
                margin: 18px auto;
            }
            .form-header, form {
                padding-left: 12px;
                padding-right: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="form-header">
            <h2>Edit User Account</h2>
        </div>
        <?php if ($error): ?><div class="error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <?php if ($success): ?><div class="success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
        <?php if ($user): ?>
        <form method="post">
            <div class="form-group">
                <label>Username:</label>
                <input type="text" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
            </div>
            <div class="form-group">
                <label>Email:</label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
            </div>
            <div class="form-group">
                <label>Role:</label>
                <input type="text" value="<?php echo htmlspecialchars(ucfirst($user['role'])); ?>" disabled>
            </div>
            <div class="form-group">
                <label>Status:</label>
                <input type="checkbox" name="is_active" value="1" <?php if ($user['is_active']) echo 'checked'; ?>> Active
            </div>
            <?php if (in_array($role, ['student', 'instructor', 'admin'])): ?>
            <div class="form-group">
                <label>First Name:</label>
                <input type="text" name="first_name" value="<?php echo htmlspecialchars($profile['first_name'] ?? ''); ?>" required>
            </div>
            <div class="form-group">
                <label>Last Name:</label>
                <input type="text" name="last_name" value="<?php echo htmlspecialchars($profile['last_name'] ?? ''); ?>" required>
            </div>
            <?php endif; ?>
            <button type="submit" class="btn">Save Changes</button>
            <a href="super_admin_dashboard.php" class="btn cancel">Cancel</a>
        </form>
        <?php endif; ?>
    </div>
</body>
</html> 