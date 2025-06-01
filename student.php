<?php
session_start();

define('DB_FILE', __DIR__.'/grading_system.sqlite');

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'student') {
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

function getStudentInfo($studentId) {
    $db = getDB();
    $stmt = $db->prepare("SELECT fullname, email FROM users WHERE id = :id AND role='student'");
    $stmt->execute([':id' => $studentId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function saveStudentInfo($studentId, $fullname, $email) {
    $db = getDB();
    $stmt = $db->prepare("UPDATE users SET fullname = :fullname, email = :email WHERE id = :id AND role='student'");
    $stmt->execute([':fullname' => $fullname, ':email' => $email, ':id' => $studentId]);
}

function getGradesForStudent($studentId) {
    $db = getDB();
    $stmt = $db->prepare("SELECT g.*, u.fullname AS instructor_name FROM grades g JOIN users u ON g.instructor_id = u.id WHERE g.student_id = :student_id ORDER BY g.subject");
    $stmt->execute([':student_id' => $studentId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function letterGrade($grade) {
    if ($grade >= 90) return 'A';
    if ($grade >= 80) return 'B';
    if ($grade >= 70) return 'C';
    if ($grade >= 60) return 'D';
    return 'F';
}

$messages = [];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_student_info'])) {
    $fullname = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    if ($fullname === '' || $email === '') {
        $errors[] = "Full name and email cannot be empty.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    } else {
        saveStudentInfo($user['id'], $fullname, $email);
        $_SESSION['user']['fullname'] = $fullname;
        $_SESSION['user']['email'] = $email;
        $messages[] = "Your information has been updated successfully.";
    }
}

// Logout
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit();
}

$info = getStudentInfo($user['id']);
$grades = getGradesForStudent($user['id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Student Dashboard - School Grading System</title>
<style>
  body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: #f5f7fa;
    color: #333;
    margin: 0;
    padding: 20px;
  }
  .container {
    max-width: 750px;
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
  input[type="text"], input[type="email"] {
    width: 100%;
    padding: 8px 10px;
    margin-top: 6px;
    border-radius: 6px;
    border: 1px solid #ccc;
    font-size: 16px;
    box-sizing: border-box;
  }
  input[type="submit"] {
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
  input[type="submit"]:hover {
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
    padding: 15px 20px;
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
    margin-bottom: 15px;
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
    <span>Logged in as <b><?= e($user['username']) ?></b> (Student)</span>
    <form method="post" style="display:inline;">
      <button type="submit" name="logout">Logout</button>
    </form>
  </nav>

  <h1>Student Information</h1>
  <?php
  if (!empty($messages)) {
      echo '<div class="alert success">';
      foreach ($messages as $msg) {
          echo '<div>' . e($msg) . '</div>';
      }
      echo '</div>';
  }
  if (!empty($errors)) {
      echo '<div class="alert error">';
      foreach ($errors as $err) {
          echo '<div>' . e($err) . '</div>';
      }
      echo '</div>';
  }
  ?>

  <form method="post" novalidate>
    <label for="fullname">Full Name:</label>
    <input type="text" id="fullname" name="fullname" required value="<?= e($info['fullname'] ?? '') ?>" />
    <label for="email">Email:</label>
    <input type="email" id="email" name="email" required value="<?= e($info['email'] ?? '') ?>" />
    <input type="submit" name="save_student_info" value="Save Information" />
  </form>

  <h2>Your Grades</h2>
  <?php
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
</div>
</body>
</html>
