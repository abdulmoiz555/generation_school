<?php
/**
 * Fee Payment Records & History
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

requirePermission('fees_collect');

$search = trim($_GET['search'] ?? '');
$method = trim($_GET['payment_method'] ?? '');
$fromDate = $_GET['from_date'] ?? '';
$toDate = $_GET['to_date'] ?? '';

$conditions = [];
$params = [];

if (!empty($search)) {
    $conditions[] = "(p.receipt_no LIKE :s OR s.first_name LIKE :s OR s.last_name LIKE :s OR s.admission_no LIKE :s)";
    $params[':s'] = "%{$search}%";
}
if (!empty($method)) {
    $conditions[] = "p.payment_method = :m";
    $params[':m'] = $method;
}
if (!empty($fromDate)) {
    $conditions[] = "p.payment_date >= :from";
    $params[':from'] = $fromDate;
}
if (!empty($toDate)) {
    $conditions[] = "p.payment_date <= :to";
    $params[':to'] = $toDate;
}

$where = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

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

$pageTitle = "Fee Payment Records";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3 no-print">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item">Fees</li>
                <li class="breadcrumb-item active">Payments</li>
            </ol>
        </nav>
        <h3 class="fw-bold text-dark mb-0">Fee Payment Receipts Archive</h3>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_PATH ?>/fees/collect.php" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm">
            <i class="fas fa-cash-register me-1"></i> + Collect Fee
        </a>
    </div>
</div>

<!-- Filters -->
<div class="card border-0 shadow-sm mb-4 no-print">
    <div class="card-body p-3">
        <form action="<?= BASE_PATH ?>/fees/payments.php" method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Search</label>
                <input type="text" name="search" class="form-control" placeholder="Receipt # or student..." value="<?= e($search) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold">Method</label>
                <select name="payment_method" class="form-select">
                    <option value="">All Methods</option>
                    <option value="Cash" <?= $method === 'Cash' ? 'selected' : '' ?>>Cash</option>
                    <option value="Bank" <?= $method === 'Bank' ? 'selected' : '' ?>>Bank</option>
                    <option value="Online" <?= $method === 'Online' ? 'selected' : '' ?>>Online</option>
                    <option value="Cheque" <?= $method === 'Cheque' ? 'selected' : '' ?>>Cheque</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold">From Date</label>
                <input type="date" name="from_date" class="form-control" value="<?= e($fromDate) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold">To Date</label>
                <input type="date" name="to_date" class="form-control" value="<?= e($toDate) ?>">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100">Filter</button>
                <a href="<?= BASE_PATH ?>/fees/payments.php" class="btn btn-light border">Reset</a>
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
                        <th class="text-end">Print Receipt</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payments)): ?>
                        <tr><td colspan="8" class="text-center py-4 text-muted">No fee payments found matching filters.</td></tr>
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
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= BASE_PATH ?>/fees/receipt.php?id=<?= $p['id'] ?>" class="btn btn-outline-primary" title="Print Full A4 Receipt">
                                            <i class="fas fa-print me-1"></i> A4
                                        </a>
                                        <a href="<?= BASE_PATH ?>/fees/receipt-half-a4.php?id=<?= $p['id'] ?>" class="btn btn-outline-primary" title="Print Half A4 Dual Slip (School + Parent)">
                                            <i class="fas fa-copy me-1"></i> Half A4
                                        </a>
                                        <a href="<?= BASE_PATH ?>/fees/receipt-pos.php?id=<?= $p['id'] ?>" class="btn btn-outline-dark" title="Print POS Thermal 80mm Slip">
                                            <i class="fas fa-receipt me-1"></i> POS
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
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
