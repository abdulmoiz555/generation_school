<?php
/**
 * Fee Payment Receipts Archive & Printing Hub
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

requirePermission('fees_view');

$search = trim($_GET['search'] ?? '');
$method = trim($_GET['payment_method'] ?? '');
$fromDate = trim($_GET['from_date'] ?? '');
$toDate = trim($_GET['to_date'] ?? '');

$where = "WHERE 1=1";
$params = [];

if (!empty($search)) {
    $where .= " AND (p.receipt_no LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR s.admission_no LIKE ?)";
    $term = "%{$search}%";
    $params = array_merge($params, [$term, $term, $term, $term]);
}
if (!empty($method)) {
    $where .= " AND p.payment_method = ?";
    $params[] = $method;
}
if (!empty($fromDate)) {
    $where .= " AND p.payment_date >= ?";
    $params[] = $fromDate;
}
if (!empty($toDate)) {
    $where .= " AND p.payment_date <= ?";
    $params[] = $toDate;
}

$stmt = $pdo->prepare("
    SELECT p.*, s.first_name, s.last_name, s.admission_no, c.class_name, sec.section_name, u.full_name as receiver_name
    FROM fee_payments p
    JOIN students s ON p.student_id = s.id
    LEFT JOIN classes c ON s.class_id = c.id
    LEFT JOIN sections sec ON s.section_id = sec.id
    LEFT JOIN users u ON p.received_by = u.id
    {$where}
    ORDER BY p.id DESC
");
$stmt->execute($params);
$payments = $stmt->fetchAll();

$totalCollected = array_sum(array_column($payments, 'paid_amount'));

// Handle Excel / CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    $filename = "Fee_Payments_Register_" . date('Ymd_His') . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
    
    fputcsv($out, ['GENERATION MODEL SCHOOL - FEE PAYMENTS & COLLECTIONS REGISTER']);
    fputcsv($out, ['Currency', 'PKR']);
    fputcsv($out, ['Total Records', count($payments)]);
    fputcsv($out, ['Total Collected', 'PKR ' . number_format($totalCollected, 2)]);
    fputcsv($out, ['Export Date', date('Y-m-d H:i:s')]);
    fputcsv($out, []);
    
    fputcsv($out, ['Receipt #', 'Student Name', 'Admission No', 'Class', 'Section', 'Payment Date', 'Payment Method', 'Paid Amount (PKR)', 'Remaining Balance', 'Received By']);
    
    foreach ($payments as $p) {
        fputcsv($out, [
            $p['receipt_no'],
            $p['first_name'] . ' ' . $p['last_name'],
            $p['admission_no'],
            $p['class_name'],
            $p['section_name'],
            $p['payment_date'],
            $p['payment_method'],
            number_format($p['paid_amount'], 2, '.', ''),
            number_format($p['balance_remaining'], 2, '.', ''),
            $p['receiver_name'] ?: 'Staff'
        ]);
    }
    
    fclose($out);
    exit;
}

$pageTitle = "Fee Receipts & Printing Center";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3 no-print">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item">Fees</li>
                <li class="breadcrumb-item active">Print Fee Slips</li>
            </ol>
        </nav>
        <h3 class="fw-bold text-dark mb-0">Fee Receipts & Print Slips Center</h3>
        <p class="text-muted small mb-0">Print fee receipts in <strong>Half A4 Dual Slip</strong>, <strong>POS Thermal (80mm)</strong>, or <strong>Standard A4</strong></p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'excel'])) ?>" class="btn btn-success btn-sm rounded-pill px-3 shadow-sm">
            <i class="fas fa-file-excel me-1"></i> Download Excel
        </a>
        <a href="<?= BASE_PATH ?>/fees/collect.php" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm">
            <i class="fas fa-cash-register me-1"></i> + Collect Fee
        </a>
    </div>
</div>

<!-- Highlight Banner: Print Options Explained -->
<div class="card border-0 bg-primary-subtle shadow-sm rounded-4 p-3 mb-4 no-print border-start border-primary border-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
        <div>
            <div class="fw-bold text-primary fs-6 mb-1">
                <i class="fas fa-print me-2"></i> Choose Your Printable Format for Any Receipt Below:
            </div>
            <div class="d-flex flex-wrap gap-2 small text-dark mt-2">
                <span class="badge bg-success px-3 py-2 fs-7 rounded-pill">
                    <i class="fas fa-copy me-1"></i> <strong>Half A4 Dual Slip:</strong> Fits School & Parent copy side-by-side on 1/2 A4
                </span>
                <span class="badge bg-dark px-3 py-2 fs-7 rounded-pill">
                    <i class="fas fa-receipt me-1"></i> <strong>POS 80mm Thermal:</strong> Continuous roll receipt for POS thermal printers
                </span>
                <span class="badge bg-primary px-3 py-2 fs-7 rounded-pill">
                    <i class="fas fa-file-invoice me-1"></i> <strong>Full A4 Receipt:</strong> Full official institutional letterhead receipt
                </span>
            </div>
        </div>
        <div class="text-nowrap">
            <span class="text-muted small d-block mb-1">Currency: <strong>PKR (₨)</strong></span>
            <span class="fw-bold text-dark fs-5"><?= count($payments) ?> Payments Recorded</span>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card border-0 shadow-sm mb-4 no-print rounded-4">
    <div class="card-body p-3">
        <form action="<?= BASE_PATH ?>/fees/payments.php" method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Search Student or Receipt #</label>
                <input type="text" name="search" class="form-control form-control-sm" placeholder="e.g. REC-2026-0001 or Alexander..." value="<?= e($search) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold">Payment Method</label>
                <select name="payment_method" class="form-select form-select-sm">
                    <option value="">All Methods</option>
                    <option value="Cash" <?= $method === 'Cash' ? 'selected' : '' ?>>Cash</option>
                    <option value="Bank" <?= $method === 'Bank' ? 'selected' : '' ?>>Bank</option>
                    <option value="Online" <?= $method === 'Online' ? 'selected' : '' ?>>Online</option>
                    <option value="Cheque" <?= $method === 'Cheque' ? 'selected' : '' ?>>Cheque</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold">From Date</label>
                <input type="date" name="from_date" class="form-control form-control-sm" value="<?= e($fromDate) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold">To Date</label>
                <input type="date" name="to_date" class="form-control form-control-sm" value="<?= e($toDate) ?>">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm w-100">Filter</button>
                <a href="<?= BASE_PATH ?>/fees/payments.php" class="btn btn-light btn-sm border">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-header bg-transparent py-3 d-flex justify-content-between align-items-center">
        <span class="fw-bold">Total Collection in View: <span class="text-success fs-5"><?= formatCurrency($totalCollected) ?></span></span>
        <button onclick="window.print()" class="btn btn-sm btn-outline-secondary"><i class="fas fa-print me-1"></i> Print Register</button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Receipt #</th>
                        <th>Student</th>
                        <th>Class & Sec</th>
                        <th>Payment Date</th>
                        <th>Paid Amount</th>
                        <th>Method</th>
                        <th>Received By</th>
                        <th class="text-end" style="min-width: 280px;"><i class="fas fa-print me-1"></i> Print Fee Slips (3 Formats)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payments)): ?>
                        <tr><td colspan="8" class="text-center py-5 text-muted">No fee payments found matching filters.</td></tr>
                    <?php else: ?>
                        <?php foreach ($payments as $p): ?>
                            <tr>
                                <td class="fw-bold text-primary">
                                    <a href="<?= BASE_PATH ?>/fees/receipt.php?id=<?= $p['id'] ?>" class="text-decoration-none">
                                        <?= e($p['receipt_no']) ?>
                                    </a>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= e($p['first_name'] . ' ' . $p['last_name']) ?></div>
                                    <code><?= e($p['admission_no']) ?></code>
                                </td>
                                <td><?= e($p['class_name'] . ' - ' . $p['section_name']) ?></td>
                                <td><?= formatDate($p['payment_date']) ?></td>
                                <td class="fw-bold text-success fs-6"><?= formatCurrency($p['paid_amount']) ?></td>
                                <td><span class="badge bg-light text-dark border"><?= e($p['payment_method']) ?></span></td>
                                <td><span class="small text-muted"><?= e($p['receiver_name'] ?: 'Staff') ?></span></td>
                                <td class="text-end text-nowrap">
                                    <!-- Half A4 Dual Slip Button -->
                                    <a href="<?= BASE_PATH ?>/fees/receipt-half-a4.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-success rounded-pill px-2 py-1 shadow-sm me-1" title="Print Half A4 Dual Slip (School & Parent Copy on Half Sheet)">
                                        <i class="fas fa-copy me-1"></i> Half A4 Slip
                                    </a>
                                    <!-- POS 80mm Thermal Slip Button -->
                                    <a href="<?= BASE_PATH ?>/fees/receipt-pos.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-dark rounded-pill px-2 py-1 shadow-sm me-1" title="Print 80mm POS Thermal Slip">
                                        <i class="fas fa-receipt me-1"></i> POS 80mm
                                    </a>
                                    <!-- Full A4 Receipt Button -->
                                    <a href="<?= BASE_PATH ?>/fees/receipt.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary rounded-pill px-2 py-1" title="Print Full A4 Official Receipt">
                                        <i class="fas fa-file-invoice me-1"></i> Full A4
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
