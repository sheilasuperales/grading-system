<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php?msg=unauthorized');
    exit();
}

// Get statistics
$db = getDB();

// Get counts
$stats = [
    'instructors' => $db->query("SELECT COUNT(*) FROM users WHERE role = 'instructor'")->fetchColumn(),
    'students' => $db->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn(),
    'courses' => $db->query("SELECT COUNT(*) FROM courses")->fetchColumn(),
    'departments' => $db->query("SELECT COUNT(DISTINCT department) FROM users WHERE department IS NOT NULL")->fetchColumn()
];

// Get recent activities
$activities = $db->query("
    SELECT a.*, u.username, u.role 
    FROM activity_log a 
    JOIN users u ON a.user_id = u.id 
    ORDER BY a.timestamp DESC 
    LIMIT 10
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Dashboard - <?php echo APP_NAME; ?></title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            color: #333;
            padding: 30px;
            margin: 0;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .header {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 {
            margin: 0;
            color: #2c3e50;
        }
        .nav {
            display: flex;
            gap: 20px;
        }
        .nav a {
            color: #3498db;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 6px;
            transition: background-color 0.3s;
        }
        .nav a:hover {
            background: #f0f7ff;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .stat-card h3 {
            margin: 0 0 10px 0;
            color: #7f8c8d;
            font-size: 16px;
        }
        .stat-card .number {
            font-size: 32px;
            font-weight: bold;
            color: #2c3e50;
        }
        .recent-activity {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .recent-activity h2 {
            margin: 0 0 20px 0;
            color: #2c3e50;
        }
        .activity-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .activity-item {
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }
        .activity-item:last-child {
            border-bottom: none;
        }
        .activity-item .time {
            color: #7f8c8d;
            font-size: 14px;
        }
        .activity-item .user {
            color: #3498db;
            font-weight: 600;
        }
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .action-button {
            background: #3498db;
            color: white;
            padding: 15px 25px;
            border-radius: 8px;
            text-decoration: none;
            text-align: center;
            transition: background-color 0.3s;
        }
        .action-button:hover {
            background: #2980b9;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Admin Dashboard</h1>
            <div class="nav">
                <a href="create_instructor.php">Create Instructor</a>
                <a href="manage_instructors.php">Manage Instructors</a>
                <a href="manage_students.php">Manage Students</a>
                <a href="../logout.php">Logout</a>
            </div>
        </div>

        <div class="quick-actions">
            <a href="create_instructor.php" class="action-button">Create Instructor Account</a>
            <a href="manage_departments.php" class="action-button">Manage Departments</a>
            <a href="system_settings.php" class="action-button">System Settings</a>
            <a href="view_logs.php" class="action-button">View System Logs</a>
        </div>

        <div class="stats">
            <div class="stat-card">
                <h3>Total Instructors</h3>
                <div class="number"><?php echo $stats['instructors']; ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Students</h3>
                <div class="number"><?php echo $stats['students']; ?></div>
            </div>
            <div class="stat-card">
                <h3>Active Courses</h3>
                <div class="number"><?php echo $stats['courses']; ?></div>
            </div>
            <div class="stat-card">
                <h3>Departments</h3>
                <div class="number"><?php echo $stats['departments']; ?></div>
            </div>
        </div>

        <div class="recent-activity">
            <h2>Recent Activity</h2>
            <ul class="activity-list">
                <?php foreach ($activities as $activity): ?>
                    <li class="activity-item">
                        <span class="time"><?php echo date('M j, Y g:i A', strtotime($activity['timestamp'])); ?></span>
                        <span class="user"><?php echo htmlspecialchars($activity['username']); ?></span>
                        <span class="role">(<?php echo htmlspecialchars($activity['role']); ?>)</span>
                        <span class="action"><?php echo htmlspecialchars($activity['action']); ?></span>
                        <?php if ($activity['details']): ?>
                            <div class="details"><?php echo htmlspecialchars($activity['details']); ?></div>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</body>
</html> 