<?php
session_start();
require_once 'config.php';

// Only allow instructors
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    header('Location: index.php');
    exit();
}

// --- FUNCTIONS ---
function getSubjectsForInstructor($db, $instructor_id) {
    $stmt = $db->prepare(
        "SELECT s.id, s.subject_code, s.subject_name, c.course_code, c.course_name
         FROM subjects s
         JOIN courses c ON s.course_id = c.id
         JOIN course_instructors ci ON ci.course_id = c.id
         WHERE ci.instructor_id = ?
         ORDER BY c.course_code, s.subject_code"
    );
    $stmt->execute([$instructor_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getStudentsForInstructor($db, $instructor_id, $subject_id = null) {
    $params = [$instructor_id];
    $subject_filter = '';
    $selected_course_id = null;
    if ($subject_id) {
        $stmt = $db->prepare("SELECT course_id FROM subjects WHERE id = ?");
        $stmt->execute([$subject_id]);
        $selected_course_id = $stmt->fetchColumn();
    }
    if ($selected_course_id) {
        $subject_filter = ' AND e.course_id = ?';
        $params[] = $selected_course_id;
    }
    $stmt = $db->prepare(
        "SELECT DISTINCT u.id, u.username, s.first_name, s.last_name, s.year_level, s.section
         FROM user_accounts u
         JOIN students s ON u.id = s.user_id
         JOIN enrollments e ON u.id = e.student_id
         JOIN courses c ON e.course_id = c.id
         JOIN course_instructors ci ON ci.course_id = c.id
         WHERE ci.instructor_id = ?" . $subject_filter . "
         ORDER BY s.last_name, s.first_name"
    );
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getGradeReport($db, $instructor_id, $subject_id = null, $student_id = null) {
    $query = "SELECT 
                u.username, 
                a.first_name, 
                a.last_name, 
                s.subject_code, 
                s.subject_name, 
                g.grade_value, 
                g.final_grade, 
                g.midterm_grade, 
                g.remarks
            FROM grades g
            JOIN students a ON g.student_id = a.id
            JOIN user_accounts u ON a.user_id = u.id
            JOIN courses c ON g.course_id = c.id
            JOIN course_instructors ci ON ci.course_id = c.id
            JOIN subjects s ON s.course_id = c.id
            WHERE ci.instructor_id = ?";
    $params = [$instructor_id];
    if ($subject_id) {
        $query .= " AND s.id = ?";
        $params[] = $subject_id;
    }
    if ($student_id) {
        $query .= " AND a.id = ?";
        $params[] = $student_id;
    }
    $query .= " ORDER BY s.subject_code, a.last_name, a.first_name";
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
// --- END FUNCTIONS ---

$report_types = [
    'grade' => 'Grade Report',
    'attendance' => 'Attendance Report',
    'progress' => 'Progress Report',
    'behavioral' => 'Behavioral/Conduct Report',
    'custom' => 'Custom Report'
];

$selected_report = $_GET['type'] ?? 'grade';

$db = getDB();
$subjects = getSubjectsForInstructor($db, $_SESSION['user_id']);
$students = getStudentsForInstructor($db, $_SESSION['user_id'], $_POST['subject'] ?? null);
$report_data = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['report_type'] === 'grade') {
    $report_data = getGradeReport($db, $_SESSION['user_id'], $_POST['subject'] ?? null, $_POST['student'] ?? null);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Reports - School Grading System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Roboto', Arial, sans-serif;
            background: linear-gradient(120deg, #e0eafc, #cfdef3 100%);
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 950px;
            margin: 40px auto;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(52, 152, 219, 0.10);
            padding: 0 0 40px 0;
        }
        .header-section {
            background: linear-gradient(90deg, #3498db 60%, #6dd5fa 100%);
            color: #fff;
            border-radius: 16px 16px 0 0;
            padding: 36px 40px 18px 40px;
            text-align: center;
        }
        .header-section h1 {
            margin: 0 0 8px 0;
            font-size: 2.3rem;
            font-weight: 700;
            letter-spacing: 1px;
        }
        .header-section p {
            margin: 0;
            font-size: 1.1rem;
            opacity: 0.95;
        }
        .steps-guide {
            background: #f1f7fd;
            border-left: 4px solid #3498db;
            margin: 0 40px 24px 40px;
            padding: 18px 24px;
            border-radius: 8px;
            font-size: 1.08rem;
            color: #34495e;
        }
        .tabs {
            display: flex;
            gap: 10px;
            margin: 0 40px 30px 40px;
            justify-content: center;
        }
        .tab {
            padding: 10px 22px;
            border-radius: 6px 6px 0 0;
            background: #eaf1fb;
            color: #2980b9;
            font-weight: 600;
            cursor: pointer;
            border: none;
            outline: none;
            transition: background 0.2s;
        }
        .tab.active {
            background: #3498db;
            color: #fff;
        }
        .report-section {
            background: #f8f9fa;
            border-radius: 0 0 16px 16px;
            padding: 32px 40px 30px 40px;
            min-height: 320px;
        }
        .filters {
            display: flex;
            gap: 28px;
            flex-wrap: wrap;
            margin-bottom: 24px;
            align-items: flex-end;
        }
        .filters label {
            font-weight: 600;
            color: #34495e;
            margin-bottom: 7px;
            display: block;
            font-size: 1.08rem;
        }
        .filters select, .filters input[type="date"] {
            padding: 10px 14px;
            border-radius: 6px;
            border: 1.5px solid #b2bec3;
            font-size: 1.08rem;
            background: #f8fafc;
            min-width: 220px;
        }
        .filters select:focus {
            border: 1.5px solid #3498db;
            outline: none;
            background: #fff;
        }
        .actions {
            margin-top: 0;
            display: flex;
            gap: 14px;
        }
        .actions button {
            background: #3498db;
            color: #fff;
            border: none;
            border-radius: 6px;
            padding: 12px 26px;
            font-size: 1.08rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
            box-shadow: 0 2px 8px rgba(52,152,219,0.08);
        }
        .actions button:hover {
            background: #217dbb;
        }
        .report-result {
            margin-top: 32px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            padding: 24px 18px;
            min-height: 120px;
        }
        .placeholder {
            color: #7f8c8d;
            font-style: italic;
            text-align: center;
            margin-top: 30px;
            font-size: 1.1rem;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            background: #fafdff;
            border-radius: 8px;
            overflow: hidden;
        }
        th, td {
            padding: 13px 12px;
            border: 1px solid #e1e1e1;
            text-align: left;
            font-size: 1.05rem;
        }
        th {
            background: #3498db;
            color: #fff;
            font-size: 1.08rem;
        }
        tr:nth-child(even) td {
            background: #f4f8fb;
        }
        @media (max-width: 900px) {
            .container, .header-section, .report-section, .steps-guide, .tabs {
                padding-left: 10px !important;
                padding-right: 10px !important;
                margin-left: 0 !important;
                margin-right: 0 !important;
            }
            .report-section {
                padding: 18px 6px 18px 6px;
            }
        }
        .top-bar {
            display: flex;
            align-items: center;
            padding: 24px 40px 0 40px;
            margin-bottom: 18px;
        }
        .back-btn {
            display: inline-flex;
            align-items: center;
            background: #3498db;
            color: #fff;
            padding: 10px 22px;
            border-radius: 6px;
            font-weight: 600;
            text-decoration: none;
            font-size: 1.08rem;
            box-shadow: 0 2px 8px rgba(52,152,219,0.08);
            transition: background 0.2s, box-shadow 0.2s;
            gap: 8px;
        }
        .back-btn:hover, .back-btn:focus {
            background: #217dbb;
            color: #fff;
            box-shadow: 0 4px 16px rgba(52,152,219,0.15);
            text-decoration: none;
        }
        @media (max-width: 900px) {
            .top-bar {
                padding-left: 10px !important;
                padding-right: 10px !important;
            }
        }
    </style>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
</head>
<body>
    <div class="container">
        <div class="top-bar">
            <a href="instructor_dashboard.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        </div>
        <div class="header-section">
            <h1><i class="fas fa-chart-line"></i> Create Reports</h1>
            <p>Gumawa at mag-export ng Grade, Attendance, Progress, o Custom Reports para sa iyong mga estudyante.</p>
        </div>
        <div class="steps-guide">
            <b>Step 1:</b> Piliin ang uri ng report sa itaas.<br>
            <b>Step 2:</b> Piliin ang Subject at/o Student na gusto mong i-generate ng report.<br>
            <b>Step 3:</b> I-click ang <b>Generate Report</b> para makita ang resulta.<br>
            <b>Step 4:</b> Pwede mo ring i-export ang report sa PDF, Excel, o i-print.
        </div>
        <div class="tabs">
            <?php foreach ($report_types as $type => $label): ?>
                <button class="tab<?php echo $selected_report === $type ? ' active' : ''; ?>" onclick="window.location.href='?type=<?php echo $type; ?>'">
                    <?php echo $label; ?>
                </button>
            <?php endforeach; ?>
        </div>
        <div style="text-align: right; margin: 0 40px 18px 40px;">
            <a href="input_grades.php" class="btn" style="background: #27ae60; color: #fff; padding: 12px 28px; border-radius: 7px; font-weight: 600; font-size: 1.08rem; text-decoration: none; box-shadow: 0 2px 8px rgba(39,174,96,0.08); transition: background 0.2s;">
                <i class="fas fa-pen"></i> Input Grades
            </a>
        </div>
        <div class="report-section">
            <?php if ($selected_report === 'grade'): ?>
            <form class="filters" method="post">
                <input type="hidden" name="report_type" value="grade">
                <label>Subject:
                    <select name="subject" id="subject-select">
                        <option value="">All Subjects</option>
                        <?php 
                        $last_course = '';
                        foreach ($subjects as $sub): 
                            if ($sub['course_code'] !== $last_course) {
                                if ($last_course !== '') echo '</optgroup>';
                                echo '<optgroup label="' . htmlspecialchars($sub['course_code'] . ' - ' . $sub['course_name']) . '">';
                                $last_course = $sub['course_code'];
                            }
                        ?>
                            <option value="<?php echo $sub['id']; ?>" data-year_level="<?php echo isset($sub['year_level']) ? $sub['year_level'] : ''; ?>" <?php if (isset($_POST['subject']) && $_POST['subject'] == $sub['id']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($sub['subject_code'] . ' - ' . $sub['subject_name']); ?>
                            </option>
                        <?php endforeach; if ($last_course !== '') echo '</optgroup>'; ?>
                    </select>
                </label>
                <label>Student:
                    <select name="student" id="student-select" style="width: 220px;">
                        <option value="">All Students</option>
                        <?php foreach ($students as $stu): ?>
                            <option value="<?php echo $stu['id']; ?>" <?php if (isset($_POST['student']) && $_POST['student'] == $stu['id']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($stu['last_name'] . ', ' . $stu['first_name'] . ' (' . $stu['year_level'] . ' - ' . $stu['section'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <div class="actions">
                    <button type="submit"><i class="fas fa-search"></i> Generate Report</button>
                    <button type="button"><i class="fas fa-file-pdf"></i> Export PDF</button>
                    <button type="button"><i class="fas fa-file-excel"></i> Export Excel</button>
                    <button type="button"><i class="fas fa-print"></i> Print</button>
                </div>
            </form>
            <div class="report-result">
                <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['report_type'] === 'grade'): ?>
                    <?php if (empty($report_data)): ?>
                        <div class="placeholder">No grade data found for the selected filters.</div>
                    <?php else: ?>
                        <table>
                            <tr>
                                <th>Student</th>
                                <th>Subject</th>
                                <th>Grade Value</th>
                                <th>Final Grade</th>
                                <th>Midterm Grade</th>
                                <th>Remarks</th>
                            </tr>
                            <?php foreach ($report_data as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars(($row['last_name'] ?? $row['username']) . ', ' . ($row['first_name'] ?? '')); ?></td>
                                    <td><?php echo htmlspecialchars($row['subject_code'] . ' - ' . $row['subject_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['grade_value']); ?></td>
                                    <td><?php echo htmlspecialchars($row['final_grade']); ?></td>
                                    <td><?php echo htmlspecialchars($row['midterm_grade']); ?></td>
                                    <td><?php echo htmlspecialchars($row['remarks']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="placeholder">Grade report results will appear here.</div>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <form class="filters">
                <div class="actions">
                    <button type="button"><i class="fas fa-search"></i> Generate Report</button>
                    <button type="button"><i class="fas fa-file-pdf"></i> Export PDF</button>
                    <button type="button"><i class="fas fa-file-excel"></i> Export Excel</button>
                    <button type="button"><i class="fas fa-print"></i> Print</button>
                </div>
            </form>
            <div class="report-result">
                <div class="placeholder">
                    <?php
                    switch ($selected_report) {
                        case 'attendance':
                            echo 'Attendance report results will appear here.';
                            break;
                        case 'progress':
                            echo 'Progress report results will appear here.';
                            break;
                        case 'behavioral':
                            echo 'Behavioral/conduct report results will appear here.';
                            break;
                        case 'custom':
                            echo 'Custom report results will appear here.';
                            break;
                    }
                    ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
    $(document).ready(function() {
        $('#student-select').select2();
        $('#subject-select').select2();

        // Store student year levels for quick lookup
        var studentYearLevels = {};
        $('#student-select option').each(function() {
            var val = $(this).val();
            var text = $(this).text();
            var match = text.match(/\((\d+) -/); // Extract year_level from "(2 - Section)"
            if (val && match) {
                studentYearLevels[val] = match[1];
            }
        });

        function filterSubjectsByYearLevel(yearLevel) {
            $('#subject-select option').each(function() {
                var optYear = $(this).data('year_level');
                if (!yearLevel || !optYear || optYear == yearLevel) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
            $('#subject-select').val('');
            $('#subject-select').trigger('change.select2');
        }

        $('#student-select').on('change', function() {
            var studentId = $(this).val();
            var yearLevel = studentYearLevels[studentId] || '';
            filterSubjectsByYearLevel(yearLevel);
        });

        // On page load, if a student is already selected, filter subjects
        var initialStudent = $('#student-select').val();
        if (initialStudent && studentYearLevels[initialStudent]) {
            filterSubjectsByYearLevel(studentYearLevels[initialStudent]);
        }
    });
    </script>
</body>
</html> 