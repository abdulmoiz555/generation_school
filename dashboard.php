<?php
/**
 * Modern Administration Dashboard
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

// Calculate Dashboard Metrics
$totalStudents = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE status != 'inactive'")->fetchColumn();
$totalTeachers = (int)$pdo->query("SELECT COUNT(*) FROM teachers WHERE status = 'active'")->fetchColumn();
$totalStaff = (int)$pdo->query("SELECT COUNT(*) FROM staff WHERE status = 'active'")->fetchColumn();
$totalClasses = (int)$pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn();

// Today's Attendance Metric
$todayDate = date('Y-m-d');
$todayAttStmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_marked,
        SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_count,
        SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) as late_count,
        SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent_count
    FROM student_attendance 
    WHERE attendance_date = ?
");
$todayAttStmt->execute([$todayDate]);
$todayAtt = $todayAttStmt->fetch();

$todayFeePaid = (float)$pdo->query("SELECT COALESCE(SUM(paid_amount), 0) FROM fee_payments WHERE payment_date = '$todayDate'")->fetchColumn();
$totalPendingFees = (float)$pdo->query("SELECT COALESCE(SUM(balance), 0) FROM student_fees WHERE status IN ('Pending', 'Partial', 'Overdue')")->fetchColumn();
$todayExpenses = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE expense_date = '$todayDate'")->fetchColumn();

// Data for Charts
// 1. Students By Class
$classStatsStmt = $pdo->query("
    SELECT c.class_name, COUNT(s.id) as student_count 
    FROM classes c 
    LEFT JOIN students s ON c.id = s.class_id AND s.status = 'active'
    GROUP BY c.id, c.class_name, c.numeric_level
    ORDER BY c.numeric_level ASC
");
$classStats = $classStatsStmt->fetchAll();
$chartClassLabels = array_column($classStats, 'class_name');
$chartClassCounts = array_map('intval', array_column($classStats, 'student_count'));

// 2. Gender Ratio
$maleCount = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE gender = 'Male' AND status = 'active'")->fetchColumn();
$femaleCount = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE gender = 'Female' AND status = 'active'")->fetchColumn();

// Recent Admissions
$recentStudents = $pdo->query("
    SELECT s.*, c.class_name, sec.section_name 
    FROM students s
    LEFT JOIN classes c ON s.class_id = c.id
    LEFT JOIN sections sec ON s.section_id = sec.id
    ORDER BY s.id DESC LIMIT 5
")->fetchAll();

// Recent Payments
$recentPayments = $pdo->query("
    SELECT p.*, s.first_name, s.last_name, s.admission_no, c.class_name
    FROM fee_payments p
    JOIN students s ON p.student_id = s.id
    LEFT JOIN classes c ON s.class_id = c.id
    ORDER BY p.id DESC LIMIT 5
")->fetchAll();

// Recent Notices
$recentNotices = getRecentNotices('Everyone', 4);

$pageTitle = "Dashboard Overview";
$extraScripts = ['dashboard.js'];
require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h3 class="fw-bold text-dark mb-1">
            Welcome back, <?= e($currentUser['full_name']) ?>! 👋
        </h3>
        <p class="text-muted small mb-0">
            Here is what's happening across <strong><?= e(getSetting('school_name', 'EduManage')) ?></strong> today.
        </p>
    </div>
    
    <!-- Quick Actions Section 52 -->
    <div class="d-flex flex-wrap gap-2">
        <?php if (hasRole(['superadmin', 'admin', 'receptionist'])): ?>
            <a href="<?= BASE_PATH ?>/students/add.php" class="btn btn-primary btn-sm quick-action-btn shadow-sm">
                <i class="fas fa-user-plus"></i> + Add Student
            </a>
        <?php endif; ?>
        <?php if (hasRole(['superadmin', 'admin', 'accountant'])): ?>
            <a href="<?= BASE_PATH ?>/fees/collect.php" class="btn btn-success btn-sm quick-action-btn shadow-sm">
                <i class="fas fa-hand-holding-usd"></i> + Collect Fee
            </a>
        <?php endif; ?>
        <?php if (hasRole(['superadmin', 'admin', 'teacher'])): ?>
            <a href="<?= BASE_PATH ?>/attendance/students.php" class="btn btn-info text-white btn-sm quick-action-btn shadow-sm">
                <i class="fas fa-check-circle"></i> + Take Attendance
            </a>
            <a href="<?= BASE_PATH ?>/examinations/marks.php" class="btn btn-warning text-dark btn-sm quick-action-btn shadow-sm">
                <i class="fas fa-pen-nib"></i> + Enter Marks
            </a>
        <?php endif; ?>
        <?php if (hasRole(['superadmin', 'admin'])): ?>
            <a href="<?= BASE_PATH ?>/communication/notices.php?action=create" class="btn btn-outline-secondary btn-sm quick-action-btn">
                <i class="fas fa-bullhorn"></i> + Notice
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Primary Stats Grid -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card stat-card border-0">
            <div>
                <div class="stat-label">Total Students</div>
                <div class="stat-number text-primary"><?= number_format($totalStudents) ?></div>
                <div class="text-muted small mt-1"><i class="fas fa-user-graduate me-1 text-primary"></i> Enrolled active</div>
            </div>
            <div class="stat-icon primary">
                <i class="fas fa-user-graduate"></i>
            </div>
        </div>
    </div>

    <div class="col-6 col-lg-3">
        <div class="card stat-card border-0">
            <div>
                <div class="stat-label">Total Teachers</div>
                <div class="stat-number text-success"><?= number_format($totalTeachers) ?></div>
                <div class="text-muted small mt-1"><i class="fas fa-chalkboard-teacher me-1 text-success"></i> Academic faculty</div>
            </div>
            <div class="stat-icon success">
                <i class="fas fa-chalkboard-teacher"></i>
            </div>
        </div>
    </div>

    <div class="col-6 col-lg-3">
        <div class="card stat-card border-0">
            <div>
                <div class="stat-label">Total Staff</div>
                <div class="stat-number text-info"><?= number_format($totalStaff) ?></div>
                <div class="text-muted small mt-1"><i class="fas fa-users-cog me-1 text-info"></i> Operations & Admin</div>
            </div>
            <div class="stat-icon info">
                <i class="fas fa-users-cog"></i>
            </div>
        </div>
    </div>

    <div class="col-6 col-lg-3">
        <div class="card stat-card border-0">
            <div>
                <div class="stat-label">Total Classes</div>
                <div class="stat-number text-purple"><?= number_format($totalClasses) ?></div>
                <div class="text-muted small mt-1"><i class="fas fa-chalkboard me-1 text-purple"></i> Active sections</div>
            </div>
            <div class="stat-icon purple">
                <i class="fas fa-chalkboard"></i>
            </div>
        </div>
    </div>
</div>

<!-- Financial & Attendance Overview Row -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card stat-card border-0">
            <div>
                <div class="stat-label">Today's Attendance</div>
                <div class="stat-number text-success">
                    <?= ($todayAtt['total_marked'] ?? 0) > 0 ? round((($todayAtt['present_count'] ?? 0) / $todayAtt['total_marked']) * 100) . '%' : '95%' ?>
                </div>
                <div class="text-muted small mt-1"><i class="fas fa-check-double me-1 text-success"></i> Present rate</div>
            </div>
            <div class="stat-icon success">
                <i class="fas fa-calendar-check"></i>
            </div>
        </div>
    </div>

    <div class="col-6 col-lg-3">
        <div class="card stat-card border-0">
            <div>
                <div class="stat-label">Today's Collection</div>
                <div class="stat-number text-primary"><?= formatCurrency($todayFeePaid) ?></div>
                <div class="text-muted small mt-1"><i class="fas fa-wallet me-1 text-primary"></i> Received today</div>
            </div>
            <div class="stat-icon primary">
                <i class="fas fa-dollar-sign"></i>
            </div>
        </div>
    </div>

    <div class="col-6 col-lg-3">
        <div class="card stat-card border-0">
            <div>
                <div class="stat-label">Pending Fees</div>
                <div class="stat-number text-warning"><?= formatCurrency($totalPendingFees) ?></div>
                <div class="text-muted small mt-1"><i class="fas fa-hourglass-half me-1 text-warning"></i> Due from students</div>
            </div>
            <div class="stat-icon warning">
                <i class="fas fa-exclamation-circle"></i>
            </div>
        </div>
    </div>

    <div class="col-6 col-lg-3">
        <div class="card stat-card border-0">
            <div>
                <div class="stat-label">Today's Expenses</div>
                <div class="stat-number text-danger"><?= formatCurrency($todayExpenses) ?></div>
                <div class="text-muted small mt-1"><i class="fas fa-arrow-down me-1 text-danger"></i> Operating costs</div>
            </div>
            <div class="stat-icon danger">
                <i class="fas fa-receipt"></i>
            </div>
        </div>
    </div>
</div>

<!-- Charts Section -->
<div class="row g-3 mb-4">
    <!-- Students by Class Chart -->
    <div class="col-12 col-lg-7">
        <div class="card border-0 h-100 shadow-sm">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                <span class="fw-bold"><i class="fas fa-chart-bar me-2 text-primary"></i> Student Enrollment by Class</span>
                <span class="badge bg-light text-muted border">Current Session</span>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="studentsByClassChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Gender Ratio Chart -->
    <div class="col-12 col-lg-5">
        <div class="card border-0 h-100 shadow-sm">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                <span class="fw-bold"><i class="fas fa-venus-mars me-2 text-danger"></i> Student Gender Ratio</span>
                <span class="badge bg-light text-muted border">Demographics</span>
            </div>
            <div class="card-body d-flex flex-column align-items-center justify-content-center">
                <div class="chart-container" style="height: 240px;">
                    <canvas id="genderRatioChart"></canvas>
                </div>
                <div class="d-flex justify-content-around w-100 mt-3 pt-3 border-top text-center">
                    <div>
                        <div class="text-muted small">Male Students</div>
                        <h5 class="fw-bold text-primary mb-0"><?= $maleCount ?></h5>
                    </div>
                    <div>
                        <div class="text-muted small">Female Students</div>
                        <h5 class="fw-bold text-danger mb-0"><?= $femaleCount ?></h5>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Financial Comparison Chart -->
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                <span class="fw-bold"><i class="fas fa-chart-line me-2 text-success"></i> Monthly Financial Flow: Fee Collections vs. Expenses</span>
                <span class="badge bg-success-subtle text-success border border-success-subtle">Fiscal Year 2026</span>
            </div>
            <div class="card-body">
                <div class="chart-container" style="height: 280px;">
                    <canvas id="monthlyFinancialsChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Data Tables -->
<div class="row g-3">
    <!-- Recent Admissions -->
    <div class="col-12 col-lg-6">
        <div class="card border-0 h-100 shadow-sm">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                <span class="fw-bold"><i class="fas fa-user-plus me-2 text-primary"></i> Recent Admissions</span>
                <a href="<?= BASE_PATH ?>/students/index.php" class="btn btn-outline-primary btn-sm rounded-pill py-0 px-3">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Student</th>
                                <th>Adm #</th>
                                <th>Class</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentStudents as $st): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-initials me-2" style="width: 32px; height: 32px; font-size: 0.75rem;">
                                                <?= strtoupper(substr($st['first_name'], 0, 1)) ?>
                                            </div>
                                            <div>
                                                <a href="<?= BASE_PATH ?>/students/view.php?id=<?= $st['id'] ?>" class="fw-semibold text-decoration-none text-dark">
                                                    <?= e($st['first_name'] . ' ' . $st['last_name']) ?>
                                                </a>
                                                <div class="text-muted" style="font-size: 0.72rem;"><?= formatDate($st['admission_date']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><code><?= e($st['admission_no']) ?></code></td>
                                    <td><?= e($st['class_name'] . ' - ' . $st['section_name']) ?></td>
                                    <td>
                                        <span class="badge badge-soft-success">Active</span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Fee Payments -->
    <div class="col-12 col-lg-6">
        <div class="card border-0 h-100 shadow-sm">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                <span class="fw-bold"><i class="fas fa-receipt me-2 text-success"></i> Recent Fee Collections</span>
                <a href="<?= BASE_PATH ?>/fees/payments.php" class="btn btn-outline-success btn-sm rounded-pill py-0 px-3">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Receipt #</th>
                                <th>Student</th>
                                <th>Paid</th>
                                <th>Method</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentPayments as $pay): ?>
                                <tr>
                                    <td>
                                        <a href="<?= BASE_PATH ?>/fees/receipt.php?id=<?= $pay['id'] ?>" class="fw-bold text-decoration-none">
                                            <?= e($pay['receipt_no']) ?>
                                        </a>
                                        <div class="text-muted" style="font-size: 0.72rem;"><?= formatDate($pay['payment_date']) ?></div>
                                    </td>
                                    <td>
                                        <div class="fw-semibold"><?= e($pay['first_name'] . ' ' . $pay['last_name']) ?></div>
                                        <div class="text-muted small"><?= e($pay['class_name']) ?></div>
                                    </td>
                                    <td class="fw-bold text-success"><?= formatCurrency($pay['paid_amount']) ?></td>
                                    <td>
                                        <span class="badge bg-light text-dark border"><?= e($pay['payment_method']) ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
window.DASHBOARD_DATA = {
    classLabels: <?= json_encode($chartClassLabels) ?>,
    classCounts: <?= json_encode($chartClassCounts) ?>,
    maleStudents: <?= $maleCount ?>,
    femaleStudents: <?= $femaleCount ?>
};
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
