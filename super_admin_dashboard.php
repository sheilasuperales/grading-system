<?php
// Force error display
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Start session first
session_start();

// Debug information
error_log("Super Admin Dashboard - Session data: " . print_r($_SESSION, true));

// Include configuration
require_once 'config.php';

// Basic session check with debug
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    error_log("Session check failed - User ID: " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'not set') . 
              ", Role: " . (isset($_SESSION['role']) ? $_SESSION['role'] : 'not set'));
    header("Location: login.php");
    exit();
}

// Initialize variables
$users = [];
$userCounts = [
    'total' => 0,
    'super_admin' => 0,
    'admin' => 0,
    'instructor' => 0,
    'student' => 0,
    'active' => 0,
    'inactive' => 0
];
$error = '';
$success = '';
$searchQuery = '';

try {
    // Test database connection
    $db = getDB();
    error_log("Database connection successful");
    
    // Handle user deletion
    if (isset($_POST['delete_user']) && isset($_POST['user_id'])) {
        $userId = (int)$_POST['user_id'];
        
        // Check if user exists and is not a super_admin
        $checkStmt = $db->prepare("SELECT role FROM user_accounts WHERE id = ?");
        $checkStmt->execute([$userId]);
        $userRole = $checkStmt->fetchColumn();
        
        if ($userRole === 'super_admin') {
            $error = "Cannot delete a super admin account.";
        } else {
            $stmt = $db->prepare("DELETE FROM user_accounts WHERE id = ? AND role != 'super_admin'");
            if ($stmt->execute([$userId])) {
                $success = "User deleted successfully.";
            } else {
                $error = "Failed to delete user.";
            }
        }
    }
    
    // Handle user status toggle
    if (isset($_POST['toggle_status']) && isset($_POST['user_id'])) {
        $userId = (int)$_POST['user_id'];
        
        // Check if user exists and is not a super_admin
        $checkStmt = $db->prepare("SELECT role FROM user_accounts WHERE id = ?");
        $checkStmt->execute([$userId]);
        $userRole = $checkStmt->fetchColumn();
        
        if ($userRole === 'super_admin') {
            $error = "Cannot modify super admin account status.";
        } else {
            $stmt = $db->prepare("UPDATE user_accounts SET is_active = NOT is_active WHERE id = ? AND role != 'super_admin'");
            if ($stmt->execute([$userId])) {
                $success = "User status updated successfully.";
            } else {
                $error = "Failed to update user status.";
            }
        }
    }
    
    // Get search query
    if (isset($_GET['search'])) {
        $searchQuery = trim($_GET['search']);
    }
    
    // Build the base query
    $query = "
        SELECT ua.id, ua.username, ua.role, ua.email, 
               CONCAT(
                   CASE 
                       WHEN ua.role = 'student' THEN s.first_name 
                       WHEN ua.role = 'instructor' THEN i.first_name 
                       WHEN ua.role = 'admin' THEN a.first_name 
                       WHEN ua.role = 'super_admin' THEN sa.first_name 
                   END,
                   ' ',
                   CASE 
                       WHEN ua.role = 'student' THEN s.last_name 
                       WHEN ua.role = 'instructor' THEN i.last_name 
                       WHEN ua.role = 'admin' THEN a.last_name 
                       WHEN ua.role = 'super_admin' THEN sa.last_name 
                   END
               ) as fullname,
               ua.created_at, ua.is_active, ua.last_login
        FROM user_accounts ua
        LEFT JOIN students s ON ua.id = s.user_id
        LEFT JOIN instructors i ON ua.id = i.user_id
        LEFT JOIN admins a ON ua.id = a.user_id
        LEFT JOIN super_admins sa ON ua.id = sa.user_id
        WHERE ua.id != ?
    ";
    $params = [$_SESSION['user_id']];
    
    // Add search condition if search query exists
    if ($searchQuery) {
        $query .= " AND (ua.username LIKE ? OR ua.email LIKE ? OR 
            CONCAT(
                CASE 
                    WHEN ua.role = 'student' THEN s.first_name 
                    WHEN ua.role = 'instructor' THEN i.first_name 
                    WHEN ua.role = 'admin' THEN a.first_name 
                    WHEN ua.role = 'super_admin' THEN sa.first_name 
                END,
                ' ',
                CASE 
                    WHEN ua.role = 'student' THEN s.last_name 
                    WHEN ua.role = 'instructor' THEN i.last_name 
                    WHEN ua.role = 'admin' THEN a.last_name 
                    WHEN ua.role = 'super_admin' THEN sa.last_name 
                END
            ) LIKE ? OR
            (ua.is_active = 1 AND ? IN ('active', 'Active')) OR
            (ua.is_active = 0 AND ? IN ('inactive', 'Inactive')) OR
            ua.role LIKE ?
        )";
        $searchParam = "%$searchQuery%";
        $roleParam = "%$searchQuery%";
        $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchQuery, $searchQuery, $roleParam]);
    }
    
    $query .= " ORDER BY ua.role, ua.created_at DESC";
    
    // Execute the query
    $stmt = $db->prepare($query);
    if (!$stmt->execute($params)) {
        throw new Exception("Failed to execute user query: " . implode(", ", $stmt->errorInfo()));
    }
    
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Count users by role
    foreach ($users as $user) {
        $userCounts['total']++;
        if (isset($user['role']) && array_key_exists($user['role'], $userCounts)) {
            $userCounts[$user['role']]++;
        }
        if (isset($user['is_active'])) {
            if ($user['is_active']) {
                $userCounts['active']++;
            } else {
                $userCounts['inactive']++;
            }
        }
    }

} catch (Exception $e) {
    error_log('Error in super admin dashboard: ' . $e->getMessage());
    $error = "A system error occurred: " . $e->getMessage();
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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Super Admin Dashboard - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .dashboard-header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .content-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        .stat-card h3 {
            margin: 0;
            color: #666;
            font-size: 0.9em;
            text-transform: uppercase;
        }
        .stat-card .number {
            font-size: 2em;
            font-weight: bold;
            color: #4a90e2;
            margin: 10px 0;
        }
        .search-box {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .search-box input {
            flex: 1;
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        .search-box button {
            padding: 10px 20px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        .search-box button:hover {
            background: #2980b9;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f8f9fa;
            font-weight: 600;
        }
        tr:hover {
            background-color: #f8f9fa;
        }
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
        }
        .btn-primary {
            background: #3498db;
            color: white;
        }
        .btn-info {
            background: #2ecc71;
            color: white;
        }
        .btn-danger {
            background: #e74c3c;
            color: white;
        }
        .btn:hover {
            opacity: 0.9;
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
        <div class="dashboard-header">
            <div>
                <h1>Super Admin Dashboard</h1>
                <p>Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Administrator'); ?></p>
                <p>Role: <?php echo htmlspecialchars($_SESSION['role'] ?? 'super_admin'); ?></p>
            </div>
            <div class="header-actions">
                <a href="logout.php" class="btn btn-danger">Logout</a>
            </div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <div class="content-section">
            <div class="action-bar">
                <a href="create_admin.php" class="btn btn-primary">Add New Administrator</a>
                <a href="manage_courses.php" class="btn btn-info">Manage Courses & Subjects</a>
            </div>

            <div class="search-box" style="display: flex; align-items: center; gap: 10px; margin-bottom: 22px;">
                <form method="get" action="" style="display: flex; width: 100%; gap: 10px;">
                    <input type="text" name="search" placeholder="Search by username, full name, email, status (Active/Inactive), or role (admin, instructor, student)..." 
                           value="<?php echo htmlspecialchars($searchQuery); ?>" style="flex: 1; padding: 12px 16px; font-size: 1rem; border: 2px solid #3498db; border-radius: 8px; color: #34495e;">
                    <button type="submit" style="padding: 12px 25px; background: #3498db; border-radius: 8px; color: #fff; font-weight: 600; font-size: 1rem; cursor: pointer; transition: background-color 0.3s ease;">Search</button>
                    <?php if ($searchQuery): ?>
                        <a href="super_admin_dashboard.php" class="btn" style="background: #bdc3c7; color: #2c3e50; padding: 12px 25px; font-weight: 600; border-radius: 8px; transition: background-color 0.3s ease;">Clear</a>
                    <?php endif; ?>
                </form>
                <small style="color:#7f8c8d; font-size: 0.85rem; margin-left: 10px;">Search by username, full name, email, status, or role.</small>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Users</h3>
                    <div class="number"><?php echo $userCounts['total']; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Active Users</h3>
                    <div class="number"><?php echo $userCounts['active']; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Inactive Users</h3>
                    <div class="number"><?php echo $userCounts['inactive']; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Admins</h3>
                    <div class="number"><?php echo $userCounts['admin']; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Instructors</h3>
                    <div class="number"><?php echo $userCounts['instructor']; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Students</h3>
                    <div class="number"><?php echo $userCounts['student']; ?></div>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Full Name</th>
                        <th>Role</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Last Login</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td><?php echo htmlspecialchars($user['fullname'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars(ucfirst($user['role'])); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td>
                                <?php if ($user['is_active']): ?>
                                    <span style="color: #2ecc71;">Active</span>
                                <?php else: ?>
                                    <span style="color: #e74c3c;">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $user['last_login'] ? date('M d, Y H:i', strtotime($user['last_login'])) : 'Never'; ?></td>
                            <td>
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <?php if ($user['role'] !== 'super_admin'): ?>
                                        <a href="edit_user.php?id=<?php echo $user['id']; ?>" class="btn btn-primary" style="margin-right: 5px;">Edit</a>
                                        <button type="submit" name="toggle_status" class="btn btn-info">
                                            <?php echo $user['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                        </button>
                                        <button type="submit" name="delete_user" class="btn btn-danger" 
                                                onclick="return confirm('Are you sure you want to delete this user?')">
                                            Delete
                                        </button>
                                    <?php endif; ?>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html> 