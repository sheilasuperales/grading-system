<?php
session_start();

define('DB_FILE', __DIR__.'/grading_system.sqlite');

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'instructor') {
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

function getUsersByRole($role) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE role = :role ORDER BY username");
    $stmt->execute([':role' => $role]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function addOrUpdateGrade($instructorId, $studentId, $subject, $grade) {
    $db = getDB();
    if ($grade < 0 || $grade > 100) return "Grade must be between 0 and 100";
    $stmt = $db->prepare("SELECT * FROM grades WHERE instructor_id = :instructor AND student_id = :student AND subject = :subject");
    $stmt->execute([':instructor' => $instructorId, ':student' => $studentId, ':subject' => $subject]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($existing) {
        if ($existing['submitted']) {
            return "Cannot modify a submitted grade.";
        }
        $stmt = $db->prepare("UPDATE grades SET grade = :grade WHERE id = :id");
        $stmt->execute([':grade' => $grade, ':id' => $existing['id']]);
    } else {
        $stmt = $db->prepare("INSERT INTO grades (student_id, instructor_id, subject, grade) VALUES (:student, :instructor, :subject, :grade)");
        $stmt->execute([':student' => $studentId, ':instructor' => $instructorId, ':subject' => $subject, ':grade' => $grade]);
    }
    return true;
}

function getInstructorGrades($instructorId) {
    $db = getDB();
    $stmt = $db->prepare("SELECT g.*, u.username, u.fullname FROM grades g JOIN users u ON g.student_id = u.id WHERE g.instructor_id = :inst ORDER BY g.subject");
    $stmt->execute([':inst' => $instructorId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function submitGrades($instructorId) {
    $db = getDB();
    $stmt = $db->prepare("UPDATE grades SET submitted = 1 WHERE instructor_id = :instructor AND submitted = 0");
    $stmt->execute([':instructor' => $instructorId]);
}

// Logout
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['logout'])) {
        session_destroy();
        header("Location: index.php");
        exit();
    }

    if (isset($_POST['submit_grade'])) {
        $student_id = intval($_POST['student_id'] ?? 0);
        $subject = trim($_POST['subject'] ?? '');
        $grade = floatval($_POST['grade'] ?? -1);
        if (!$student_id || $subject === '' || $grade < 0 || $grade > 100) {
            $error = "Please enter valid student, subject and grade (0-100).";
        } else {
            $result = addOrUpdateGrade($user['id'], $student_id, $subject, $grade);
            if ($result === true) {
                $message = "Grade saved successfully.";
            } else {
                $error = $result;
            }
        }
    }

    if (isset($_POST['submit_to_admin'])) {
        submitGrades($user['id']);
        $message = "All your grades have been submitted to admin and are now locked.";
    }
}

$students = getUsersByRole('student');
$grades = getInstructorGrades($user['id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Instructor Dashboard - School Grading System</title>
<style>
  body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: #f5f7fa;
    color: #333;
    margin: 0;
    padding: 20px;
  }
  .container {
    max-width: 850px;
    margin: 0 auto;
    background: white;
    padding: 20px 30px;
    border-radius: 12px;
    box-shadow: 0 6px 15px rgba(74,144,226,0.25);
  }
  nav {
    margin-bottom: 30px;
    text-align: right;
  }
  nav form {
    display: inline;
  }
  nav span {
    font-weight: 600;
    margin-right: 15px;
    font-size: 16px;
  }
  nav button {
    background:#4a90e2;
    border:none;
    color: white;
    padding: 10px 16px;
    font-size: 16px;
    cursor: pointer;
    border-radius: 8px;
    font-weight: 600;
    transition: background 0.3s ease;
  }
  nav button:hover {
    background: #357abd;
  }
  h1, h2 {
    color: #4a90e2;
    margin-top: 0;
  }
  form {
    margin-bottom: 25px;
  }
  label {
    display: block;
    font-weight: 600;
    margin-top: 12px;
  }
  select, input[type="text"], input[type="number"] {
    width: 100%;
    padding: 8px 10px;
    margin-top: 6px;
    border-radius: 6px;
    border: 1px solid #ccc;
    font-size: 16px;
    box-sizing: border-box;
  }
  input[type="submit"], button {
    margin-top: 18px;
    background: #4a90e2;
    color: white;
    border: none;
    padding: 12px 22px;
    font-size: 17px;
    border-radius: 8px;
    cursor: pointer;
    transition: background 0.3s ease;
  }
  input[type="submit"]:hover, button:hover {
    background: #357abd;
  }
  table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 16px;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 4px 10px rgba(74,144,226,0.1);
  }
  th, td {
    padding: 15px 18px;
    text-align: left;
    border-bottom: 1px solid #eee;
  }
  th {
    background: #4a90e2;
    color: white;
  }
  tr:nth-child(even) {
    background: #f0f6ff;
  }
  .grade-A { color: #2e7d32; font-weight: bold; }
  .grade-B { color: #388e3c; font-weight: bold; }
  .grade-C { color: #f9a825; font-weight: bold; }
  .grade-D { color: #ef6c00; font-weight: bold; }
  .grade-F { color: #c62828; font-weight: bold; }
  .alert {
    padding: 14px 20px;
    margin-bottom: 20px;
    border-radius: 8px;
  }
  .alert.success {
    background: #d4edda;
    color: #155724;
  }
  .alert.error {
    background: #f8d7da;
    color: #721c24;
  }
</style>
</head>
<body>
<div class="container">
  <nav>
    <span>Logged in as <b><?= e($user['username']) ?></b> (Instructor)</span>
    <form method="post" style="display:inline;">
      <button type="submit" name="logout">Logout</button>
    </form>
  </nav>

  <h1>Input or Update Grades</h1>

  <?php if (!empty($message)): ?>
    <div class="alert success"><?= e($message) ?></div>
  <?php endif; ?>

  <?php if (!empty($error)): ?>
    <div class="alert error"><?= e($error) ?></div>
  <?php endif; ?>

  <form method="post" novalidate>
    <label for="student_id">Select Student:</label>
    <select id="student_id" name="student_id" required>
      <option value="" disabled selected>Select student</option>
      <?php foreach($students as $s): ?>
        <option value="<?= e($s['id']) ?>"><?= e($s['username'] . ' - ' . $s['fullname']) ?></option>
      <?php endforeach; ?>
    </select>
    
    <label for="subject">Subject Name:</label>
    <input type="text" id="subject" name="subject" placeholder="e.g. Mathematics" required />

    <label for="grade">Grade (0-100):</label>
    <input type="number" id="grade" name="grade" min="0" max="100" step="0.01" required />

    <input type="submit" name="submit_grade" value="Save Grade" />
  </form>

  <h2>Your Entered Grades</h2>
  <?php if (!$grades): ?>
    <p>You have not entered any grades yet.</p>
  <?php else: ?>
  <table>
    <thead>
      <tr>
        <th>Student</th>
        <th>Subject</th>
        <th>Grade</th>
        <th>Letter Grade</th>
        <th>Submitted</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($grades as $g): 
        $lg = letterGrade($g['grade']);
    ?>
      <tr>
        <td><?= e($g['username']) ?> - <?= e($g['fullname']) ?></td>
        <td><?= e($g['subject']) ?></td>
        <td><?= e($g['grade']) ?></td>
        <td class="grade-<?= $lg ?>"><?= $lg ?></td>
        <td><?= $g['submitted'] ? 'Yes' : 'No' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>

  <?php
  // Check if there are any unsubmitted grades
  $unsubmittedGrades = array_filter($grades, fn($g) => !$g['submitted']);
  if (count($unsubmittedGrades) > 0):
  ?>
    <form method="post" onsubmit="return confirm('Are you sure you want to submit all your grades to admin? This action cannot be undone and grades will be locked.')">
      <input type="submit" name="submit_to_admin" value="Submit All Grades to Admin" />
    </form>
  <?php endif; ?>

</div>
</body>
</html>
