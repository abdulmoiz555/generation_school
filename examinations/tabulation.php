<?php
/**
 * Master Examination Tabulation Sheet
 * Displays all students in a class across all subjects with totals, percentages, grades and ranks
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

requirePermission('exam_view');

$examId = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : null;
$classId = isset($_GET['class_id']) ? (int)$_GET['class_id'] : null;
$sectionId = isset($_GET['section_id']) ? (int)$_GET['section_id'] : null;

$exams = $pdo->query("SELECT * FROM exams ORDER BY id DESC")->fetchAll();
$classes = $pdo->query("SELECT * FROM classes ORDER BY numeric_level ASC")->fetchAll();

if (!$examId && !empty($exams)) {
    $examId = $exams[0]['id'];
}
if (!$classId && !empty($classes)) {
    $classId = $classes[0]['id'];
}

$sections = [];
if ($classId) {
    $stmt = $pdo->prepare("SELECT * FROM sections WHERE class_id = ? ORDER BY section_name ASC");
    $stmt->execute([$classId]);
    $sections = $stmt->fetchAll();
}

$selectedExam = null;
if ($examId) {
    $stmt = $pdo->prepare("SELECT e.*, t.name as type_name FROM exams e LEFT JOIN exam_types t ON e.exam_type_id = t.id WHERE e.id = ?");
    $stmt->execute([$examId]);
    $selectedExam = $stmt->fetch();
}

$selectedClass = null;
if ($classId) {
    $stmt = $pdo->prepare("SELECT * FROM classes WHERE id = ?");
    $stmt->execute([$classId]);
    $selectedClass = $stmt->fetch();
}

// Fetch subjects and exam_subjects for this class and exam
$subjects = [];
$examSubjects = [];
if ($examId && $classId) {
    $stmt = $pdo->prepare("
        SELECT es.id as exam_subject_id, es.max_marks, es.pass_marks, s.id as subject_id, s.subject_name
        FROM exam_subjects es
        JOIN subjects s ON es.subject_id = s.id
        WHERE es.exam_id = ? AND es.class_id = ?
        ORDER BY s.subject_name ASC
    ");
    $stmt->execute([$examId, $classId]);
    $subjects = $stmt->fetchAll();
}

// Fetch students
$students = [];
if ($classId) {
    $query = "SELECT s.*, sec.section_name FROM students s LEFT JOIN sections sec ON s.section_id = sec.id WHERE s.class_id = ? AND s.status = 'active'";
    $params = [$classId];
    if ($sectionId) {
        $query .= " AND s.section_id = ?";
        $params[] = $sectionId;
    }
    $query .= " ORDER BY s.roll_no ASC, s.first_name ASC";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $students = $stmt->fetchAll();
}

// Fetch marks for this exam
$marksMap = []; // student_id -> exam_subject_id -> marks row
if ($examId && !empty($subjects)) {
    $stmt = $pdo->prepare("
        SELECT m.* 
        FROM marks m
        WHERE m.exam_id = ?
    ");
    $stmt->execute([$examId]);
    $allMarks = $stmt->fetchAll();
    foreach ($allMarks as $m) {
        $marksMap[$m['student_id']][$m['exam_subject_id']] = $m;
    }
}

// Grading rules
$gradingRules = $pdo->query("SELECT * FROM grades ORDER BY min_percentage DESC")->fetchAll();

function calculateGradeFromRules($percentage, $rules) {
    foreach ($rules as $r) {
        if ($percentage >= $r['min_percentage'] && $percentage <= $r['max_percentage']) {
            return $r['grade_name'];
        }
    }
    return $percentage >= 50 ? 'C' : 'F';
}

// Precalculate totals for ranks
$studentStats = [];
$totalMaxMarks = 0;
foreach ($subjects as $sub) {
    $totalMaxMarks += ($sub['max_marks'] ?: 100);
}

foreach ($students as $stu) {
    $obtainedTotal = 0;
    $hasFailed = false;

    foreach ($subjects as $sub) {
        $m = $marksMap[$stu['id']][$sub['exam_subject_id']] ?? null;
        if ($m) {
            $obtained = (float)$m['marks_obtained'];
            $obtainedTotal += $obtained;
            $passMarks = (float)($sub['pass_marks'] ?: 40);
            if ($obtained < $passMarks) {
                $hasFailed = true;
            }
        }
    }

    $pct = $totalMaxMarks > 0 ? round(($obtainedTotal / $totalMaxMarks) * 100, 2) : 0;
    $grade = calculateGradeFromRules($pct, $gradingRules);

    $studentStats[$stu['id']] = [
        'total' => $obtainedTotal,
        'percentage' => $pct,
        'grade' => $grade,
        'hasFailed' => $hasFailed,
        'rank' => 1
    ];
}

// Compute rank based on total
$sortedTotals = [];
foreach ($studentStats as $sId => $st) {
    $sortedTotals[$sId] = $st['total'];
}
arsort($sortedTotals);
$currentRank = 1;
foreach ($sortedTotals as $sId => $tot) {
    $studentStats[$sId]['rank'] = $currentRank++;
}

// Handle Excel Export for Tabulation Sheet
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    $className = $selectedClass['class_name'] ?? 'Class';
    $examTitle = $selectedExam['title'] ?? 'Exam';
    $cleanFilename = "Tabulation_" . preg_replace('/[^a-zA-Z0-9_-]/', '_', $className . "_" . $examTitle) . "_" . date('Ymd') . ".csv";
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $cleanFilename . '"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
    
    fputcsv($out, ['GENERATION MODEL SCHOOL - MASTER EXAMINATION TABULATION SHEET']);
    fputcsv($out, ['Examination', $examTitle]);
    fputcsv($out, ['Class', $className]);
    fputcsv($out, ['Section', $sectionId ? ($sections[0]['section_name'] ?? 'All') : 'All Sections']);
    fputcsv($out, ['Total Students', count($students)]);
    fputcsv($out, ['Export Date', date('Y-m-d H:i:s')]);
    fputcsv($out, []); // empty line
    
    // Headers
    $headers = ['Rank', 'Roll No', 'Adm No', 'Student Name'];
    foreach ($subjects as $sub) {
        $headers[] = $sub['subject_name'] . ' (' . ($sub['max_marks'] ?: 100) . ')';
    }
    $headers[] = 'Grand Total';
    $headers[] = 'Max Marks';
    $headers[] = 'Percentage (%)';
    $headers[] = 'Grade';
    $headers[] = 'Status';
    fputcsv($out, $headers);
    
    // Rows sorted by rank
    $rankedStudents = $students;
    usort($rankedStudents, function($a, $b) use ($studentStats) {
        return ($studentStats[$a['id']]['rank'] ?? 999) <=> ($studentStats[$b['id']]['rank'] ?? 999);
    });
    
    foreach ($rankedStudents as $stu) {
        $st = $studentStats[$stu['id']];
        $row = [
            $st['rank'],
            $stu['roll_no'] ?: '-',
            $stu['admission_no'],
            $stu['first_name'] . ' ' . $stu['last_name']
        ];
        
        foreach ($subjects as $sub) {
            $m = $marksMap[$stu['id']][$sub['exam_subject_id']] ?? null;
            $row[] = $m ? $m['marks_obtained'] : '-';
        }
        
        $row[] = $st['total'];
        $row[] = $totalMaxMarks;
        $row[] = $st['percentage'] . '%';
        $row[] = $st['grade'];
        $row[] = $st['hasFailed'] ? 'FAIL' : 'PASS';
        
        fputcsv($out, $row);
    }
    
    fclose($out);
    exit;
}

$schoolName = getSetting('school_name', 'Generation Model School');
$pageTitle = "Examination Tabulation Sheet";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3 no-print">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/examinations/exams.php">Examinations</a></li>
                <li class="breadcrumb-item active">Tabulation Sheet</li>
            </ol>
        </nav>
        <h3 class="fw-bold text-dark mb-0"><i class="fas fa-table text-primary me-2"></i> Master Tabulation Sheet</h3>
        <p class="text-muted small mb-0">Consolidated class marksheet across all subjects with rank and GPA</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="?exam_id=<?= $examId ?>&class_id=<?= $classId ?>&section_id=<?= $sectionId ?>&export=excel" class="btn btn-success rounded-pill px-3 shadow-sm">
            <i class="fas fa-file-excel me-1"></i> Download Excel
        </a>
        <button type="button" onclick="window.print()" class="btn btn-primary rounded-pill px-4 shadow-sm">
            <i class="fas fa-print me-2"></i> Print Tabulation
        </button>
        <a href="<?= BASE_PATH ?>/examinations/marks.php" class="btn btn-outline-secondary rounded-pill px-3">
            <i class="fas fa-pen me-1"></i> Enter Marks
        </a>
    </div>
</div>

<!-- Filter card -->
<div class="card border-0 shadow-sm rounded-4 mb-4 no-print">
    <div class="card-body p-3">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-md-4">
                <label class="small text-muted mb-1 fw-bold">Select Examination</label>
                <select name="exam_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php foreach ($exams as $ex): ?>
                        <option value="<?= $ex['id'] ?>" <?= $examId == $ex['id'] ? 'selected' : '' ?>>
                            <?= e($ex['title']) ?> (<?= e($ex['start_date']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="small text-muted mb-1 fw-bold">Select Class</label>
                <select name="class_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php foreach ($classes as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $classId == $c['id'] ? 'selected' : '' ?>>
                            <?= e($c['class_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="small text-muted mb-1 fw-bold">Section (Optional)</label>
                <select name="section_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">-- All Sections --</option>
                    <?php foreach ($sections as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= $sectionId == $s['id'] ? 'selected' : '' ?>>
                            Section <?= e($s['section_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-dark btn-sm w-100"><i class="fas fa-filter me-1"></i> Filter</button>
            </div>
        </form>
    </div>
</div>

<!-- Print Header (Visible only when printing) -->
<div class="d-none d-print-block text-center mb-4">
    <h3 class="fw-bold mb-1 text-uppercase"><?= e($schoolName) ?></h3>
    <h5 class="fw-bold text-primary mb-1">CONSOLIDATED TABULATION SHEET</h5>
    <div class="small text-muted">
        <strong>Exam:</strong> <?= e($selectedExam['title'] ?? 'N/A') ?> | 
        <strong>Class:</strong> <?= e($selectedClass['class_name'] ?? 'N/A') ?> <?= $sectionId ? ' - Section ' . e($sections[0]['section_name'] ?? '') : '' ?> | 
        <strong>Date:</strong> <?= date('F d, Y') ?>
    </div>
    <hr class="my-2">
</div>

<!-- Tabulation Table -->
<div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
    <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
        <div>
            <h6 class="fw-bold text-dark mb-0">
                <?= e($selectedExam['title'] ?? 'Examination') ?> &bull; <?= e($selectedClass['class_name'] ?? 'Class') ?>
            </h6>
            <span class="text-muted small">Total Students: <?= count($students) ?> &bull; Scheduled Subjects: <?= count($subjects) ?></span>
        </div>
        <div class="no-print">
            <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2">
                Max Marks: <?= $totalMaxMarks ?>
            </span>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle mb-0 text-center" style="font-size: 0.85rem;">
            <thead class="table-light">
                <tr>
                    <th rowspan="2" class="align-middle" style="width: 50px;">Roll</th>
                    <th rowspan="2" class="align-middle text-start" style="min-width: 170px;">Student Name</th>
                    <?php foreach ($subjects as $sub): ?>
                        <th class="align-middle">
                            <?= e($sub['subject_name']) ?>
                            <div class="text-muted" style="font-size: 0.7rem; font-weight: normal;">(<?= (int)($sub['max_marks'] ?: 100) ?>)</div>
                        </th>
                    <?php endforeach; ?>
                    <th rowspan="2" class="align-middle table-primary" style="width: 75px;">Total<br><small>/<?= $totalMaxMarks ?></small></th>
                    <th rowspan="2" class="align-middle table-info" style="width: 70px;">%</th>
                    <th rowspan="2" class="align-middle" style="width: 65px;">Grade</th>
                    <th rowspan="2" class="align-middle" style="width: 65px;">Rank</th>
                    <th rowspan="2" class="align-middle" style="width: 75px;">Status</th>
                    <th rowspan="2" class="align-middle no-print" style="width: 80px;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($students)): ?>
                    <tr>
                        <td colspan="<?= count($subjects) + 8 ?>" class="text-muted py-4">No students found for this class and exam.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($students as $stu): 
                        $stats = $studentStats[$stu['id']] ?? ['total' => 0, 'percentage' => 0, 'grade' => '-', 'hasFailed' => false, 'rank' => '-'];
                    ?>
                    <tr>
                        <td class="fw-bold"><?= e($stu['roll_no'] ?: '-') ?></td>
                        <td class="text-start">
                            <span class="fw-semibold text-dark"><?= e($stu['first_name'] . ' ' . $stu['last_name']) ?></span>
                            <div class="text-muted" style="font-size: 0.72rem;"><?= e($stu['admission_no']) ?></div>
                        </td>
                        <?php foreach ($subjects as $sub): 
                            $m = $marksMap[$stu['id']][$sub['exam_subject_id']] ?? null;
                            $passMarks = (float)($sub['pass_marks'] ?: 40);
                            $obtained = $m !== null ? (float)$m['marks_obtained'] : null;
                            $isFail = $obtained !== null && $obtained < $passMarks;
                        ?>
                            <td class="<?= $isFail ? 'table-danger text-danger fw-bold' : '' ?>">
                                <?= $obtained !== null ? number_format($obtained, 1) : '<span class="text-muted opacity-50">-</span>' ?>
                            </td>
                        <?php endforeach; ?>
                        <td class="fw-bold table-primary"><?= number_format($stats['total'], 1) ?></td>
                        <td class="fw-bold table-info"><?= $stats['percentage'] ?>%</td>
                        <td>
                            <span class="badge <?= $stats['grade'] === 'F' ? 'bg-danger' : ($stats['grade'] === 'A+' || $stats['grade'] === 'A' ? 'bg-success' : 'bg-primary') ?>">
                                <?= e($stats['grade']) ?>
                            </span>
                        </td>
                        <td class="fw-bold text-secondary">
                            <?php if ($stats['rank'] <= 3): ?>
                                <span class="badge bg-warning text-dark"><i class="fas fa-trophy me-1"></i> <?= $stats['rank'] ?></span>
                            <?php else: ?>
                                #<?= $stats['rank'] ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($stats['hasFailed'] || $stats['grade'] === 'F'): ?>
                                <span class="badge bg-danger-subtle text-danger px-2">Fail</span>
                            <?php else: ?>
                                <span class="badge bg-success-subtle text-success px-2">Pass</span>
                            <?php endif; ?>
                        </td>
                        <td class="no-print">
                            <a href="<?= BASE_PATH ?>/examinations/results.php?student_id=<?= $stu['id'] ?>&exam_id=<?= $examId ?>" class="btn btn-outline-primary btn-sm py-0 px-2" title="View Result Card">
                                <i class="fas fa-file-alt"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="d-none d-print-block mt-5 pt-4">
    <div class="row text-center">
        <div class="col-4">
            <div style="border-top: 1px solid #333; width: 80%; margin: 0 auto; padding-top: 5px;">
                <strong>Class Teacher</strong>
            </div>
        </div>
        <div class="col-4">
            <div style="border-top: 1px solid #333; width: 80%; margin: 0 auto; padding-top: 5px;">
                <strong>Examination Controller</strong>
            </div>
        </div>
        <div class="col-4">
            <div style="border-top: 1px solid #333; width: 80%; margin: 0 auto; padding-top: 5px;">
                <strong>Principal / Headmaster</strong>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
