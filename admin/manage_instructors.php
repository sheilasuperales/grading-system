<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php?msg=unauthorized');
    exit();
}

$db = getDB();

// Handle instructor status toggle
if (isset($_POST['toggle_status']) && isset($_POST['instructor_id'])) {
    $stmt = $db->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ? AND role = 'instructor'");
    $stmt->execute([$_POST['instructor_id']]);
    logActivity($_SESSION['user_id'], 'toggle_instructor_status', "Toggled status for instructor ID: {$_POST['instructor_id']}");
    header('Location: manage_instructors.php');
    exit();
}

// Handle instructor deletion
if (isset($_POST['delete_instructor']) && isset($_POST['instructor_id'])) {
    // Check if instructor has any active courses
    $stmt = $db->prepare("SELECT COUNT(*) FROM courses WHERE instructor_id = ?");
    $stmt->execute([$_POST['instructor_id']]);
    $courseCount = $stmt->fetchColumn();

    if ($courseCount > 0) {
        $error = "Cannot delete instructor with active courses. Please reassign or delete their courses first.";
    } else {
        $stmt = $db->prepare("DELETE FROM users WHERE id = ? AND role = 'instructor'");
        $stmt->execute([$_POST['instructor_id']]);
        logActivity($_SESSION['user_id'], 'delete_instructor', "Deleted instructor ID: {$_POST['instructor_id']}");
        header('Location: manage_instructors.php');
        exit();
    }
}

// Get all instructors with their departments
$instructors = $db->query("
    SELECT u.*, d.name as department_name,
           (SELECT COUNT(*) FROM courses WHERE instructor_id = u.id) as course_count,
           (SELECT COUNT(*) FROM users s 
            JOIN enrollments e ON s.id = e.student_id 
            JOIN courses c ON e.course_id = c.id 
            WHERE c.instructor_id = u.id) as student_count
    FROM users u
    LEFT JOIN departments d ON u.department = d.code
    WHERE u.role = 'instructor'
    ORDER BY u.fullname
")->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Instructors - <?php echo APP_NAME; ?></title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            color: #333;
            padding: 30px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 25px 35px;
            border-radius: 12px;
            box-shadow: 0 6px 15px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2c3e50;
            margin-bottom: 30px;
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
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
        }
        tr:hover {
            background: #f8f9fa;
        }
        .status {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 14px;
        }
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }
        .action-buttons {
            display: flex;
            gap: 10px;
        }
        .btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.3s;
        }
        .btn-toggle {
            background: #17a2b8;
            color: white;
        }
        .btn-toggle:hover {
            background: #138496;
        }
        .btn-delete {
            background: #dc3545;
            color: white;
        }
        .btn-delete:hover {
            background: #c82333;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        .stats {
            display: inline-block;
            background: #e8f4f8;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 14px;
            color: #2c3e50;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="nav">
            <a href="index.php">Dashboard</a>
            <a href="create_instructor.php">Create Instructor</a>
            <a href="../logout.php">Logout</a>
        </div>

        <h1>Manage Instructors</h1>

        <?php if (isset($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Department</th>
                    <th>Status</th>
                    <th>Statistics</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($instructors as $instructor): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($instructor['fullname']); ?></td>
                        <td><?php echo htmlspecialchars($instructor['email']); ?></td>
                        <td><?php echo htmlspecialchars($instructor['department_name']); ?></td>
                        <td>
                            <span class="status <?php echo $instructor['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                <?php echo $instructor['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </td>
                        <td>
                            <span class="stats">
                                Courses: <?php echo $instructor['course_count']; ?> |
                                Students: <?php echo $instructor['student_count']; ?>
                            </span>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="instructor_id" value="<?php echo $instructor['id']; ?>">
                                    <button type="submit" name="toggle_status" class="btn btn-toggle">
                                        <?php echo $instructor['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                    </button>
                                </form>
                                <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this instructor?');">
                                    <input type="hidden" name="instructor_id" value="<?php echo $instructor['id']; ?>">
                                    <button type="submit" name="delete_instructor" class="btn btn-delete">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html> 