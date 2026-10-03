<?php
/**
 * Daily Student Attendance (Section 16)
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

requirePermission('attendance_student');

$allClasses = getAllClasses();
$activeSession = getActiveSession();

$attendanceDate = $_GET['date'] ?? date('Y-m-d');
$classId = (int)($_GET['class_id'] ?? ($allClasses[0]['id'] ?? 1));
$sectionId = (int)($_GET['section_id'] ?? 1);
$sections = getSections($classId);

// Handle standard POST submit as well (fallback for non-JS)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_attendance') {
    $date = $_POST['attendance_date'];
    $cId = (int)$_POST['class_id'];
    $sId = (int)$_POST['section_id'];
    $att = $_POST['attendance'] ?? [];
    $rem = $_POST['remarks'] ?? [];

    $stmt = $pdo->prepare("
        INSERT INTO student_attendance (session_id, student_id, class_id, section_id, attendance_date, status, remarks)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE status = VALUES(status), remarks = VALUES(remarks)
    ");

    $count = 0;
    foreach ($att as $studentId => $status) {
        $stmt->execute([
            $activeSession['id'],
            (int)$studentId,
            $cId,
            $sId,
            $date,
            $status,
            trim($rem[$studentId] ?? '')
        ]);
        $count++;
    }
    logAudit('SAVE_ATTENDANCE', 'Attendance', $cId, "Saved attendance for {$count} students on {$date}");
    setFlashMessage('success', "Attendance saved for {$count} students successfully!");
    header("Location: " . BASE_PATH . "/attendance/students.php?date={$date}&class_id={$cId}&section_id={$sId}");
    exit;
}

// Fetch all students for this class and section
$stStmt = $pdo->prepare("
    SELECT s.id, s.admission_no, s.roll_no, s.first_name, s.last_name, s.gender,
           sa.status as current_status, sa.remarks as current_remarks
    FROM students s
    LEFT JOIN student_attendance sa ON s.id = sa.student_id AND sa.attendance_date = ?
    WHERE s.class_id = ? AND s.section_id = ? AND s.status = 'active'
    ORDER BY s.roll_no ASC, s.first_name ASC
");
$stStmt->execute([$attendanceDate, $classId, $sectionId]);
$students = $stStmt->fetchAll();

$currentClass = getClass($classId);
$currentSection = getSection($sectionId);

$pageTitle = "Student Attendance";
$extraScripts = ['attendance.js'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3 no-print">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item">Attendance</li>
                <li class="breadcrumb-item active">Students</li>
            </ol>
        </nav>
        <h3 class="fw-bold text-dark mb-0">Daily Student Attendance</h3>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_PATH ?>/attendance/reports.php" class="btn btn-outline-info btn-sm rounded-pill px-3 shadow-sm">
            <i class="fas fa-chart-bar me-1"></i> Attendance Reports
        </a>
    </div>
</div>

<!-- Selection Filter -->
<div class="card border-0 shadow-sm mb-4 no-print">
    <div class="card-body p-3">
        <form action="<?= BASE_PATH ?>/attendance/students.php" method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Attendance Date</label>
                <input type="date" name="date" class="form-control" value="<?= e($attendanceDate) ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Class</label>
                <select name="class_id" class="form-select class-select" data-target-section="attSecSelect">
                    <?php foreach ($allClasses as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $classId == $c['id'] ? 'selected' : '' ?>>
                            <?= e($c['class_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Section</label>
                <select name="section_id" id="attSecSelect" class="form-select">
                    <?php foreach ($sections as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= $sectionId == $s['id'] ? 'selected' : '' ?>>
                            <?= e($s['section_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-1"></i> Load Students</button>
            </div>
        </form>
    </div>
</div>

<!-- Attendance Form -->
<form id="attendanceForm" action="<?= BASE_PATH ?>/attendance/students.php" method="POST" data-ajax="true">
    <input type="hidden" name="action" value="save_attendance">
    <input type="hidden" name="attendance_date" value="<?= e($attendanceDate) ?>">
    <input type="hidden" name="class_id" value="<?= $classId ?>">
    <input type="hidden" name="section_id" value="<?= $sectionId ?>">

    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-transparent py-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
            <div>
                <h5 class="fw-bold mb-0 text-dark">
                    <?= e($currentClass['class_name'] ?? 'Class') ?> - <?= e($currentSection['section_name'] ?? '') ?> 
                    <span class="text-muted fw-normal fs-6 ms-2">(<?= formatDate($attendanceDate) ?>)</span>
                </h5>
                <small class="text-muted"><?= count($students) ?> students enrolled</small>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-xs btn-outline-success" id="markAllPresentBtn">
                    <i class="fas fa-check-double me-1"></i> Mark All Present
                </button>
                <button type="button" class="btn btn-xs btn-outline-danger" id="markAllAbsentBtn">
                    <i class="fas fa-times-circle me-1"></i> Mark All Absent
                </button>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th width="80">Roll #</th>
                            <th>Student</th>
                            <th>Adm #</th>
                            <th class="text-center" width="340">Attendance Status</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($students)): ?>
                            <tr><td colspan="5" class="text-center py-5 text-muted">No active students in this class and section.</td></tr>
                        <?php else: ?>
                            <?php foreach ($students as $st): 
                                $status = $st['current_status'] ?? 'Present';
                            ?>
                                <tr>
                                    <td><span class="fw-bold"><?= e($st['roll_no'] ?: '-') ?></span></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-initials me-2" style="width: 32px; height: 32px; font-size: 0.75rem;">
                                                <?= strtoupper(substr($st['first_name'], 0, 1)) ?>
                                            </div>
                                            <div>
                                                <div class="fw-bold text-dark"><?= e($st['first_name'] . ' ' . $st['last_name']) ?></div>
                                                <small class="text-muted"><?= e($st['gender']) ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><code><?= e($st['admission_no']) ?></code></td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm" role="group">
                                            <input type="radio" class="btn-check" name="attendance[<?= $st['id'] ?>]" id="pres_<?= $st['id'] ?>" value="Present" <?= $status === 'Present' ? 'checked' : '' ?>>
                                            <label class="btn btn-outline-success px-3" for="pres_<?= $st['id'] ?>">Present</label>

                                            <input type="radio" class="btn-check" name="attendance[<?= $st['id'] ?>]" id="abs_<?= $st['id'] ?>" value="Absent" <?= $status === 'Absent' ? 'checked' : '' ?>>
                                            <label class="btn btn-outline-danger px-3" for="abs_<?= $st['id'] ?>">Absent</label>

                                            <input type="radio" class="btn-check" name="attendance[<?= $st['id'] ?>]" id="late_<?= $st['id'] ?>" value="Late" <?= $status === 'Late' ? 'checked' : '' ?>>
                                            <label class="btn btn-outline-warning px-3" for="late_<?= $st['id'] ?>">Late</label>

                                            <input type="radio" class="btn-check" name="attendance[<?= $st['id'] ?>]" id="leave_<?= $st['id'] ?>" value="Leave" <?= $status === 'Leave' ? 'checked' : '' ?>>
                                            <label class="btn btn-outline-info px-3" for="leave_<?= $st['id'] ?>">Leave</label>
                                        </div>
                                    </td>
                                    <td>
                                        <input type="text" name="remarks[<?= $st['id'] ?>]" class="form-control form-control-sm" placeholder="Optional notes..." value="<?= e($st['current_remarks'] ?? '') ?>">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if (!empty($students)): ?>
            <div class="card-footer bg-transparent py-3 text-end border-top">
                <button type="submit" class="btn btn-primary px-5 rounded-pill shadow-sm">
                    <i class="fas fa-save me-2"></i> Save Attendance Record
                </button>
            </div>
        <?php endif; ?>
    </div>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
