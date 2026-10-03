<?php
/**
 * Fee Collection & Financial Reports
 * Detailed revenue breakdown by payment mode, class, date range, with CSV export
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

requirePermission('fee_reports');

$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$classId = isset($_GET['class_id']) && $_GET['class_id'] !== '' ? (int)$_GET['class_id'] : null;
$paymentMethod = $_GET['payment_method'] ?? '';

// Build Query
$query = "
    SELECT p.*, s.first_name, s.last_name, s.admission_no, c.class_name, sec.section_name
    FROM fee_payments p
    JOIN students s ON p.student_id = s.id
    LEFT JOIN classes c ON s.class_id = c.id
    LEFT JOIN sections sec ON s.section_id = sec.id
    WHERE DATE(p.payment_date) BETWEEN ? AND ?
";
$params = [$startDate, $endDate];

if ($classId) {
    $query .= " AND s.class_id = ?";
    $params[] = $classId;
}
if ($paymentMethod) {
    $query .= " AND p.payment_method = ?";
    $params[] = $paymentMethod;
}
$query .= " ORDER BY p.payment_date DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$payments = $stmt->fetchAll();

// CSV Export Action
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=fee_collection_report_' . date('Ymd_His') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Receipt No', 'Date', 'Student Name', 'Admission No', 'Class', 'Method', 'Amount Paid', 'Discount', 'Fine']);

    foreach ($payments as $p) {
        fputcsv($output, [
            $p['receipt_no'],
            $p['payment_date'],
            $p['first_name'] . ' ' . $p['last_name'],
            $p['admission_no'],
            $p['class_name'] . ' (' . ($p['section_name'] ?: 'A') . ')',
            $p['payment_method'],
            $p['paid_amount'],
            $p['discount_amount'] ?? 0,
            $p['fine_amount'] ?? 0
        ]);
    }
    fclose($output);
    exit;
}

// Totals
$totalCollected = 0;
$totalDiscount = 0;
$totalFine = 0;
$modeBreakdown = [];
foreach ($payments as $p) {
    $totalCollected += (float)$p['paid_amount'];
    $totalDiscount += (float)($p['discount_amount'] ?? 0);
    $totalFine += (float)($p['fine_amount'] ?? 0);
    $m = $p['payment_method'] ?: 'Other';
    $modeBreakdown[$m] = ($modeBreakdown[$m] ?? 0) + (float)$p['paid_amount'];
}

$classes = $pdo->query("SELECT * FROM classes ORDER BY numeric_level ASC")->fetchAll();

$pageTitle = "Fee Collection Report";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3 no-print">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/reports/index.php">Reports</a></li>
                <li class="breadcrumb-item active">Fee Collection</li>
            </ol>
        </nav>
        <h3 class="fw-bold text-dark mb-0"><i class="fas fa-file-invoice-dollar text-primary me-2"></i> Fee Collection Report</h3>
        <p class="text-muted small mb-0">Audited revenue streams, fee payment journals, and payment mode breakdowns</p>
    </div>
    <div class="d-flex gap-2">
        <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" class="btn btn-outline-success rounded-pill px-3">
            <i class="fas fa-file-excel me-1"></i> Export CSV
        </a>
        <button type="button" onclick="window.print()" class="btn btn-primary rounded-pill px-4 shadow-sm">
            <i class="fas fa-print me-1"></i> Print Report
        </button>
    </div>
</div>

<!-- Filter card -->
<div class="card border-0 shadow-sm rounded-4 mb-4 no-print">
    <div class="card-body p-3">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-md-3">
                <label class="small text-muted mb-1 fw-bold">From Date</label>
                <input type="date" name="start_date" class="form-control form-control-sm" value="<?= e($startDate) ?>">
            </div>
            <div class="col-md-3">
                <label class="small text-muted mb-1 fw-bold">To Date</label>
                <input type="date" name="end_date" class="form-control form-control-sm" value="<?= e($endDate) ?>">
            </div>
            <div class="col-md-2">
                <label class="small text-muted mb-1 fw-bold">Class</label>
                <select name="class_id" class="form-select form-select-sm">
                    <option value="">All Classes</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $classId == $c['id'] ? 'selected' : '' ?>><?= e($c['class_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="small text-muted mb-1 fw-bold">Payment Method</label>
                <select name="payment_method" class="form-select form-select-sm">
                    <option value="">All Methods</option>
                    <option value="Cash" <?= $paymentMethod === 'Cash' ? 'selected' : '' ?>>Cash</option>
                    <option value="Bank" <?= $paymentMethod === 'Bank' ? 'selected' : '' ?>>Bank</option>
                    <option value="Online" <?= $paymentMethod === 'Online' ? 'selected' : '' ?>>Online</option>
                    <option value="Cheque" <?= $paymentMethod === 'Cheque' ? 'selected' : '' ?>>Cheque</option>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-dark btn-sm w-100"><i class="fas fa-filter me-1"></i> Filter</button>
            </div>
        </form>
    </div>
</div>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-primary-subtle text-primary h-100">
            <div class="small fw-semibold text-uppercase">Total Collections</div>
            <div class="fs-2 fw-bold mt-1"><?= formatCurrency($totalCollected) ?></div>
            <div class="small opacity-75 mt-1"><?= count($payments) ?> Transactions in Period</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-warning-subtle text-warning h-100">
            <div class="small fw-semibold text-uppercase">Discounts Granted</div>
            <div class="fs-2 fw-bold mt-1"><?= formatCurrency($totalDiscount) ?></div>
            <div class="small opacity-75 mt-1">Scholarships & Concessions</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-danger-subtle text-danger h-100">
            <div class="small fw-semibold text-uppercase">Fines Collected</div>
            <div class="fs-2 fw-bold mt-1"><?= formatCurrency($totalFine) ?></div>
            <div class="small opacity-75 mt-1">Late payment surcharges</div>
        </div>
    </div>
</div>

<!-- Table -->
<div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
    <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
        <h6 class="fw-bold text-dark mb-0">Transactions (<?= e($startDate) ?> to <?= e($endDate) ?>)</h6>
        <span class="text-muted small">Showing <?= count($payments) ?> records</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" style="font-size: 0.9rem;">
            <thead class="table-light">
                <tr>
                    <th>Receipt #</th>
                    <th>Date</th>
                    <th>Student</th>
                    <th>Class</th>
                    <th>Method</th>
                    <th class="text-end">Amount</th>
                    <th class="text-end no-print">Receipt</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($payments)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">No fee transactions found for this date range.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($payments as $p): ?>
                    <tr>
                        <td class="fw-bold text-primary font-monospace"><?= e($p['receipt_no']) ?></td>
                        <td class="text-muted small"><?= date('M d, Y', strtotime($p['payment_date'])) ?></td>
                        <td>
                            <div class="fw-semibold text-dark"><?= e($p['first_name'] . ' ' . $p['last_name']) ?></div>
                            <small class="text-muted"><?= e($p['admission_no']) ?></small>
                        </td>
                        <td><?= e($p['class_name']) ?> <small class="text-muted">(<?= e($p['section_name'] ?: 'A') ?>)</small></td>
                        <td><span class="badge bg-light text-dark border"><?= e($p['payment_method'] ?: 'Cash') ?></span></td>
                        <td class="text-end fw-bold text-success"><?= formatCurrency($p['paid_amount']) ?></td>
                        <td class="text-end no-print text-nowrap">
                            <div class="btn-group btn-group-sm">
                                <a href="<?= BASE_PATH ?>/fees/receipt.php?id=<?= $p['id'] ?>" class="btn btn-outline-primary btn-sm py-0 px-2" title="Full A4 Receipt">
                                    <i class="fas fa-print"></i>
                                </a>
                                <a href="<?= BASE_PATH ?>/fees/receipt-half-a4.php?id=<?= $p['id'] ?>" class="btn btn-outline-primary btn-sm py-0 px-2" title="Half A4 Dual Slip">
                                    <i class="fas fa-copy"></i>
                                </a>
                                <a href="<?= BASE_PATH ?>/fees/receipt-pos.php?id=<?= $p['id'] ?>" class="btn btn-outline-dark btn-sm py-0 px-2" title="POS Thermal Slip">
                                    <i class="fas fa-receipt"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
