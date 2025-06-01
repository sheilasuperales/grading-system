<?php
session_start();

define('DB_FILE', __DIR__.'/grading_system.sqlite');

if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit();
}

$user = $_SESSION['user'];

function getDB() {
    static $db;
    if ($db === null) {
        if (!file_exists(DB_FILE)) {
            die("Database not found. Please setup the database first.");
        }
        $db = new PDO('sqlite:'.DB_FILE);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    return $db;
}

function e($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function letterGrade($grade) {
    if ($grade >= 90) return 'A';
    if ($grade >= 80) return 'B';
    if ($grade >= 70) return 'C';
    if ($grade >= 60) return 'D';
    return 'F';
}

function getStudentInfo($studentId) {
    $db = getDB();
    $stmt = $db->prepare("SELECT fullname, email FROM users WHERE id = :id AND role='student'");
    $stmt->execute([':id' => $studentId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getGradesForStudent($studentId) {
    $db = getDB();
    $stmt = $db->prepare("SELECT g.*, u.fullname AS instructor_name FROM grades g JOIN users u ON g.instructor_id = u.id WHERE g.student_id = :student_id ORDER BY g.subject");
    $stmt->execute([':student_id' => $studentId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getUsersByRole($role) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE role = :role ORDER BY username");
    $stmt->execute([':role' => $role]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getInstructorGrades($instructorId) {
    $db = getDB();
    $stmt = $db->prepare("SELECT g.*, u.username, u.fullname FROM grades g JOIN users u ON g.student_id = u.id WHERE g.instructor_id = :inst ORDER BY g.subject");
    $stmt->execute([':inst' => $instructorId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getAllUsers() {
    $db = getDB();
    return $db->query("SELECT * FROM users ORDER BY role, username")->fetchAll(PDO::FETCH_ASSOC);
}

function getAllGrades() {
    $db = getDB();
    $query = "SELECT g.*, s.fullname AS student_name, i.fullname AS instructor_name FROM grades g JOIN users s ON g.student_id = s.id JOIN users i ON g.instructor_id = i.id ORDER BY g.subject";
    $stmt = $db->query($query);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Logout form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit();
}

require_once 'header.php';
?>

<div style="padding: 20px;">
    <h2>Welcome to the School Grading System</h2>
    <div style="margin-top: 20px;">
        <h3>Your Information:</h3>
        <p><strong>Username:</strong> <?php echo htmlspecialchars($_SESSION['username']); ?></p>
        <p><strong>Role:</strong> <?php echo htmlspecialchars($_SESSION['role']); ?></p>
        <?php if ($_SESSION['fullname']): ?>
            <p><strong>Full Name:</strong> <?php echo htmlspecialchars($_SESSION['fullname']); ?></p>
        <?php endif; ?>
    </div>
</div>

<?php if ($user['role'] === 'student'): ?>
  <h1>Student Dashboard</h1>

  <?php
  // Display student info
  $info = getStudentInfo($user['id']);
  ?>
  <h2>Your Information</h2>
  <p><strong>Full Name:</strong> <?= e($info['fullname'] ?? '') ?></p>
  <p><strong>Email:</strong> <?= e($info['email'] ?? '') ?></p>

  <h2>Your Grades</h2>
  <?php
  $grades = getGradesForStudent($user['id']);
  if (!$grades) {
      echo "<p>No grades available yet.</p>";
  } else {
      echo '<table><thead><tr><th>Subject</th><th>Grade</th><th>Letter Grade</th><th>Instructor</th><th>Submitted</th></tr></thead><tbody>';
      foreach ($grades as $g) {
          $lg = letterGrade($g['grade']);
          echo '<tr>';
          echo '<td>' . e($g['subject']) . '</td>';
          echo '<td>' . e($g['grade']) . '</td>';
          echo "<td class=\"grade-$lg\">$lg</td>";
          echo '<td>' . e($g['instructor_name']) . '</td>';
          echo '<td>' . ($g['submitted'] ? 'Yes' : 'No') . '</td>';
          echo '</tr>';
      }
      echo '</tbody></table>';
  }
  ?>

<?php elseif ($user['role'] === 'instructor'): ?>
  <h1>Instructor Dashboard</h1>
  <p><a href="instructor.php">Go to Instructor page for grade input & submission</a></p>

<?php elseif ($user['role'] === 'admin'): ?>
  <h1>Admin Dashboard</h1>
  <p><a href="admin.php">Go to Admin page to manage users and view reports</a></p>

<?php else: ?>
  <h1>Unknown Role</h1>
  <p>Your assigned role is not supported.</p>
<?php endif; ?>

</body>
</html>
