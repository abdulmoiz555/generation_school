<?php
/**
 * Attendance Analytics & Reports (Section 16 & 37)
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

requirePermission('attendance_student');

$allClasses = getAllClasses();
$month = $_GET['month'] ?? date('Y-m');
$classId = (int)($_GET['class_id'] ?? ($allClasses[0]['id'] ?? 1));

// Monthly Class Attendance Statistics
$stmt = $pdo->prepare("
    SELECT s.id, s.admission_no, s.roll_no, s.first_name, s.last_name,
           COUNT(sa.id) as total_days_recorded,
           SUM(CASE WHEN sa.status = 'Present' THEN 1 ELSE 0 END) as present_days,
           SUM(CASE WHEN sa.status = 'Late' THEN 1 ELSE 0 END) as late_days,
           SUM(CASE WHEN sa.status = 'Absent' THEN 1 ELSE 0 END) as absent_days,
           SUM(CASE WHEN sa.status = 'Leave' THEN 1 ELSE 0 END) as leave_days
    FROM students s
    LEFT JOIN student_attendance sa ON s.id = sa.student_id AND DATE_FORMAT(sa.attendance_date, '%Y-%m') = ?
    WHERE s.class_id = ? AND s.status = 'active'
    GROUP BY s.id
    ORDER BY s.roll_no ASC, s.first_name ASC
");
$stmt->execute([$month, $classId]);
$monthlyReport = $stmt->fetchAll();

$currentClass = getClass($classId);

$pageTitle = "Attendance Reports";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3 no-print">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/attendance/students.php">Attendance</a></li>
                <li class="breadcrumb-item active">Reports</li>
            </ol>
        </nav>
        <h3 class="fw-bold text-dark mb-0">Monthly Attendance Report</h3>
    </div>
    <div class="d-flex gap-2">
        <button onclick="window.print()" class="btn btn-outline-secondary btn-sm rounded-pill px-3 shadow-sm">
            <i class="fas fa-print me-1"></i> Print Report
        </button>
        <a href="<?= BASE_PATH ?>/attendance/students.php" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm">
            <i class="fas fa-check-circle me-1"></i> Take Attendance
        </a>
    </div>
</div>

<!-- Filters -->
<div class="card border-0 shadow-sm mb-4 no-print">
    <div class="card-body p-3">
        <form action="<?= BASE_PATH ?>/attendance/reports.php" method="GET" class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label small fw-semibold">Month</label>
                <input type="month" name="month" class="form-control" value="<?= e($month) ?>" required>
            </div>
            <div class="col-md-5">
                <label class="form-label small fw-semibold">Class</label>
                <select name="class_id" class="form-select">
                    <?php foreach ($allClasses as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $classId == $c['id'] ? 'selected' : '' ?>>
                            <?= e($c['class_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Generate</button>
            </div>
        </form>
    </div>
</div>

<!-- Printable Report Table -->
<div class="printable-area">
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-header bg-transparent py-3 text-center border-bottom">
            <h4 class="fw-bold text-dark mb-1"><?= e(getSetting('school_name', 'EduManage Academy')) ?></h4>
            <h6 class="fw-semibold text-primary mb-0">Monthly Attendance Report: <?= e($currentClass['class_name'] ?? 'Class') ?></h6>
            <small class="text-muted">Month: <?= date('F Y', strtotime($month . '-01')) ?></small>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle mb-0">
                    <thead class="table-light text-center">
                        <tr>
                            <th width="80">Roll #</th>
                            <th class="text-start">Student Name</th>
                            <th>Adm #</th>
                            <th>Total Days</th>
                            <th>Present</th>
                            <th>Late</th>
                            <th>Absent</th>
                            <th>Leave</th>
                            <th>Percentage</th>
                        </tr>
                    </thead>
                    <tbody class="text-center">
                        <?php if (empty($monthlyReport)): ?>
                            <tr><td colspan="9" class="text-center py-4 text-muted">No attendance data found for this period.</td></tr>
                        <?php else: ?>
                            <?php foreach ($monthlyReport as $row): 
                                $tot = (int)$row['total_days_recorded'];
                                $pres = (int)$row['present_days'];
                                $pct = ($tot > 0) ? round(($pres / $tot) * 100, 1) : 100;
                            ?>
                                <tr>
                                    <td><?= e($row['roll_no'] ?: '-') ?></td>
                                    <td class="text-start fw-bold text-dark">
                                        <a href="<?= BASE_PATH ?>/students/view.php?id=<?= $row['id'] ?>" class="text-decoration-none text-dark">
                                            <?= e($row['first_name'] . ' ' . $row['last_name']) ?>
                                        </a>
                                    </td>
                                    <td><code><?= e($row['admission_no']) ?></code></td>
                                    <td><?= $tot ?></td>
                                    <td class="text-success fw-bold"><?= $pres ?></td>
                                    <td class="text-warning"><?= (int)$row['late_days'] ?></td>
                                    <td class="text-danger fw-bold"><?= (int)$row['absent_days'] ?></td>
                                    <td class="text-info"><?= (int)$row['leave_days'] ?></td>
                                    <td>
                                        <span class="badge <?= $pct >= 80 ? 'bg-success' : ($pct >= 60 ? 'bg-warning' : 'bg-danger') ?>">
                                            <?= $pct ?>%
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
