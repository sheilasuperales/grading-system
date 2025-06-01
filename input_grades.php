<?php
session_start();
require_once 'config.php';

// Only allow instructors
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    header('Location: index.php');
    exit();
}

$db = getDB();
$instructor_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Fetch subjects assigned to this instructor
$subjects = $db->prepare("SELECT s.id, s.subject_code, s.subject_name, c.course_code, c.course_name FROM subjects s JOIN courses c ON s.course_id = c.id JOIN course_instructors ci ON ci.course_id = c.id WHERE ci.instructor_id = ? ORDER BY c.course_code, s.subject_code");
$subjects->execute([$instructor_id]);
$subjects = $subjects->fetchAll(PDO::FETCH_ASSOC);

$selected_subject_id = $_POST['subject'] ?? '';
$students = [];
if ($selected_subject_id) {
    // Get course_id for the selected subject
    $stmt = $db->prepare("SELECT course_id FROM subjects WHERE id = ?");
    $stmt->execute([$selected_subject_id]);
    $course_id = $stmt->fetchColumn();
    // Get students enrolled in this course
    $students = $db->prepare("SELECT s.id, s.first_name, s.last_name, s.year_level, s.section, u.username FROM students s JOIN user_accounts u ON s.user_id = u.id JOIN enrollments e ON s.id = e.student_id WHERE e.course_id = ?");
    $students->execute([$course_id]);
    $students = $students->fetchAll(PDO::FETCH_ASSOC);
    // Fetch existing grades for this subject/course
    $grades_map = [];
    $grades_stmt = $db->prepare("SELECT * FROM grades WHERE course_id = ?");
    $grades_stmt->execute([$course_id]);
    foreach ($grades_stmt->fetchAll(PDO::FETCH_ASSOC) as $g) {
        $grades_map[$g['student_id']] = $g;
    }
}

