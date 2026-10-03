<?php
/**
 * Daily Teacher Attendance
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

requirePermission('attendance_teacher');

$attendanceDate = $_GET['date'] ?? date('Y-m-d');

// Save Teacher Attendance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_teacher_attendance') {
    $date = $_POST['attendance_date'];
    $att = $_POST['attendance'] ?? [];
    $rem = $_POST['remarks'] ?? [];

    $stmt = $pdo->prepare("
        INSERT INTO teacher_attendance (teacher_id, attendance_date, status, remarks)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE status = VALUES(status), remarks = VALUES(remarks)
    ");

    $count = 0;
    foreach ($att as $teacherId => $status) {
        $stmt->execute([(int)$teacherId, $date, $status, trim($rem[$teacherId] ?? '')]);
        $count++;
    }
    logAudit('SAVE_TEACHER_ATTENDANCE', 'Attendance', null, "Recorded attendance for {$count} teachers");
    setFlashMessage('success', "Teacher attendance saved for {$count} staff members!");
    header("Location: " . BASE_PATH . "/attendance/teachers.php?date={$date}");
    exit;
}

// Fetch teachers and their attendance for date
$stmt = $pdo->prepare("
    SELECT t.*, d.name as dept_name, des.title as desig_title,
           ta.status as current_status, ta.remarks as current_remarks
    FROM teachers t
    LEFT JOIN departments d ON t.department_id = d.id
    LEFT JOIN designations des ON t.designation_id = des.id
    LEFT JOIN teacher_attendance ta ON t.id = ta.teacher_id AND ta.attendance_date = ?
    WHERE t.status = 'active'
    ORDER BY t.name ASC
");
$stmt->execute([$attendanceDate]);
$teachers = $stmt->fetchAll();

$pageTitle = "Teacher Attendance";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3 no-print">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item">Attendance</li>
                <li class="breadcrumb-item active">Teachers</li>
            </ol>
        </nav>
        <h3 class="fw-bold text-dark mb-0">Faculty & Teacher Attendance</h3>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4 no-print">
    <div class="card-body p-3">
        <form action="<?= BASE_PATH ?>/attendance/teachers.php" method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small fw-semibold">Attendance Date</label>
                <input type="date" name="date" class="form-control" value="<?= e($attendanceDate) ?>" required>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Load Roster</button>
            </div>
        </form>
    </div>
</div>

<form action="<?= BASE_PATH ?>/attendance/teachers.php" method="POST">
    <input type="hidden" name="action" value="save_teacher_attendance">
    <input type="hidden" name="attendance_date" value="<?= e($attendanceDate) ?>">

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-header bg-transparent py-3 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0 text-dark">Faculty Attendance (<?= formatDate($attendanceDate) ?>)</h5>
            <span class="badge bg-primary rounded-pill"><?= count($teachers) ?> Faculty Members</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Teacher Name</th>
                            <th>Code</th>
                            <th>Designation</th>
                            <th class="text-center" width="340">Status</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($teachers as $t): 
                            $status = $t['current_status'] ?? 'Present';
                        ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-dark"><?= e($t['name']) ?></div>
                                    <small class="text-muted"><?= e($t['email']) ?></small>
                                </td>
                                <td><code><?= e($t['teacher_code']) ?></code></td>
                                <td><?= e($t['desig_title'] ?? 'Teacher') ?></td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <input type="radio" class="btn-check" name="attendance[<?= $t['id'] ?>]" id="t_pres_<?= $t['id'] ?>" value="Present" <?= $status === 'Present' ? 'checked' : '' ?>>
                                        <label class="btn btn-outline-success px-3" for="t_pres_<?= $t['id'] ?>">Present</label>

                                        <input type="radio" class="btn-check" name="attendance[<?= $t['id'] ?>]" id="t_abs_<?= $t['id'] ?>" value="Absent" <?= $status === 'Absent' ? 'checked' : '' ?>>
                                        <label class="btn btn-outline-danger px-3" for="t_abs_<?= $t['id'] ?>">Absent</label>

                                        <input type="radio" class="btn-check" name="attendance[<?= $t['id'] ?>]" id="t_late_<?= $t['id'] ?>" value="Late" <?= $status === 'Late' ? 'checked' : '' ?>>
                                        <label class="btn btn-outline-warning px-3" for="t_late_<?= $t['id'] ?>">Late</label>

                                        <input type="radio" class="btn-check" name="attendance[<?= $t['id'] ?>]" id="t_leave_<?= $t['id'] ?>" value="Leave" <?= $status === 'Leave' ? 'checked' : '' ?>>
                                        <label class="btn btn-outline-info px-3" for="t_leave_<?= $t['id'] ?>">Leave</label>
                                    </div>
                                </td>
                                <td>
                                    <input type="text" name="remarks[<?= $t['id'] ?>]" class="form-control form-control-sm" placeholder="Notes..." value="<?= e($t['current_remarks'] ?? '') ?>">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-transparent py-3 text-end">
            <button type="submit" class="btn btn-primary px-5 rounded-pill shadow-sm">
                <i class="fas fa-save me-2"></i> Save Faculty Attendance
            </button>
        </div>
    </div>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
