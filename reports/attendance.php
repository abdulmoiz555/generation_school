<?php
/**
 * Student Attendance Analytics & Low Attendance Report
 * Tracks class percentages, defaulters (< 75%), and monthly patterns
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

requirePermission('attendance_reports');

$classId = isset($_GET['class_id']) && $_GET['class_id'] !== '' ? (int)$_GET['class_id'] : null;
$threshold = isset($_GET['threshold']) ? (float)$_GET['threshold'] : 75.0;

$classes = $pdo->query("SELECT * FROM classes ORDER BY numeric_level ASC")->fetchAll();

// Query student attendance statistics
$query = "
    SELECT 
        s.id, s.admission_no, s.first_name, s.last_name, s.roll_no,
        c.class_name, sec.section_name,
        COUNT(a.id) as total_days,
        SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_days,
        SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_days,
        SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late_days,
        SUM(CASE WHEN a.status = 'half_day' THEN 1 ELSE 0 END) as half_days
    FROM students s
    JOIN classes c ON s.class_id = c.id
    LEFT JOIN sections sec ON s.section_id = sec.id
    LEFT JOIN student_attendance a ON s.id = a.student_id
    WHERE s.status = 'active'
";
$params = [];

if ($classId) {
    $query .= " AND s.class_id = ?";
    $params[] = $classId;
}

$query .= " GROUP BY s.id ORDER BY c.numeric_level ASC, s.roll_no ASC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$attendanceData = $stmt->fetchAll();

$lowAttendanceCount = 0;
$processedStudents = [];

foreach ($attendanceData as $row) {
    $total = (int)$row['total_days'];
    $present = (int)$row['present_days'];
    $half = (int)$row['half_days'];
    $effectivePresent = $present + ($half * 0.5);
    $percentage = $total > 0 ? round(($effectivePresent / $total) * 100, 1) : 100.0;
    
    $row['percentage'] = $percentage;
    if ($percentage < $threshold && $total > 0) {
        $lowAttendanceCount++;
    }
    $processedStudents[] = $row;
}

$pageTitle = "Attendance Analytics & Defaulters";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3 no-print">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/reports/index.php">Reports</a></li>
                <li class="breadcrumb-item active">Attendance Analytics</li>
            </ol>
        </nav>
        <h3 class="fw-bold text-dark mb-0"><i class="fas fa-user-check text-primary me-2"></i> Attendance Analytics & Defaulters</h3>
        <p class="text-muted small mb-0">Monitor student attendance rates and identify students falling below required thresholds</p>
    </div>
    <div class="d-flex gap-2">
        <button type="button" onclick="window.print()" class="btn btn-primary rounded-pill px-4 shadow-sm">
            <i class="fas fa-print me-1"></i> Print Report
        </button>
        <a href="<?= BASE_PATH ?>/attendance/students.php" class="btn btn-outline-secondary rounded-pill px-3">
            <i class="fas fa-calendar-check me-1"></i> Mark Attendance
        </a>
    </div>
</div>

<!-- Filters -->
<div class="card border-0 shadow-sm rounded-4 mb-4 no-print">
    <div class="card-body p-3">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-md-5">
                <label class="small text-muted mb-1 fw-bold">Select Class</label>
                <select name="class_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">-- All Classes --</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $classId == $c['id'] ? 'selected' : '' ?>><?= e($c['class_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="small text-muted mb-1 fw-bold">Defaulter Threshold (%)</label>
                <input type="number" name="threshold" class="form-control form-control-sm" value="<?= $threshold ?>" min="1" max="100" step="5">
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-dark btn-sm w-100"><i class="fas fa-filter me-1"></i> Apply Filter</button>
            </div>
        </form>
    </div>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-primary-subtle text-primary h-100">
            <div class="small fw-semibold text-uppercase">Total Enrolled Audited</div>
            <div class="fs-2 fw-bold mt-1"><?= count($processedStudents) ?> Students</div>
            <div class="small opacity-75 mt-1">Across filtered classes</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-danger-subtle text-danger h-100">
            <div class="small fw-semibold text-uppercase">Low Attendance Defaulters</div>
            <div class="fs-2 fw-bold mt-1"><?= $lowAttendanceCount ?> Students</div>
            <div class="small opacity-75 mt-1">Attendance &lt; <?= $threshold ?>% minimum required</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-success-subtle text-success h-100">
            <div class="small fw-semibold text-uppercase">Compliance Status</div>
            <div class="fs-2 fw-bold mt-1">
                <?= count($processedStudents) > 0 ? round(((count($processedStudents) - $lowAttendanceCount) / count($processedStudents)) * 100, 1) : 100 ?>%
            </div>
            <div class="small opacity-75 mt-1">Meeting minimum attendance threshold</div>
        </div>
    </div>
</div>

<!-- Table -->
<div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" style="font-size: 0.9rem;">
            <thead class="table-light">
                <tr>
                    <th>Roll</th>
                    <th>Student Name</th>
                    <th>Class</th>
                    <th class="text-center">Total Working Days</th>
                    <th class="text-center text-success">Present</th>
                    <th class="text-center text-danger">Absent</th>
                    <th class="text-center text-warning">Late</th>
                    <th class="text-center">Attendance Rate</th>
                    <th class="text-end">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($processedStudents as $row): 
                    $isLow = $row['percentage'] < $threshold && $row['total_days'] > 0;
                ?>
                <tr class="<?= $isLow ? 'table-danger' : '' ?>">
                    <td class="fw-bold"><?= e($row['roll_no'] ?: '-') ?></td>
                    <td>
                        <div class="fw-semibold text-dark"><?= e($row['first_name'] . ' ' . $row['last_name']) ?></div>
                        <small class="text-muted"><?= e($row['admission_no']) ?></small>
                    </td>
                    <td><?= e($row['class_name']) ?> <small class="text-muted">(<?= e($row['section_name'] ?: 'A') ?>)</small></td>
                    <td class="text-center fw-bold"><?= $row['total_days'] ?></td>
                    <td class="text-center text-success fw-bold"><?= $row['present_days'] ?></td>
                    <td class="text-center text-danger fw-bold"><?= $row['absent_days'] ?></td>
                    <td class="text-center text-warning fw-bold"><?= $row['late_days'] ?></td>
                    <td class="text-center">
                        <div class="d-flex align-items-center justify-content-center gap-2">
                            <div class="progress flex-grow-1" style="height: 6px; max-width: 80px;">
                                <div class="progress-bar <?= $isLow ? 'bg-danger' : 'bg-success' ?>" style="width: <?= $row['percentage'] ?>%"></div>
                            </div>
                            <span class="fw-bold <?= $isLow ? 'text-danger' : 'text-dark' ?>"><?= $row['percentage'] ?>%</span>
                        </div>
                    </td>
                    <td class="text-end">
                        <?php if ($isLow): ?>
                            <span class="badge bg-danger px-2"><i class="fas fa-exclamation-triangle me-1"></i> Low Attendance</span>
                        <?php else: ?>
                            <span class="badge bg-success-subtle text-success px-2">Regular</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
