<?php
session_start();
require_once 'config.php';

// Strict admin access control
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['error'] = "Access Denied. This area is restricted to administrators only.";
    header("Location: login.php");
    exit();
}

// Initialize variables
$selected_course = '';
$subjects = [];
$error = '';
$success = '';
$users = [];
$userCounts = [
    'total' => 0,
    'admin' => 0,
    'instructor' => 0,
    'student' => 0,
    'active' => 0,
    'inactive' => 0
];
$searchQuery = '';

// Get selected course from URL parameter
if (isset($_GET['course'])) {
    $selected_course = $_GET['course'];
}

try {
    $db = getDB();

    // Get all courses
    $stmt = $db->query("SELECT course_code, MIN(course_name) as course_name FROM courses GROUP BY course_code ORDER BY course_code");
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // If a course is selected, get its subjects
    if ($selected_course) {
        $stmt = $db->prepare("
            SELECT * FROM subjects s
            JOIN courses c ON s.course_id = c.id
            WHERE c.course_code = ?
            ORDER BY s.year_level, s.semester, s.subject_code
        ");
        $stmt->execute([$selected_course]);
        $all_subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Group subjects by year level
        foreach ($all_subjects as $subject) {
            $year = $subject['year_level'] . getYearSuffix($subject['year_level']) . ' Year';
            if (!isset($subjects[$year])) {
                $subjects[$year] = [];
            }
            $subjects[$year][] = $subject;
        }
    }

    // Handle user status toggle if requested
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['toggle_status']) && isset($_POST['user_id'])) {
            $userId = (int)$_POST['user_id'];
            try {
                // Check if user exists and is not an admin
                $checkStmt = $db->prepare("SELECT role FROM users WHERE id = ?");
                $checkStmt->execute([$userId]);
                $userRole = $checkStmt->fetchColumn();

                if ($userRole === 'admin') {
                    $error = "Cannot modify administrator account status.";
                } else {
                    $stmt = $db->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ? AND role NOT IN ('admin')");
                    if ($stmt->execute([$userId])) {
                        $success = "User  status updated successfully.";
                    } else {
                        $error = "Failed to update user status.";
                    }
                }
            } catch (PDOException $e) {
                $error = "Error updating user status: " . $e->getMessage();
            }
        }

        // Handle user deletion with additional security check
        if (isset($_POST['delete_user']) && isset($_POST['user_id'])) {
            $userId = (int)$_POST['user_id'];
            try {
                // First verify this isn't an admin
                $checkStmt = $db->prepare("SELECT role FROM users WHERE id = ?");
                $checkStmt->execute([$userId]);
                $userRole = $checkStmt->fetchColumn();

                if ($userRole === 'admin') {
                    $error = "Cannot delete administrator accounts.";
                } else {
                    $stmt = $db->prepare("DELETE FROM users WHERE id = ? AND role NOT IN ('admin')");
                    if ($stmt->execute([$userId])) {
                        $success = "User  deleted successfully.";
                    } else {
                        $error = "Failed to delete user.";
                    }
                }
            } catch (PDOException $e) {
                $error = "Error deleting user: " . $e->getMessage();
            }
        }
    }

    // Get all users with additional fields
    function getAllUsers() {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT ua.id, ua.username, ua.role,
                CASE 
                    WHEN ua.role = 'admin' THEN CONCAT(a.first_name, ' ', a.last_name)
                    WHEN ua.role = 'instructor' THEN CONCAT(i.first_name, ' ', i.last_name)
                    WHEN ua.role = 'student' THEN CONCAT(s.first_name, ' ', s.last_name)
                    ELSE ''
                END as fullname,
                ua.email, ua.created_at, ua.is_active, ua.last_login
            FROM user_accounts ua
            LEFT JOIN admins a ON ua.id = a.user_id
            LEFT JOIN instructors i ON ua.id = i.user_id
            LEFT JOIN students s ON ua.id = s.user_id
            WHERE ua.role IN ('admin', 'instructor', 'student')
            ORDER BY ua.role, ua.created_at DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $users = getAllUsers();

    // Add search functionality
    if (isset($_GET['search'])) {
        $searchQuery = trim($_GET['search']);
        if ($searchQuery) {
            $filteredUsers = array_filter($users, function($user) use ($searchQuery) {
                $status = $user['is_active'] ? 'active' : 'inactive';
                $q = strtolower($searchQuery);
                // Exact match for status
                if ($q === 'active' && $status === 'active') return true;
                if ($q === 'inactive' && $status === 'inactive') return true;
                // Partial match for other fields
                return stripos($user['username'], $searchQuery) !== false ||
                       stripos($user['fullname'], $searchQuery) !== false ||
                       stripos($user['email'], $searchQuery) !== false ||
                       stripos($user['role'], $searchQuery) !== false;
            });
            $users = $filteredUsers;
        }
    }

    // Count users by role
    foreach ($users as $user) {
        $userCounts['total']++;

        // Ensure the role exists in the count array before incrementing
        if (isset($user['role']) && array_key_exists($user['role'], $userCounts)) {
            $userCounts[$user['role']]++;
        }

        // Count active/inactive users
        if (isset($user['is_active'])) {
            if ($user['is_active']) {
                $userCounts['active']++;
            } else {
                $userCounts['inactive']++;
            }
        }
    }

} catch (PDOException $e) {
    error_log("Error in admin_dashboard.php: " . $e->getMessage());
    $error = "A system error occurred. Please try again later.";
}

