<?php
if (!isset($_SESSION)) {
    session_start();
}

// Get the current script path to determine if we're in admin directory
$current_path = $_SERVER['SCRIPT_NAME'];
$in_admin = strpos($current_path, '/admin/') !== false;
$root_path = $in_admin ? '../' : '';

// List of pages that don't require login
$public_pages = ['index.php', 'register.php', 'forgot_password.php'];

// Get the current page name
$current_page = basename($_SERVER['PHP_SELF']);

// Only redirect if not on a public page
if (!isset($_SESSION['user_id']) && !in_array($current_page, $public_pages)) {
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>School Grading System</title>
    <style>
        .header {
            background: #2c3e50;
            padding: 1rem;
            color: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .logo {
            font-size: 1.5em;
            font-weight: bold;
            color: white;
            text-decoration: none;
        }
        .nav-menu {
            display: flex;
            gap: 20px;
            align-items: center;
        }
        .nav-link {
            color: white;
            text-decoration: none;
            padding: 5px 10px;
            border-radius: 4px;
            transition: background 0.3s;
        }
        .nav-link:hover {
            background: rgba(255,255,255,0.1);
        }
        .settings-dropdown {
            position: relative;
            display: inline-block;
        }
        .settings-dropdown:hover .dropdown-content {
            display: block;
        }
        .dropdown-content {
            display: none;
            position: absolute;
            background-color: #fff;
            min-width: 200px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
            z-index: 1000;
            border-radius: 4px;
            top: 100%;
            right: 0;
        }
        .dropdown-content a {
            color: #333;
            padding: 12px 16px;
            text-decoration: none;
            display: block;
            transition: background 0.3s;
        }
        .dropdown-content a:hover {
            background-color: #f5f5f5;
        }
        .user-menu {
            position: relative;
            display: inline-block;
        }
        .user-button {
            background: none;
            border: none;
            color: white;
            padding: 5px 10px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .user-menu-content {
            display: none;
            position: absolute;
            right: 0;
            background: white;
            min-width: 160px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            border-radius: 4px;
            z-index: 1000;
        }
        .user-menu-content a {
            color: #333;
            padding: 12px 16px;
            text-decoration: none;
            display: block;
        }
        .user-menu-content a:hover {
            background: #f5f5f5;
        }
        .user-menu:hover .user-menu-content {
            display: block;
        }
        .notification {
            background: #f8d7da;
            color: #721c24;
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
            text-align: center;
        }
        .logout-link {
            color: white;
            text-decoration: none;
            padding: 5px 10px;
            border-radius: 4px;
            transition: background 0.3s;
            margin-left: 20px;
        }
        .logout-link:hover {
            background: rgba(255,255,255,0.1);
        }
        @media (max-width: 768px) {
            .header-container {
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }
            .nav-menu {
                flex-direction: column;
                width: 100%;
            }
            .user-menu {
                width: 100%;
            }
            .user-menu-content {
                width: 100%;
                position: static;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-container">
            <a href="<?php echo $root_path; ?>index.php" class="logo">School Grading System</a>
            
            <?php if (isset($_SESSION['user_id'])): ?>
                <nav class="nav-menu">
                    <?php if ($_SESSION['role'] === 'super_admin'): ?>
                        <a href="<?php echo $root_path; ?>super_admin_dashboard.php" class="nav-link">Dashboard</a>
                        <div class="settings-dropdown">
                            <a href="#" class="nav-link">Settings ▼</a>
                            <div class="dropdown-content">
                                <a href="<?php echo $root_path; ?>profile.php">Profile</a>
                                <a href="<?php echo $root_path; ?>change_password.php">Change Password</a>
                                <a href="<?php echo $root_path; ?>system_settings.php" style="border-top: 1px solid #eee;">System Settings</a>
                            </div>
                        </div>
                        <a href="<?php echo $root_path; ?>logout.php" class="logout-link">Logout</a>
                    <?php elseif ($_SESSION['role'] === 'admin'): ?>
                        <a href="<?php echo $root_path; ?>admin_dashboard.php" class="nav-link">Dashboard</a>
                        <div class="settings-dropdown">
                            <a href="#" class="nav-link">Settings ▼</a>
                            <div class="dropdown-content">
                                <a href="<?php echo $root_path; ?>profile.php">Profile</a>
                                <a href="<?php echo $root_path; ?>change_password.php">Change Password</a>
                                <a href="<?php echo $root_path; ?>system_settings.php" style="border-top: 1px solid #eee;">System Settings</a>
                            </div>
                        </div>
                        <a href="<?php echo $root_path; ?>logout.php" class="logout-link">Logout</a>
                    <?php elseif ($_SESSION['role'] === 'instructor'): ?>
                        <a href="<?php echo $root_path; ?>instructor_dashboard.php" class="nav-link">Dashboard</a>
                        <a href="<?php echo $root_path; ?>reports.php" class="nav-link">View Reports</a>
                        <div class="user-menu">
                            <button class="user-button">
                                <?php echo htmlspecialchars($_SESSION['username']); ?>
                                <span>▼</span>
                            </button>
                            <div class="user-menu-content">
                                <a href="<?php echo $root_path; ?>profile.php">Profile</a>
                                <a href="<?php echo $root_path; ?>change_password.php">Change Password</a>
                                <a href="<?php echo $root_path; ?>logout.php">Logout</a>
                            </div>
                        </div>
                    <?php elseif ($_SESSION['role'] === 'student'): ?>
                        <a href="<?php echo $root_path; ?>student_dashboard.php" class="nav-link">Dashboard</a>
                        <a href="<?php echo $root_path; ?>my_courses.php" class="nav-link">My Courses</a>
                        <a href="<?php echo $root_path; ?>my_grades.php" class="nav-link">My Grades</a>
                        <div class="user-menu">
                            <button class="user-button">
                                <?php echo htmlspecialchars($_SESSION['username']); ?>
                                <span>▼</span>
                            </button>
                            <div class="user-menu-content">
                                <a href="<?php echo $root_path; ?>profile.php">Profile</a>
                                <a href="<?php echo $root_path; ?>change_password.php">Change Password</a>
                                <a href="<?php echo $root_path; ?>logout.php">Logout</a>
                            </div>
                        </div>
                    <?php endif; ?>
                </nav>
            <?php else: ?>
                <nav class="nav-menu">
                    <a href="index.php" class="nav-link">Login</a>
                    <a href="register.php" class="nav-link">Register</a>
                </nav>
            <?php endif; ?>
        </div>
    </header>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="notification error">
            <?php 
            echo htmlspecialchars($_SESSION['error']);
            unset($_SESSION['error']);
            ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="notification success">
            <?php 
            echo htmlspecialchars($_SESSION['success']);
            unset($_SESSION['success']);
            ?>
        </div>
    <?php endif; ?>
</body>
</html> 