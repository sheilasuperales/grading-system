<?php
session_start();
require_once 'config.php';

// Only allow admins or super_admins
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    header('Location: index.php');
    exit();
}

$db = getDB();
$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['course_id'])) {
    $course_id = intval($_POST['course_id']);
    $selected_instructors = isset($_POST['instructors']) ? $_POST['instructors'] : [];
    try {
        // Remove all current assignments for this course
        $stmt = $db->prepare("DELETE FROM course_instructors WHERE course_id = ?");
        $stmt->execute([$course_id]);
        // Add new assignments
        if (!empty($selected_instructors)) {
            $stmt = $db->prepare("INSERT INTO course_instructors (course_id, instructor_id) VALUES (?, ?)");
            foreach ($selected_instructors as $instructor_id) {
                $stmt->execute([$course_id, $instructor_id]);
            }
        }
        // Redirect with GET to preserve selected course
        header('Location: assign_instructors.php?success=1&course_id=' . $course_id);
        exit();
    } catch (PDOException $e) {
        $error = "Error updating instructors: " . $e->getMessage();
    }
}

// Fetch all courses
$courses = $db->query("SELECT id, course_name, course_code FROM courses ORDER BY course_name")->fetchAll(PDO::FETCH_ASSOC);

// Fetch all instructors
$instructors = $db->query("SELECT ua.id, CONCAT(COALESCE(i.first_name, ''), ' ', COALESCE(i.last_name, '')) AS fullname, ua.username, ua.email FROM user_accounts ua LEFT JOIN instructors i ON ua.id = i.user_id WHERE ua.role = 'instructor' ORDER BY fullname, ua.username")->fetchAll(PDO::FETCH_ASSOC);

// Determine selected course
$selected_course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : (isset($_GET['course_id']) ? intval($_GET['course_id']) : ($courses[0]['id'] ?? null));

// Fetch assigned instructors for selected course
$assigned_instructors = [];
if ($selected_course_id) {
    $stmt = $db->prepare("SELECT instructor_id FROM course_instructors WHERE course_id = ?");
    $stmt->execute([$selected_course_id]);
    $assigned_instructors = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'instructor_id');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Instructors to Courses</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Roboto', Arial, sans-serif; background: #f5f7fa; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 40px auto; background: #fff; padding: 36px 32px 32px 32px; border-radius: 14px; box-shadow: 0 8px 32px rgba(52, 152, 219, 0.10); }
        h2 { text-align: center; margin-bottom: 30px; font-size: 2rem; font-weight: 700; color: #2c3e50; }
        .form-group { margin-bottom: 22px; }
        label { font-weight: 600; color: #34495e; margin-bottom: 7px; display: block; font-size: 1.08rem; }
        select { padding: 10px 14px; border-radius: 6px; border: 1.5px solid #b2bec3; font-size: 1.08rem; background: #f8fafc; min-width: 220px; }
        select:focus { border: 1.5px solid #3498db; outline: none; background: #fff; }
        .instructor-list { max-height: 300px; overflow-y: auto; border: 1px solid #eee; border-radius: 5px; padding: 10px; background: #fafbfc; }
        .instructor-item { margin-bottom: 8px; }
        .btn { background: #3498db; color: #fff; border: none; padding: 12px 28px; border-radius: 6px; cursor: pointer; font-size: 1.08rem; font-weight: 600; transition: background 0.2s; box-shadow: 0 2px 8px rgba(52,152,219,0.08); }
        .btn:hover { background: #217dbb; }
        .success { background: #d4edda; color: #155724; padding: 12px; border-radius: 6px; margin-bottom: 18px; text-align: center; font-size: 1.08rem; }
        .error { background: #f8d7da; color: #721c24; padding: 12px; border-radius: 6px; margin-bottom: 18px; text-align: center; font-size: 1.08rem; }
        .back-link { display: block; text-align: center; margin-top: 28px; }
        .back-link a { color: #3498db; text-decoration: none; font-weight: 600; font-size: 1.08rem; transition: color 0.2s; }
        .back-link a:hover { color: #217dbb; text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Assign Instructors to Courses</h2>
        <?php if ($error): ?><div class="error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <?php if (isset($_GET['success'])): ?><div class="success">Instructors updated for the course.</div><?php endif; ?>
        <form method="post" action="assign_instructors.php">
            <div class="form-group">
                <label for="course_id"><strong>Select Course:</strong></label>
                <select name="course_id" id="course_id" onchange="this.form.submit()" required>
                    <?php foreach ($courses as $course): ?>
                        <option value="<?php echo $course['id']; ?>" <?php if ($selected_course_id == $course['id']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($course['course_name'] . ' (' . $course['course_code'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label><strong>Assign Instructors:</strong></label>
                <div class="instructor-list">
                    <?php foreach ($instructors as $instructor): ?>
                        <div class="instructor-item">
                            <label>
                                <input type="checkbox" name="instructors[]" value="<?php echo $instructor['id']; ?>" <?php if (in_array($instructor['id'], $assigned_instructors)) echo 'checked'; ?>>
                                <?php echo htmlspecialchars(trim($instructor['fullname'])) ?: htmlspecialchars($instructor['username']); ?> (<?php echo htmlspecialchars($instructor['email']); ?>)
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <button type="submit" class="btn">Save Assignments</button>
        </form>
        <div class="back-link">
            <a href="admin_dashboard.php">&larr; Back to Dashboard</a>
        </div>
    </div>
</body>
</html> 