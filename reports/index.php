<?php
/**
 * Reports & Analytics Hub
 * Central launching pad for institutional audits, financial, academic and demographic reports
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

requirePermission('dashboard_view');

// Fetch quick aggregates
$studentCount = $pdo->query("SELECT COUNT(*) FROM students WHERE status = 'active'")->fetchColumn();
$teacherCount = $pdo->query("SELECT COUNT(*) FROM teachers WHERE status = 'active'")->fetchColumn();
$feesCollected = $pdo->query("SELECT COALESCE(SUM(paid_amount), 0) FROM fee_payments")->fetchColumn();
$totalExpenses = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM expenses")->fetchColumn();

$pageTitle = "Institutional Reports & Analytics";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Reports Hub</li>
            </ol>
        </nav>
        <h3 class="fw-bold text-dark mb-0"><i class="fas fa-chart-line text-primary me-2"></i> Reports & Analytics Center</h3>
        <p class="text-muted small mb-0">Audited institutional reporting, academic performance metrics, and financial records</p>
    </div>
</div>

<!-- Quick Metric Badges -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-primary-subtle text-primary">
            <div class="small fw-semibold text-uppercase">Total Enrolled</div>
            <div class="fs-3 fw-bold mt-1"><?= $studentCount ?> Students</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-success-subtle text-success">
            <div class="small fw-semibold text-uppercase">Total Revenue</div>
            <div class="fs-3 fw-bold mt-1"><?= formatCurrency($feesCollected) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-danger-subtle text-danger">
            <div class="small fw-semibold text-uppercase">Total Expenses</div>
            <div class="fs-3 fw-bold mt-1"><?= formatCurrency($totalExpenses) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-info-subtle text-info">
            <div class="small fw-semibold text-uppercase">Faculty Staff</div>
            <div class="fs-3 fw-bold mt-1"><?= $teacherCount ?> Teachers</div>
        </div>
    </div>
</div>

<!-- Report Cards Grid -->
<div class="row g-4">
    <!-- Student Reports -->
    <div class="col-12 col-md-6 col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 h-100 p-4 transition-hover">
            <div class="d-flex align-items-center mb-3">
                <div class="rounded-4 bg-primary text-white p-3 me-3">
                    <i class="fas fa-user-graduate fa-2x"></i>
                </div>
                <div>
                    <h5 class="fw-bold mb-1">Student Demographics</h5>
                    <span class="text-muted small">Admissions & Enrolment</span>
                </div>
            </div>
            <p class="text-secondary small mb-4">
                Detailed breakdowns by class, gender, blood group, parent profiles, and student admission history.
            </p>
            <div class="d-flex flex-column gap-2 mt-auto">
                <a href="<?= BASE_PATH ?>/reports/students.php" class="btn btn-outline-primary rounded-pill btn-sm">
                    <i class="fas fa-arrow-right me-1"></i> Demographic Report
                </a>
            </div>
        </div>
    </div>

    <!-- Financial Reports -->
    <div class="col-12 col-md-6 col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 h-100 p-4 transition-hover">
            <div class="d-flex align-items-center mb-3">
                <div class="rounded-4 bg-success text-white p-3 me-3">
                    <i class="fas fa-file-invoice-dollar fa-2x"></i>
                </div>
                <div>
                    <h5 class="fw-bold mb-1">Fee & Revenue Audit</h5>
                    <span class="text-muted small">Financial Collections</span>
                </div>
            </div>
            <p class="text-secondary small mb-4">
                Audited fee collection records, breakdown by cash/bank/online channels, discounts, and defaulters.
            </p>
            <div class="d-flex flex-column gap-2 mt-auto">
                <a href="<?= BASE_PATH ?>/reports/fees.php" class="btn btn-outline-success rounded-pill btn-sm">
                    <i class="fas fa-arrow-right me-1"></i> Fee Collection Report
                </a>
                <a href="<?= BASE_PATH ?>/fees/arrears.php" class="btn btn-light rounded-pill btn-sm text-secondary">
                    <i class="fas fa-clock me-1"></i> Outstanding Arrears
                </a>
            </div>
        </div>
    </div>

    <!-- Attendance Reports -->
    <div class="col-12 col-md-6 col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 h-100 p-4 transition-hover">
            <div class="d-flex align-items-center mb-3">
                <div class="rounded-4 bg-info text-white p-3 me-3">
                    <i class="fas fa-calendar-check fa-2x"></i>
                </div>
                <div>
                    <h5 class="fw-bold mb-1">Attendance Analytics</h5>
                    <span class="text-muted small">Daily & Monthly Tracking</span>
                </div>
            </div>
            <p class="text-secondary small mb-4">
                Student and teacher attendance statistics, percentage calculations, and low attendance alerts.
            </p>
            <div class="d-flex flex-column gap-2 mt-auto">
                <a href="<?= BASE_PATH ?>/reports/attendance.php" class="btn btn-outline-info rounded-pill btn-sm">
                    <i class="fas fa-arrow-right me-1"></i> Attendance Analytics
                </a>
                <a href="<?= BASE_PATH ?>/attendance/reports.php" class="btn btn-light rounded-pill btn-sm text-secondary">
                    <i class="fas fa-calendar-alt me-1"></i> Monthly Register
                </a>
            </div>
        </div>
    </div>

    <!-- Academic & Exam Reports -->
    <div class="col-12 col-md-6 col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 h-100 p-4 transition-hover">
            <div class="d-flex align-items-center mb-3">
                <div class="rounded-4 bg-warning text-dark p-3 me-3">
                    <i class="fas fa-award fa-2x"></i>
                </div>
                <div>
                    <h5 class="fw-bold mb-1">Academic & Exams</h5>
                    <span class="text-muted small">Results & Tabulation</span>
                </div>
            </div>
            <p class="text-secondary small mb-4">
                Master tabulation sheets, student grade reports, pass/fail percentage ratios, and rank sheets.
            </p>
            <div class="d-flex flex-column gap-2 mt-auto">
                <a href="<?= BASE_PATH ?>/examinations/tabulation.php" class="btn btn-outline-warning rounded-pill btn-sm text-dark">
                    <i class="fas fa-arrow-right me-1"></i> Tabulation Sheet
                </a>
                <a href="<?= BASE_PATH ?>/examinations/results.php" class="btn btn-light rounded-pill btn-sm text-secondary">
                    <i class="fas fa-poll me-1"></i> Student Result Cards
                </a>
            </div>
        </div>
    </div>

    <!-- Expense Reports -->
    <div class="col-12 col-md-6 col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 h-100 p-4 transition-hover">
            <div class="d-flex align-items-center mb-3">
                <div class="rounded-4 bg-danger text-white p-3 me-3">
                    <i class="fas fa-money-bill-wave fa-2x"></i>
                </div>
                <div>
                    <h5 class="fw-bold mb-1">Institutional Expenses</h5>
                    <span class="text-muted small">Expenditure Tracking</span>
                </div>
            </div>
            <p class="text-secondary small mb-4">
                Monthly school operational expenses categorized by utilities, maintenance, supplies, and miscellaneous.
            </p>
            <div class="d-flex flex-column gap-2 mt-auto">
                <a href="<?= BASE_PATH ?>/reports/expenses.php" class="btn btn-outline-danger rounded-pill btn-sm">
                    <i class="fas fa-arrow-right me-1"></i> Expense Report
                </a>
            </div>
        </div>
    </div>

    <!-- HR & Payroll -->
    <div class="col-12 col-md-6 col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 h-100 p-4 transition-hover">
            <div class="d-flex align-items-center mb-3">
                <div class="rounded-4 bg-secondary text-white p-3 me-3">
                    <i class="fas fa-users-cog fa-2x"></i>
                </div>
                <div>
                    <h5 class="fw-bold mb-1">HR & Payroll Ledger</h5>
                    <span class="text-muted small">Staff Compensation</span>
                </div>
            </div>
            <p class="text-secondary small mb-4">
                Employee directories, monthly salary disbursement records, leave tracking, and printable payslips.
            </p>
            <div class="d-flex flex-column gap-2 mt-auto">
                <a href="<?= BASE_PATH ?>/hr/payroll.php" class="btn btn-outline-secondary rounded-pill btn-sm">
                    <i class="fas fa-arrow-right me-1"></i> Payroll Sheet
                </a>
                <a href="<?= BASE_PATH ?>/hr/leaves.php" class="btn btn-light rounded-pill btn-sm text-secondary">
                    <i class="fas fa-calendar-times me-1"></i> Leave Applications
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
