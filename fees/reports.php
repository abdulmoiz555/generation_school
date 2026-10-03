<?php
/**
 * Financial & Fee Analytics Reports (Section 37)
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

requirePermission('fees_manage');

$todayDate = date('Y-m-d');
$currentMonth = date('Y-m');
$currentYear = date('Y');

// Metrics
$dailyTotal = (float)$pdo->query("SELECT COALESCE(SUM(paid_amount), 0) FROM fee_payments WHERE payment_date = '{$todayDate}'")->fetchColumn();
$monthlyTotal = (float)$pdo->query("SELECT COALESCE(SUM(paid_amount), 0) FROM fee_payments WHERE DATE_FORMAT(payment_date, '%Y-%m') = '{$currentMonth}'")->fetchColumn();
$yearlyTotal = (float)$pdo->query("SELECT COALESCE(SUM(paid_amount), 0) FROM fee_payments WHERE DATE_FORMAT(payment_date, '%Y') = '{$currentYear}'")->fetchColumn();
$allPendingTotal = (float)$pdo->query("SELECT COALESCE(SUM(balance), 0) FROM student_fees WHERE status IN ('Pending', 'Partial', 'Overdue')")->fetchColumn();

// Payment Method Distribution
$methodStmt = $pdo->query("
    SELECT payment_method, COUNT(*) as txn_count, SUM(paid_amount) as total_amt 
    FROM fee_payments 
    GROUP BY payment_method 
    ORDER BY total_amt DESC
");
$methodStats = $methodStmt->fetchAll();

// Recent Collections Register
$recentStmt = $pdo->query("
    SELECT p.*, s.first_name, s.last_name, s.admission_no, c.class_name
    FROM fee_payments p
    JOIN students s ON p.student_id = s.id
    LEFT JOIN classes c ON s.class_id = c.id
    ORDER BY p.id DESC LIMIT 15
");
$recentCollections = $recentStmt->fetchAll();

$pageTitle = "Financial Fee Reports";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3 no-print">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item">Fees</li>
                <li class="breadcrumb-item active">Financial Reports</li>
            </ol>
        </nav>
        <h3 class="fw-bold text-dark mb-0">Financial & Fee Collection Reports</h3>
    </div>
    <button onclick="window.print()" class="btn btn-outline-secondary btn-sm rounded-pill px-3 shadow-sm">
        <i class="fas fa-print me-1"></i> Print Financial Statement
    </button>
</div>

<!-- Financial Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card stat-card border-0">
            <div>
                <div class="stat-label">Today's Collection</div>
                <div class="stat-number text-success"><?= formatCurrency($dailyTotal) ?></div>
                <div class="text-muted small mt-1">Receipted today</div>
            </div>
            <div class="stat-icon success"><i class="fas fa-calendar-day"></i></div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card border-0">
            <div>
                <div class="stat-label">This Month (<?= date('M Y') ?>)</div>
                <div class="stat-number text-primary"><?= formatCurrency($monthlyTotal) ?></div>
                <div class="text-muted small mt-1">Current billing month</div>
            </div>
            <div class="stat-icon primary"><i class="fas fa-calendar-alt"></i></div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card border-0">
            <div>
                <div class="stat-label">Fiscal Year <?= $currentYear ?></div>
                <div class="stat-number text-info"><?= formatCurrency($yearlyTotal) ?></div>
                <div class="text-muted small mt-1">Total revenue collected</div>
            </div>
            <div class="stat-icon info"><i class="fas fa-chart-line"></i></div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card border-0">
            <div>
                <div class="stat-label">Total Outstanding Arrears</div>
                <div class="stat-number text-danger"><?= formatCurrency($allPendingTotal) ?></div>
                <div class="text-muted small mt-1">Unpaid student dues</div>
            </div>
            <div class="stat-icon danger"><i class="fas fa-exclamation-triangle"></i></div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Channel Breakdown -->
    <div class="col-12 col-lg-5">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-transparent py-3">
                <h6 class="fw-bold text-dark mb-0"><i class="fas fa-wallet me-2 text-primary"></i> Collection Channels</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Method</th>
                                <th>Transactions</th>
                                <th class="text-end">Total Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($methodStats as $m): ?>
                                <tr>
                                    <td><span class="badge bg-light text-dark border"><?= e($m['payment_method']) ?></span></td>
                                    <td><?= $m['txn_count'] ?> payments</td>
                                    <td class="text-end fw-bold text-success"><?= formatCurrency($m['total_amt']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Receipts Ledger -->
    <div class="col-12 col-lg-7">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-transparent py-3 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold text-dark mb-0"><i class="fas fa-receipt me-2 text-success"></i> Recent Collections Ledger</h6>
                <a href="<?= BASE_PATH ?>/fees/payments.php" class="btn btn-xs btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Receipt</th>
                                <th>Student</th>
                                <th>Date</th>
                                <th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentCollections as $rc): ?>
                                <tr>
                                    <td>
                                        <a href="<?= BASE_PATH ?>/fees/receipt.php?id=<?= $rc['id'] ?>" class="fw-bold text-decoration-none">
                                            <?= e($rc['receipt_no']) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-dark"><?= e($rc['first_name'] . ' ' . $rc['last_name']) ?></div>
                                        <small class="text-muted"><?= e($rc['class_name']) ?></small>
                                    </td>
                                    <td><?= formatDate($rc['payment_date']) ?></td>
                                    <td class="text-end fw-bold text-success"><?= formatCurrency($rc['paid_amount']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
