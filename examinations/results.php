<?php
/**
 * Student Examination Result Card (Section 26)
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$allExams = $pdo->query("SELECT * FROM exams ORDER BY start_date DESC")->fetchAll();
$allStudents = $pdo->query("SELECT id, first_name, last_name, admission_no FROM students WHERE status = 'active' ORDER BY first_name ASC")->fetchAll();

$examId = (int)($_GET['exam_id'] ?? ($allExams[0]['id'] ?? 1));
$studentId = (int)($_GET['student_id'] ?? ($allStudents[0]['id'] ?? 1));

$student = getStudent($studentId);
$exam = $pdo->query("SELECT * FROM exams WHERE id = {$examId}")->fetch();

// Fetch all subject marks for student in this exam
$stmt = $pdo->prepare("
    SELECT m.*, sub.subject_name, sub.subject_code, sub.subject_type
    FROM marks m
    JOIN exam_subjects es ON m.exam_subject_id = es.id
    JOIN subjects sub ON es.subject_id = sub.id
    WHERE m.exam_id = ? AND m.student_id = ?
    ORDER BY sub.subject_name ASC
");
$stmt->execute([$examId, $studentId]);
$results = $stmt->fetchAll();

// Calculations
$totalMaxMarks = 0;
$totalObtainedMarks = 0;
foreach ($results as $r) {
    $totalMaxMarks += $r['max_marks'];
    $totalObtainedMarks += $r['marks_obtained'];
}

$overallPercentage = ($totalMaxMarks > 0) ? round(($totalObtainedMarks / $totalMaxMarks) * 100, 2) : 0;
$overallGrade = calculateGrade($overallPercentage);

// Attendance rate
$attPct = 95.0; // Default fallback
if ($student) {
    $attStmt = $pdo->prepare("SELECT COUNT(*) as tot, SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as pres FROM student_attendance WHERE student_id = ?");
    $attStmt->execute([$studentId]);
    $attRes = $attStmt->fetch();
    if ($attRes && $attRes['tot'] > 0) {
        $attPct = round(($attRes['pres'] / $attRes['tot']) * 100, 1);
    }
}

$pageTitle = "Result Card - " . ($student['first_name'] ?? 'Student');
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 no-print">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item">Examinations</li>
                <li class="breadcrumb-item active">Result Card</li>
            </ol>
        </nav>
        <h3 class="fw-bold text-dark mb-0">Official Student Result Card</h3>
    </div>
    <div class="d-flex gap-2">
        <button onclick="window.print()" class="btn btn-primary rounded-pill px-4 shadow-sm">
            <i class="fas fa-print me-2"></i> Print Official Result Card
        </button>
        <a href="<?= BASE_PATH ?>/examinations/marks.php" class="btn btn-outline-secondary rounded-pill px-3">
            Marks Entry
        </a>
    </div>
</div>

<!-- Selector Filter (No Print) -->
<div class="card border-0 shadow-sm mb-4 no-print">
    <div class="card-body p-3">
        <form action="<?= BASE_PATH ?>/examinations/results.php" method="GET" class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label small fw-semibold">Select Examination</label>
                <select name="exam_id" class="form-select" onchange="this.form.submit()">
                    <?php foreach ($allExams as $ex): ?>
                        <option value="<?= $ex['id'] ?>" <?= $examId == $ex['id'] ? 'selected' : '' ?>>
                            <?= e($ex['title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label small fw-semibold">Select Student</label>
                <select name="student_id" class="form-select" onchange="this.form.submit()">
                    <?php foreach ($allStudents as $st): ?>
                        <option value="<?= $st['id'] ?>" <?= $studentId == $st['id'] ? 'selected' : '' ?>>
                            <?= e($st['first_name'] . ' ' . $st['last_name']) ?> (<?= e($st['admission_no']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Load Card</button>
            </div>
        </form>
    </div>
</div>

<!-- Printable Result Card Format (Section 26) -->
<div class="printable-area py-3">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-10">
            
            <div class="card border border-2 border-dark rounded-4 p-4 p-md-5 bg-white shadow-sm">
                
                <!-- School Header -->
                <div class="text-center pb-3 mb-4 border-bottom border-2 border-dark">
                    <div class="d-flex align-items-center justify-content-center gap-2 mb-1">
                        <i class="fas fa-graduation-cap text-primary fs-1"></i>
                        <h2 class="fw-bold text-dark mb-0 text-uppercase letter-spacing-1">
                            <?= e(getSetting('school_name', 'Springfield International Academy')) ?>
                        </h2>
                    </div>
                    <div class="text-muted small mb-2"><?= e(getSetting('school_motto', 'Excellence in Education, Character in Leadership')) ?></div>
                    <div class="small text-muted mb-2">
                        <?= e(getSetting('address', '742 Evergreen Terrace')) ?> &bull; Phone: <?= e(getSetting('phone', '+1 555-0199')) ?> &bull; Reg: <?= e(getSetting('registration_no', 'EDU-REG-2024')) ?>
                    </div>
                    <div class="mt-2">
                        <span class="badge bg-dark px-4 py-2 fs-6 text-uppercase">
                            Official Progress & Assessment Report
                        </span>
                    </div>
                </div>

                <!-- Exam & Student Profile Bar -->
                <div class="bg-light p-3 rounded-3 border mb-4">
                    <div class="row g-2 small">
                        <div class="col-sm-6">
                            <span class="text-muted">Student Name:</span>
                            <strong class="text-dark fs-6 ms-1"><?= e($student['first_name'] . ' ' . $student['last_name']) ?></strong>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted">Admission No:</span>
                            <strong class="text-dark ms-1"><?= e($student['admission_no']) ?></strong>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted">Class & Section:</span>
                            <strong class="text-dark ms-1"><?= e($student['class_name'] . ' - ' . $student['section_name']) ?></strong>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted">Roll Number:</span>
                            <strong class="text-dark ms-1"><?= e($student['roll_no'] ?: 'N/A') ?></strong>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted">Assessment Title:</span>
                            <strong class="text-primary ms-1"><?= e($exam['title'] ?? 'Semester Assessment') ?></strong>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted">Cumulative Attendance:</span>
                            <strong class="text-success ms-1"><?= $attPct ?>%</strong>
                        </div>
                    </div>
                </div>

                <!-- Marks Table -->
                <div class="table-responsive mb-4">
                    <table class="table table-bordered table-print align-middle mb-0 text-center">
                        <thead class="table-light">
                            <tr>
                                <th width="60">Sr #</th>
                                <th class="text-start">Course Subject</th>
                                <th>Subject Code</th>
                                <th>Maximum Marks</th>
                                <th>Obtained Marks</th>
                                <th>Percentage</th>
                                <th>Grade</th>
                                <th>Result</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($results)): ?>
                                <tr><td colspan="8" class="text-center py-4 text-muted">No marks uploaded for this student in the selected assessment.</td></tr>
                            <?php else: ?>
                                <?php $sr = 1; foreach ($results as $res): ?>
                                    <tr>
                                        <td><?= $sr++ ?></td>
                                        <td class="text-start fw-bold text-dark"><?= e($res['subject_name']) ?></td>
                                        <td><code><?= e($res['subject_code']) ?></code></td>
                                        <td><?= $res['max_marks'] ?></td>
                                        <td class="fw-bold text-primary fs-6"><?= $res['marks_obtained'] ?></td>
                                        <td><?= $res['percentage'] ?>%</td>
                                        <td>
                                            <span class="badge <?= in_array($res['grade'], ['A+', 'A']) ? 'bg-success' : 'bg-primary' ?> px-2 py-1">
                                                <?= e($res['grade']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge <?= $res['is_passed'] ? 'bg-success' : 'bg-danger' ?>">
                                                <?= $res['is_passed'] ? 'PASS' : 'FAIL' ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <tfoot class="table-light fw-bold">
                            <tr>
                                <td colspan="3" class="text-end">Summary Grand Totals:</td>
                                <td><?= $totalMaxMarks ?></td>
                                <td class="text-primary fs-5"><?= $totalObtainedMarks ?></td>
                                <td class="fs-5 text-success"><?= $overallPercentage ?>%</td>
                                <td><span class="badge bg-dark fs-6"><?= $overallGrade ?></span></td>
                                <td>
                                    <span class="badge <?= $overallPercentage >= 40 ? 'bg-success' : 'bg-danger' ?> fs-6">
                                        <?= $overallPercentage >= 40 ? 'PASSED' : 'FAILED' ?>
                                    </span>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Grading Scale Reference -->
                <div class="d-flex justify-content-between flex-wrap gap-2 small text-muted border p-2 rounded mb-4" style="font-size: 0.75rem;">
                    <span><strong>Grading Scale:</strong> A+ (90-100%)</span>
                    <span>A (80-89%)</span>
                    <span>B (70-79%)</span>
                    <span>C (60-69%)</span>
                    <span>D (50-59%)</span>
                    <span>F (&lt;50%)</span>
                </div>

                <!-- Remarks Box -->
                <div class="row g-3 mb-5">
                    <div class="col-md-6">
                        <div class="border p-3 rounded h-100">
                            <span class="fw-bold text-dark small d-block mb-1">Class Teacher Remarks:</span>
                            <p class="text-muted small mb-0">Demonstrates keen engagement in scientific discovery and exceptional mathematical dexterity. Consistently prompt and attentive.</p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="border p-3 rounded h-100">
                            <span class="fw-bold text-dark small d-block mb-1">Principal / Head of Institution Remarks:</span>
                            <p class="text-muted small mb-0">Commendable academic milestone. Awarded Certificate of Honor for academic standing.</p>
                        </div>
                    </div>
                </div>

                <!-- Signatures -->
                <div class="d-flex justify-content-between pt-5">
                    <div class="text-center">
                        <div class="sign-line mx-auto mb-1">Class Teacher Signature</div>
                        <div class="text-muted" style="font-size: 0.72rem;">Mrs. Elizabeth Bennett</div>
                    </div>
                    <div class="text-center">
                        <div class="sign-line mx-auto mb-1">Controller of Examinations</div>
                        <div class="text-muted" style="font-size: 0.72rem;">Academic Board Verification</div>
                    </div>
                    <div class="text-center">
                        <div class="sign-line mx-auto mb-1">Principal Signature & Seal</div>
                        <div class="text-muted" style="font-size: 0.72rem;"><?= e(getSetting('principal_name', 'Dr. Sarah Jenkins')) ?></div>
                    </div>
                </div>

            </div>

        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