// Handle grade submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_grades']) && $selected_subject_id) {
    $grades = $_POST['grades'] ?? [];
    foreach ($grades as $student_id => $grade_data) {
        $midterm = $grade_data['midterm'] !== '' ? $grade_data['midterm'] : null;
        $final = $grade_data['final'] !== '' ? $grade_data['final'] : null;
        $remarks = trim($grade_data['remarks'] ?? '');
        // Check if grade already exists
        $stmt = $db->prepare("SELECT id FROM grades WHERE student_id = ? AND course_id = ?");
        $stmt->execute([$student_id, $course_id]);
        $existing = $stmt->fetchColumn();
        if ($existing) {
            // Update
            $stmt = $db->prepare("UPDATE grades SET midterm_grade=?, final_grade=?, remarks=? WHERE id=?");
            $stmt->execute([$midterm, $final, $remarks, $existing]);
        } else {
            // Insert
            $stmt = $db->prepare("INSERT INTO grades (student_id, course_id, midterm_grade, final_grade, remarks) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$student_id, $course_id, $midterm, $final, $remarks]);
        }
    }
    $success = 'Grades saved successfully!';
    // Refresh students list
    header('Location: input_grades.php?subject=' . $selected_subject_id . '&success=1');
    exit();
}
if (isset($_GET['success'])) {
    $success = 'Grades saved successfully!';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Input Grades - Instructor</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        body { font-family: 'Roboto', Arial, sans-serif; background: #f5f7fa; margin: 0; padding: 0; }
        .container { max-width: 900px; margin: 40px auto; background: #fff; border-radius: 14px; box-shadow: 0 8px 32px rgba(52, 152, 219, 0.10); padding: 0 0 40px 0; }
        .header-section { background: linear-gradient(90deg, #3498db 60%, #6dd5fa 100%); color: #fff; border-radius: 14px 14px 0 0; padding: 32px 40px 18px 40px; text-align: center; }
        .header-section h1 { margin: 0 0 8px 0; font-size: 2.1rem; font-weight: 700; letter-spacing: 1px; }
        .header-section p { margin: 0; font-size: 1.1rem; opacity: 0.95; }
        .form-section { padding: 32px 40px 0 40px; }
        .form-group { margin-bottom: 22px; }
        label { font-weight: 600; color: #34495e; margin-bottom: 7px; display: block; font-size: 1.08rem; }
        select, input[type="number"], input[type="text"] { padding: 10px 14px; border-radius: 6px; border: 1.5px solid #b2bec3; font-size: 1.08rem; background: #f8fafc; min-width: 220px; }
        select:focus, input:focus { border: 1.5px solid #3498db; outline: none; background: #fff; }
        .btn { background: #3498db; color: #fff; border: none; border-radius: 6px; padding: 12px 26px; font-size: 1.08rem; font-weight: 600; cursor: pointer; transition: background 0.2s; box-shadow: 0 2px 8px rgba(52,152,219,0.08); }
        .btn:hover { background: #217dbb; }
        .success { background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin-bottom: 15px; text-align: center; }
        .error { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin-bottom: 15px; text-align: center; }
        table { width: 100%; border-collapse: collapse; margin-top: 18px; background: #fafdff; border-radius: 8px; overflow: hidden; }
        th, td { padding: 13px 12px; border: 1px solid #e1e1e1; text-align: left; font-size: 1.05rem; }
        th { background: #3498db; color: #fff; font-size: 1.08rem; }
        tr:nth-child(even) td { background: #f4f8fb; }
        .input-cell { padding: 0; }
        .input-cell input { width: 100%; border: none; background: transparent; padding: 10px 12px; }
        .input-cell input:focus { background: #fff; border: 1.5px solid #3498db; }
        @media (max-width: 900px) { .container, .header-section, .form-section { padding-left: 10px !important; padding-right: 10px !important; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-section">
            <h1><i class="fas fa-pen"></i> Input Grades</h1>
            <p>Ilagay ang grades ng iyong mga estudyante para sa napiling subject.</p>
        </div>
        <div class="form-section">
            <?php if ($error): ?><div class="error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
            <?php if ($success): ?><div class="success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
            <form method="post" action="input_grades.php" style="display: flex; gap: 24px; align-items: flex-end;">
                <div class="form-group" style="flex: 1;">
                    <label for="subject">Subject:</label>
                    <select name="subject" id="subject" required onchange="this.form.submit()" style="width: 100%;">
                        <option value="">-- Piliin ang Subject --</option>
                        <?php foreach ($subjects as $sub): ?>
                            <option value="<?php echo $sub['id']; ?>" <?php if ($selected_subject_id == $sub['id']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($sub['subject_code'] . ' - ' . $sub['subject_name']); ?> (<?php echo htmlspecialchars($sub['course_code']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($selected_subject_id && $students): ?>
                <div class="form-group" style="flex: 1;">
                    <label for="student_filter">Student:</label>
                    <select id="student_filter" style="width: 100%;">
                        <option value="">-- All Students --</option>
                        <?php foreach ($students as $stu): ?>
                            <option value="student-<?php echo $stu['id']; ?>"><?php echo htmlspecialchars($stu['last_name'] . ', ' . $stu['first_name'] . ' (' . $stu['username'] . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
            </form>
            <?php if ($selected_subject_id && $students): ?>
            <form method="post" action="input_grades.php">
                <input type="hidden" name="subject" value="<?php echo htmlspecialchars($selected_subject_id); ?>">
                <div class="form-group">
                    <label for="student_filter">Filter Student:</label>
                    <select id="student_filter" style="width: 100%; min-width: 220px;">
                        <option value="">-- All Students --</option>
                        <?php foreach ($students as $stu): ?>
                            <option value="student-<?php echo $stu['id']; ?>"><?php echo htmlspecialchars($stu['last_name'] . ', ' . $stu['first_name'] . ' (' . $stu['username'] . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <table>
                    <tr>
                        <th>Student</th>
                        <th>Year Level</th>
                        <th>Section</th>
                        <th>Midterm Grade</th>
                        <th>Final Grade</th>
                        <th>Remarks</th>
                    </tr>
                    <?php foreach ($students as $stu): ?>
                    <tr class="student-row student-<?php echo $stu['id']; ?>">
                        <td><?php echo htmlspecialchars($stu['last_name'] . ', ' . $stu['first_name'] . ' (' . $stu['username'] . ')'); ?></td>
                        <td><?php echo htmlspecialchars($stu['year_level']); ?></td>
                        <td><?php echo htmlspecialchars($stu['section']); ?></td>
                        <td class="input-cell"><input type="number" step="0.01" name="grades[<?php echo $stu['id']; ?>][midterm]" min="0" max="100" value="<?php echo isset($grades_map[$stu['id']]) ? htmlspecialchars($grades_map[$stu['id']]['midterm_grade']) : ''; ?>"></td>
                        <td class="input-cell"><input type="number" step="0.01" name="grades[<?php echo $stu['id']; ?>][final]" min="0" max="100" value="<?php echo isset($grades_map[$stu['id']]) ? htmlspecialchars($grades_map[$stu['id']]['final_grade']) : ''; ?>"></td>
                        <td class="input-cell"><input type="text" name="grades[<?php echo $stu['id']; ?>][remarks]" value="<?php echo isset($grades_map[$stu['id']]) ? htmlspecialchars($grades_map[$stu['id']]['remarks']) : ''; ?>"></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
                <div style="margin-top: 18px; text-align: right;">
                    <button type="submit" name="save_grades" class="btn"><i class="fas fa-save"></i> Save Grades</button>
                </div>
            </form>
            <?php elseif ($selected_subject_id): ?>
                <div class="error">Walang estudyanteng naka-enroll sa subject na ito.</div>
            <?php endif; ?>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
    $(document).ready(function() {
        $('#subject').select2({
            placeholder: '-- Piliin ang Subject --',
            allowClear: true,
            width: 'resolve'
        });
        $('#student_filter').select2({
            placeholder: '-- All Students --',
            allowClear: true,
            width: 'resolve'
        });
        $('#student_filter').on('change', function() {
            var selected = $(this).val();
            if (!selected) {
                $('.student-row').show();
            } else {
                $('.student-row').hide();
                $('.' + selected).show();
            }
        });
    });
    </script>
</body>
</html> 