// Helper function to get year suffix
function getYearSuffix($year) {
    if ($year == 1) return 'st';
    if ($year == 2) return 'nd';
    if ($year == 3) return 'rd';
    return 'th';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Admin Dashboard - School Grading System</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap');
        * {
            box-sizing: border-box;
        }
        body {
            font-family: 'Poppins', sans-serif;
            background: #e9f0fa;
            margin: 0;
            color: #2c3e50;
            line-height: 1.5;
        }
        a {
            text-decoration: none;
            color: #3498db;
            transition: color 0.3s ease;
        }
        a:hover, a:focus {
            color: #1d6fa5;
            outline: none;
        }
        .container {
            max-width: 1200px;
            margin: 30px auto 50px;
            padding: 0 24px;
        }
        .dashboard-header {
            background: #fff;
            padding: 25px 30px;
            border-radius: 12px;
            box-shadow: 0 12px 25px rgba(34, 60, 80, 0.1);
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
        }
        .dashboard-header h1 {
            font-weight: 700;
            font-size: 2.2rem;
            margin: 0;
            color: #34495e;
        }
        .message {
            max-width: 800px;
            margin: 15px auto 25px;
            padding: 16px 24px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1rem;
            box-shadow: 0 4px 12px rgba(33, 33, 33, 0.1);
        }
        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .dashboard-sections {
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 28px;
        }
        /* Left Sidebar */
        .left-sidebar {
            background: #fff;
            padding: 25px 22px;
            border-radius: 12px;
            box-shadow: 0 12px 25px rgba(34, 60, 80, 0.08);
            position: sticky;
            top: 30px;
            height: fit-content;
        }
        .left-sidebar h2 {
            font-weight: 700;
            font-size: 1.4rem;
            border-bottom: 2px solid #3498db;
            padding-bottom: 12px;
            margin-bottom: 25px;
            color: #2a3a52;
        }
        .course-selector select {
            width: 100%;
            padding: 12px 15px;
            font-size: 1rem;
            border: 2px solid #3498db;
            border-radius: 8px;
            color: #34495e;
            transition: border-color 0.3s ease;
            cursor: pointer;
        }
        .course-selector select:hover,
        .course-selector select:focus {
            border-color: #1d6fa5;
            outline: none;
        }
        .course-content {
            margin-top: 30px;
        }
        .course-content h3 {
            color: #2c3e50;
            margin-bottom: 18px;
            font-weight: 600;
            font-size: 1.2rem;
            border-bottom: 1px solid #dae3ea;
            padding-bottom: 6px;
        }
        .year-section {
            background: #f9fbfe;
            padding: 16px 18px;
            margin-bottom: 18px;
            border-radius: 8px;
            box-shadow: inset 0 1px 2px rgba(0,0,0,0.05);
        }
        .year-title {
            color: #2c3e50;
            margin: 0 0 12px 0;
            font-size: 1.1rem;
            font-weight: 700;
            border-bottom: 2px solid #3498db;
            padding-bottom: 5px;
        }
        .subjects-list {
            list-style: none;
            padding-left: 0;
            margin: 0;
        }
        .subjects-list li {
            padding: 8px 0;
            border-bottom: 1px solid #e1e8f0;
            font-size: 0.95rem;
            transition: background-color 0.25s ease;
            border-radius: 5px;
        }
        .subjects-list li:last-child {
            border-bottom: none;
        }
        .subject-link {
            color: #3498db;
            display: block;
            font-weight: 500;
            padding: 5px 0;
            border-radius: 5px;
            transition: background-color 0.25s ease;
        }
        .subject-link small {
            color: #7f8c8d;
            margin-left: 6px;
            font-weight: 400;
        }
        .subject-link:hover,
        .subject-link:focus {
            background: #eaf3fc;
            color: #1d6fa5;
            outline: none;
            padding-left: 12px;
            text-decoration: none;
        }
        /* Main Content Area */
        .main-content {
            background: #fff;
            padding: 30px 25px;
            border-radius: 12px;
            box-shadow: 0 12px 25px rgba(34, 60, 80, 0.08);
        }
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 20px;
            margin-bottom: 36px;
        }
        .stat-card {
            background: #3498db;
            color: #fff;
            border-radius: 12px;
            padding: 20px 15px;
            text-align: center;
            box-shadow: 0 6px 15px rgba(52, 152, 219, 0.5);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            cursor: default;
            user-select: none;
        }
        .stat-card:hover, .stat-card:focus-within {
            transform: translateY(-6px);
            box-shadow: 0 12px 30px rgba(52, 152, 219, 0.65);
            outline: none;
        }
        .stat-card h3 {
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #ebf5fb;
        }
        .stat-card .number {
            font-size: 2.6rem;
            font-weight: 700;
            margin-top: 5px;
            font-family: 'Courier New', Courier, monospace;
            letter-spacing: 3px;
        }
        /* Colors for cards */
        .stat-card:nth-child(2) {
            background: #27ae60;
            box-shadow: 0 6px 15px rgba(39, 174, 96, 0.5);
        }
        .stat-card:nth-child(2):hover,
        .stat-card:nth-child(2):focus-within {
            box-shadow: 0 12px 30px rgba(39, 174, 96, 0.65);
        }
        .stat-card:nth-child(3) {
            background: #16a085;
            box-shadow: 0 6px 15px rgba(22, 160, 133, 0.5);
        }
        .stat-card:nth-child(3):hover,
        .stat-card:nth-child(3):focus-within {
            box-shadow: 0 12px 30px rgba(22, 160, 133, 0.65);
        }
        .stat-card:nth-child(4) {
            background: #e67e22;
            box-shadow: 0 6px 15px rgba(230, 126, 34, 0.5);
        }
        .stat-card:nth-child(4):hover,
        .stat-card:nth-child(4):focus-within {
            box-shadow: 0 12px 30px rgba(230, 126, 34, 0.65);
        }
        /* Search Box */
        .search-box {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 22px;
        }
        .search-box input[type="text"] {
            flex: 1;
            padding: 12px 16px;
            font-size: 1rem;
            border: 2px solid #3498db;
            border-radius: 8px;
            transition: border-color 0.3s ease;
            color: #34495e;
        }
        .search-box input[type="text"]:focus {
            border-color: #1d6fa5;
            outline: none;
        }
        .search-box button {
            padding: 12px 25px;
            background: #3498db;
            border-radius: 8px;
            color: #fff;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        .search-box button:hover,
        .search-box button:focus {
            background: #1d6fa5;
            outline: none;
        }
        .search-box .action-btn {
            background: #bdc3c7;
            color: #2c3e50;
            padding: 12px 25px;
            font-weight: 600;
            border-radius: 8px;
            transition: background-color 0.3s ease;
        }
        .search-box .action-btn:hover,
        .search-box .action-btn:focus {
            background: #95a5a6;
            color: #1c2833;
            outline: none;
        }
        /* Action Buttons */
        .action-buttons {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }
        .action-btn {
            background: #2980b9;
            color: #fff;
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: background-color 0.3s ease;
            text-align: center;
            border: none;
            user-select: none;
            display: inline-block;
            text-decoration: none;
        }
        .action-btn:hover,
        .action-btn:focus {
            background: #1b4f72;
            outline: none;
            text-decoration: none;
        }
        .delete-btn {
            background: #c0392b;
        }
        .delete-btn:hover,
        .delete-btn:focus {
            background: #922b21;
            outline: none;
        }
        .toggle-btn {
            background: #f39c12;
            color: #fff;
            padding: 10.5px 22px;
            font-weight: 600;
            border-radius: 8px;
            cursor: pointer;
            transition: background-color 0.3s ease;
            user-select: none;
        }
        .toggle-btn:hover,
        .toggle-btn:focus {
            background: #ba7400;
            outline: none;
        }
        /* Users Table */
        .users-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 12px 25px rgba(34, 60, 80, 0.1        );
        }
        .users-table thead tr {
            background-color: #2980b9;
            color: white;
            text-align: left;
        }
        .users-table th, .users-table td {
            padding: 14px 18px;
            font-size: 0.95rem;
            border-bottom: 1px solid #ecf0f1;
            word-wrap: break-word;
        }
        .users-table tbody tr:nth-child(even) {
            background-color: #f6f9fc;
        }
        .role-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 16px;
            font-size: 0.85rem;
            font-weight: 700;
            text-transform: capitalize;
            user-select: none;
            background-color: #f0f0f0;
            color: #333;
        }
        .status-active {
            background: #2ecc71;
            color: white;
            padding: 5px 12px;
            border-radius: 16px;
            font-size: 0.85rem;
            user-select: none;
            display: inline-block;
            white-space: nowrap;
        }
        .status-inactive {
            background: #e74c3c;
            color: white;
            padding: 5px 12px;
            border-radius: 16px;
            font-size: 0.85rem;
            user-select: none;
            display: inline-block;
            white-space: nowrap;
        }
        /* Responsive tweaks */
        @media (max-width: 1020px) {
            .dashboard-sections {
                grid-template-columns: 1fr;
            }
            .left-sidebar {
                position: static;
                margin-bottom: 30px;
                height: auto;
            }
        }
        @media (max-width: 480px) {
            .stat-card .number {
                font-size: 1.7rem;
            }
            .dashboard-header h1 {
                font-size: 1.6rem;
                text-align: center;
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <?php require_once 'header.php'; ?>

    <main class="container" role="main" aria-label="Admin dashboard main content">
        <section class="dashboard-header" aria-label="Dashboard header with title">
            <h1 tabindex="0">Admin Dashboard</h1>
        </section>

        <?php if ($success): ?>
            <div class="message success" role="alert" tabindex="0"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="message error" role="alert" tabindex="0"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <section class="dashboard-sections">
            <!-- Left Sidebar with Course Overview -->
            <aside class="left-sidebar" aria-labelledby="course-overview-heading">
                <h2 id="course-overview-heading">Course Overview</h2>
                <div class="course-selector">
                    <label for="courseSelect" class="sr-only">Choose a course to view curriculum</label>
                    <select id="courseSelect" aria-controls="courseContent" onchange="window.location.href='admin_dashboard.php?course=' + encodeURIComponent(this.value)">
                        <option value="">Select a Course</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?php echo htmlspecialchars($course['course_code']); ?>"
                                <?php echo $selected_course === $course['course_code'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($course['course_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if ($selected_course && !empty($subjects)): ?>
                    <article id="courseContent" class="course-content" tabindex="0" aria-live="polite">
                        <h3><?php echo htmlspecialchars($selected_course); ?> Curriculum</h3>
                        <?php foreach ($subjects as $year => $year_subjects): ?>
                            <section class="year-section" aria-labelledby="year-<?php echo htmlspecialchars($year); ?>-heading">
                                <h4 id="year-<?php echo htmlspecialchars($year); ?>-heading" class="year-title"><?php echo htmlspecialchars($year); ?></h4>
                                <ul class="subjects-list">
                                    <?php foreach ($year_subjects as $subject): ?>
                                        <li>
                                            <a href="admin/subject_details.php?course=<?php echo urlencode($selected_course); ?>&subject=<?php echo urlencode($subject['subject_name']); ?>&year=<?php echo urlencode($year); ?>" class="subject-link">
                                                <?php echo htmlspecialchars($subject['subject_name']); ?>
                                                <small>(<?php echo htmlspecialchars($subject['subject_code']); ?>)</small>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </section>
                        <?php endforeach; ?>
                    </article>
                <?php endif; ?>
            </aside>

            <!-- Main Content Area -->
            <section class="main-content" aria-label="Users and statistics section">
                <div class="stats-container" role="region" aria-label="User statistics summary">
                    <div class="stat-card" tabindex="0">
                        <h3>Total Users</h3>
                        <div class="number" aria-live="polite"><?php echo $userCounts['total']; ?></div>
                    </div>
                    <div class="stat-card" tabindex="0">
                        <h3>Active Users</h3>
                        <div class="number" aria-live="polite"><?php echo $userCounts['active']; ?></div>
                    </div>
                    <div class="stat-card" tabindex="0">
                        <h3>Instructors</h3>
                        <div class="number" aria-live="polite"><?php echo $userCounts['instructor']; ?></div>
                    </div>
                    <div class="stat-card" tabindex="0">
                        <h3>Students</h3>
                        <div class="number" aria-live="polite"><?php echo $userCounts['student']; ?></div>
                    </div>
                </div>

                <div class="search-box" role="search" aria-label="Search users">
                    <form method="get" action="" style="display: flex; width: 100%; gap: 10px;">
                        <label for="searchInput" class="sr-only">Search users by username, full name, email, role, or status</label>
                        <input type="text" id="searchInput" name="search" placeholder="Search by username, full name, email, role, or status (Active/Inactive)..." value="<?php echo htmlspecialchars($searchQuery); ?>" aria-describedby="searchHelp" autocomplete="off" style="flex: 1; padding: 12px 16px; font-size: 1rem; border: 2px solid #3498db; border-radius: 8px; color: #34495e;" />
                        <button type="submit" aria-label="Search users" style="padding: 12px 25px; background: #3498db; border-radius: 8px; color: #fff; font-weight: 600; font-size: 1rem; cursor: pointer; transition: background-color 0.3s ease;">Search</button>
                        <?php if ($searchQuery): ?>
                            <a href="admin_dashboard.php" class="action-btn" role="button" aria-label="Clear search" style="background: #bdc3c7; color: #2c3e50; padding: 12px 25px; font-weight: 600; border-radius: 8px; transition: background-color 0.3s ease;">Clear</a>
                        <?php endif; ?>
                    </form>
                    <small id="searchHelp" style="color:#7f8c8d; font-size: 0.85rem; margin-left: 10px;">Search by username, full name, email, role, or status.</small>
                </div>

                <div class="action-buttons" role="group" aria-label="User actions">
                    <!-- Only allow adding instructors, no permission to add admins -->
                    <a href="register_instructor.php" class="action-btn" role="button">+ Add New Instructor</a>
                </div>

                <div class="quick-actions">
                    <a href="assign_instructors.php" class="quick-action-btn">
                        <i class="fas fa-user-plus"></i>
                        Assign Instructors to Courses
                    </a>
                </div>

                <div class="users-table" role="table" aria-label="Users list table">
                    <table>
                        <thead>
                            <tr role="row">
                                <th role="columnheader" scope="col">Username</th>
                                <th role="columnheader" scope="col">Role</th>
                                <th role="columnheader" scope="col">Full Name</th>
                                <th role="columnheader" scope="col">Email</th>
                                <th role="columnheader" scope="col">Status</th>
                                <th role="columnheader" scope="col">Registration Date</th>
                                <th role="columnheader" scope="col">Last Login</th>
                                <th role="columnheader" scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($users)): ?>
                                <tr><td colspan="8" style="padding:20px; text-align:center; font-style:italic; color:#7f8c8d;">No users found.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($users as $user): ?>
                                <tr role="row">
                                    <td role="cell"><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td role="cell">
                                        <span class="role-badge">
                                            <?php echo htmlspecialchars(ucfirst($user['role'])); ?>
                                        </span>
                                    </td>
                                    <td role="cell"><?php echo htmlspecialchars($user['fullname']); ?></td>
                                    <td role="cell"><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td role="cell">
                                        <span class="status-<?php echo $user['is_active'] ? 'active' : 'inactive'; ?>" aria-label="User is <?php echo $user['is_active'] ? 'active' : 'inactive'; ?>">
                                            <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td role="cell"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                    <td role="cell"><?php echo $user['last_login'] ? date('M d, Y H:i', strtotime($user['last_login'])) : 'Never'; ?></td>
                                    <td role="cell">
                                        <?php if ($user['role'] !== 'admin'): // do not allow admin-manipulation ?>
                                            <div class="action-buttons">
                                                <form method="post" style="display: inline;" aria-label="Toggle user activation status for <?php echo htmlspecialchars($user['username']); ?>" onsubmit="return confirm('Are you sure you want to change the status of this user?');">
                                                    <input type="hidden" name="user_id" value="<?php echo (int)$user['id']; ?>">
                                                    <button type="submit" name="toggle_status" class="toggle-btn" aria-pressed="<?php echo $user['is_active'] ? 'true' : 'false'; ?>">
                                                        <?php echo $user['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                                    </button>
                                                </form>
                                                <form method="post" style="display: inline;" aria-label="Delete user <?php echo htmlspecialchars($user['username']); ?>" onsubmit="return confirm('Are you sure you want to delete this user? This action cannot be undone.');">
                                                    <input type="hidden" name="user_id" value="<?php echo (int)$user['id']; ?>">
                                                    <button type="submit" name="delete_user" class="action-btn delete-btn" aria-label="Delete user">Delete</button>
                                                </form>
                                            </div>
                                        <?php else: ?>
                                            <span style="color: #7f8c8d; font-style: italic; user-select:none;">Protected</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </section>
    </main>

    <script>
        // Enhance user experience by handling course select keyboard accessibility
        document.getElementById('courseSelect').addEventListener('keydown', function(event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                window.location.href = 'admin_dashboard.php?course=' + encodeURIComponent(this.value);
            }
        });
    </script>
</body>
</html>
