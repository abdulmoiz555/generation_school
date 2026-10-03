<?php
/**
 * Marks Entry Screen (Section 24)
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

requirePermission('marks_entry');

$allExams = $pdo->query("SELECT * FROM exams ORDER BY start_date DESC")->fetchAll();
$allClasses = getAllClasses();

$examId = (int)($_GET['exam_id'] ?? ($allExams[0]['id'] ?? 1));
$classId = (int)($_GET['class_id'] ?? ($allClasses[0]['id'] ?? 1));
$sectionId = (int)($_GET['section_id'] ?? 1);
$sections = getSections($classId);

// Find subjects for class
$subjects = $pdo->query("SELECT id, subject_name, max_marks, pass_marks FROM subjects WHERE class_id = {$classId} ORDER BY subject_name ASC")->fetchAll();
$subjectId = (int)($_GET['subject_id'] ?? ($subjects[0]['id'] ?? 1));

// Find exam_subject ID
$esStmt = $pdo->prepare("SELECT id, max_marks, pass_marks FROM exam_subjects WHERE exam_id = ? AND class_id = ? AND subject_id = ? LIMIT 1");
$esStmt->execute([$examId, $classId, $subjectId]);
$examSubject = $esStmt->fetch();

if (!$examSubject && !empty($subjects)) {
    // Auto-create exam_subject link if not yet explicitly mapped
    $subInfo = $pdo->query("SELECT max_marks, pass_marks FROM subjects WHERE id = {$subjectId}")->fetch();
    $maxM = $subInfo['max_marks'] ?? 100;
    $passM = $subInfo['pass_marks'] ?? 40;
    $ins = $pdo->prepare("INSERT INTO exam_subjects (exam_id, class_id, subject_id, exam_date, start_time, end_time, max_marks, pass_marks) VALUES (?, ?, ?, CURDATE(), '09:00:00', '11:00:00', ?, ?)");
    $ins->execute([$examId, $classId, $subjectId, $maxM, $passM]);
    $examSubjectId = (int)$pdo->lastInsertId();
    $maxMarks = $maxM;
} else {
    $examSubjectId = $examSubject['id'] ?? 1;
    $maxMarks = $examSubject['max_marks'] ?? 100;
}

// Fetch all students and any existing marks for this exam & subject
$stStmt = $pdo->prepare("
    SELECT s.id, s.admission_no, s.roll_no, s.first_name, s.last_name,
           m.marks_obtained, m.percentage, m.grade, m.is_passed, m.remarks
    FROM students s
    LEFT JOIN marks m ON s.id = m.student_id AND m.exam_subject_id = ?
    WHERE s.class_id = ? AND s.section_id = ? AND s.status = 'active'
    ORDER BY s.roll_no ASC, s.first_name ASC
");
$stStmt->execute([$examSubjectId, $classId, $sectionId]);
$students = $stStmt->fetchAll();

// POST Save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_marks') {
    $marksArr = $_POST['marks_obtained'] ?? [];
    $remarksArr = $_POST['remarks'] ?? [];
    $maxM = (float)($_POST['max_marks'] ?? 100);

    $stmt = $pdo->prepare("
        INSERT INTO marks (exam_id, exam_subject_id, student_id, marks_obtained, max_marks, percentage, grade, is_passed, remarks, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            marks_obtained = VALUES(marks_obtained),
            max_marks = VALUES(max_marks),
            percentage = VALUES(percentage),
            grade = VALUES(grade),
            is_passed = VALUES(is_passed),
            remarks = VALUES(remarks),
            updated_at = CURRENT_TIMESTAMP
    ");

    $count = 0;
    foreach ($marksArr as $studentId => $obtained) {
        $obtained = floatval($obtained);
        $percentage = ($maxM > 0) ? round(($obtained / $maxM) * 100, 2) : 0;
        $grade = calculateGrade($percentage);
        $isPassed = ($percentage >= 40) ? 1 : 0;
        $remark = trim($remarksArr[$studentId] ?? '');

        $stmt->execute([$examId, $examSubjectId, (int)$studentId, $obtained, $maxM, $percentage, $grade, $isPassed, $remark, getCurrentUserId()]);
        $count++;
    }

    logAudit('SAVE_MARKS', 'Examinations', $examSubjectId, "Saved marks for {$count} students");
    setFlashMessage('success', "Marks saved for {$count} students successfully!");
    header("Location: " . BASE_PATH . "/examinations/marks.php?exam_id={$examId}&class_id={$classId}&section_id={$sectionId}&subject_id={$subjectId}");
    exit;
}

$pageTitle = "Marks Entry";
$extraScripts = ['results.js'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3 no-print">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item">Examinations</li>
                <li class="breadcrumb-item active">Marks Entry</li>
            </ol>
        </nav>
        <h3 class="fw-bold text-dark mb-0">Examination Marks Entry (Section 24)</h3>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_PATH ?>/examinations/results.php" class="btn btn-outline-info btn-sm rounded-pill px-3 shadow-sm">
            <i class="fas fa-award me-1"></i> Result Cards
        </a>
    </div>
</div>

<!-- Selection Filter -->
<div class="card border-0 shadow-sm mb-4 no-print">
    <div class="card-body p-3">
        <form action="<?= BASE_PATH ?>/examinations/marks.php" method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Examination</label>
                <select name="exam_id" class="form-select" onchange="this.form.submit()">
                    <?php foreach ($allExams as $ex): ?>
                        <option value="<?= $ex['id'] ?>" <?= $examId == $ex['id'] ? 'selected' : '' ?>>
                            <?= e($ex['title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Class</label>
                <select name="class_id" class="form-select class-select" data-target-section="mSecSelect" onchange="this.form.submit()">
                    <?php foreach ($allClasses as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $classId == $c['id'] ? 'selected' : '' ?>>
                            <?= e($c['class_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold">Section</label>
                <select name="section_id" id="mSecSelect" class="form-select" onchange="this.form.submit()">
                    <?php foreach ($sections as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= $sectionId == $s['id'] ? 'selected' : '' ?>>
                            <?= e($s['section_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Subject</label>
                <select name="subject_id" class="form-select" onchange="this.form.submit()">
                    <?php foreach ($subjects as $sub): ?>
                        <option value="<?= $sub['id'] ?>" <?= $subjectId == $sub['id'] ? 'selected' : '' ?>>
                            <?= e($sub['subject_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-primary w-100">Load</button>
            </div>
        </form>
    </div>
</div>

<!-- Marks Input Table -->
<form action="<?= BASE_PATH ?>/examinations/marks.php" method="POST">
    <input type="hidden" name="action" value="save_marks">
    <input type="hidden" name="exam_id" value="<?= $examId ?>">
    <input type="hidden" name="exam_subject_id" value="<?= $examSubjectId ?>">
    <input type="hidden" name="max_marks" value="<?= $maxMarks ?>">

    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-transparent py-3 d-flex justify-content-between align-items-center">
            <h6 class="fw-bold mb-0 text-dark">
                Entering Marks &bull; Maximum Marks: <span class="badge bg-primary fs-6"><?= $maxMarks ?></span>
            </h6>
            <span class="small text-muted"><?= count($students) ?> Students on Roster</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th width="80">Roll #</th>
                            <th>Student</th>
                            <th>Adm #</th>
                            <th width="150">Marks Obtained</th>
                            <th width="120" class="text-center">Percentage</th>
                            <th width="100" class="text-center">Grade</th>
                            <th width="100" class="text-center">Result</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($students)): ?>
                            <tr><td colspan="8" class="text-center py-5 text-muted">No students found in this class section.</td></tr>
                        <?php else: ?>
                            <?php foreach ($students as $st): 
                                $obt = $st['marks_obtained'] !== null ? $st['marks_obtained'] : '';
                                $pct = $st['percentage'] !== null ? $st['percentage'] : 0;
                                $grd = $st['grade'] ?: '-';
                                $pass = ($st['is_passed'] == 1);
                            ?>
                                <tr class="marks-entry-row">
                                    <td><strong><?= e($st['roll_no'] ?: '-') ?></strong></td>
                                    <td>
                                        <div class="fw-bold text-dark"><?= e($st['first_name'] . ' ' . $st['last_name']) ?></div>
                                    </td>
                                    <td><code><?= e($st['admission_no']) ?></code></td>
                                    <td>
                                        <input type="hidden" class="max-marks" value="<?= $maxMarks ?>">
                                        <input type="number" step="0.5" name="marks_obtained[<?= $st['id'] ?>]" class="form-control form-control-sm fw-bold obtained-marks text-primary" max="<?= $maxMarks ?>" min="0" value="<?= $obt ?>" placeholder="0.0">
                                    </td>
                                    <td class="text-center fw-bold percent-display"><?= $pct ?>%</td>
                                    <td class="text-center">
                                        <span class="badge bg-light text-dark border grade-display"><?= $grd ?></span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge <?= $pass ? 'bg-success' : 'bg-danger' ?> status-display">
                                            <?= $pass ? 'Pass' : 'Fail' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <input type="text" name="remarks[<?= $st['id'] ?>]" class="form-control form-control-sm" placeholder="e.g. Excellent" value="<?= e($st['remarks'] ?? '') ?>">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php if (!empty($students)): ?>
            <div class="card-footer bg-transparent py-3 text-end">
                <button type="submit" class="btn btn-primary px-5 rounded-pill shadow-sm">
                    <i class="fas fa-save me-2"></i> Save Examination Marks
                </button>
            </div>
        <?php endif; ?>
    </div>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
