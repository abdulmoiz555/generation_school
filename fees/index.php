<?php
/**
 * Fee Management & Printing Portal Hub
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

requirePermission('fees_view');

// Fetch recent fee payments
$stmt = $pdo->query("
    SELECT p.*, s.first_name, s.last_name, s.admission_no, c.class_name, sec.section_name, u.full_name as receiver_name
    FROM fee_payments p
    JOIN students s ON p.student_id = s.id
    LEFT JOIN classes c ON s.class_id = c.id
    LEFT JOIN sections sec ON s.section_id = sec.id
    LEFT JOIN users u ON p.received_by = u.id
    ORDER BY p.id DESC
    LIMIT 20
");
$recentPayments = $stmt->fetchAll();

// Total collections
$statsStmt = $pdo->query("
    SELECT 
        COALESCE(SUM(paid_amount), 0) as total_collected,
        COUNT(id) as total_receipts
    FROM fee_payments
");
$stats = $statsStmt->fetch();

// Total pending
$pendingStmt = $pdo->query("SELECT COALESCE(SUM(balance), 0) as total_arrears FROM student_fees WHERE status != 'Paid'");
$arrears = $pendingStmt->fetch();

$pageTitle = "Fee Management & Printing Hub";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Fee & Finance</li>
            </ol>
        </nav>
        <h3 class="fw-bold text-dark mb-0"><i class="fas fa-print text-primary me-2"></i> Fee Slips & Finance Hub</h3>
        <p class="text-muted small mb-0">Generation Model School &bull; Collect fees and print slips in <strong>Half A4 Dual</strong> or <strong>80mm POS Thermal</strong></p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="<?= BASE_PATH ?>/fees/collect.php" class="btn btn-primary rounded-pill px-4 shadow-sm fw-bold">
            <i class="fas fa-plus-circle me-1"></i> Collect Fee Now
        </a>
        <a href="<?= BASE_PATH ?>/fees/payments.php?export=excel" class="btn btn-success rounded-pill px-3 shadow-sm">
            <i class="fas fa-file-excel me-1"></i> Download Excel
        </a>
    </div>
</div>

<!-- 4 Key Launch Cards for Printing and Collections -->
<div class="row g-3 mb-4">
    <!-- Card 1: Half A4 Dual Slip -->
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="card border-0 shadow-sm rounded-4 h-100 p-3 bg-white border-top border-success border-4">
            <div class="d-flex align-items-center mb-2">
                <div class="bg-success-subtle text-success p-2 rounded-3 me-2">
                    <i class="fas fa-copy fa-lg"></i>
                </div>
                <h6 class="fw-bold text-dark mb-0">Half A4 Dual Slip</h6>
            </div>
            <p class="text-muted small mb-3">Fits School Copy & Parent Copy side-by-side with perforation line on 1/2 of A4 paper.</p>
            <a href="<?= BASE_PATH ?>/fees/payments.php" class="btn btn-success btn-sm rounded-pill mt-auto w-100 fw-bold">
                <i class="fas fa-print me-1"></i> Print Half A4 Slips
            </a>
        </div>
    </div>

    <!-- Card 2: POS Thermal Receipt -->
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="card border-0 shadow-sm rounded-4 h-100 p-3 bg-white border-top border-dark border-4">
            <div class="d-flex align-items-center mb-2">
                <div class="bg-dark text-white p-2 rounded-3 me-2">
                    <i class="fas fa-receipt fa-lg"></i>
                </div>
                <h6 class="fw-bold text-dark mb-0">POS Thermal (80mm)</h6>
            </div>
            <p class="text-muted small mb-3">Fast receipt printing for continuous rolls on 80mm & 58mm POS thermal printers.</p>
            <a href="<?= BASE_PATH ?>/fees/payments.php" class="btn btn-dark btn-sm rounded-pill mt-auto w-100 fw-bold">
                <i class="fas fa-receipt me-1"></i> Print POS Slips
            </a>
        </div>
    </div>

    <!-- Card 3: Collect Fee -->
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="card border-0 shadow-sm rounded-4 h-100 p-3 bg-white border-top border-primary border-4">
            <div class="d-flex align-items-center mb-2">
                <div class="bg-primary-subtle text-primary p-2 rounded-3 me-2">
                    <i class="fas fa-cash-register fa-lg"></i>
                </div>
                <h6 class="fw-bold text-dark mb-0">Fee Collection</h6>
            </div>
            <p class="text-muted small mb-3">Accept tuition, admission, exam & bus fees in PKR with immediate multi-format printing.</p>
            <a href="<?= BASE_PATH ?>/fees/collect.php" class="btn btn-primary btn-sm rounded-pill mt-auto w-100 fw-bold">
                <i class="fas fa-plus me-1"></i> Collect Fee
            </a>
        </div>
    </div>

    <!-- Card 4: Unpaid Arrears -->
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="card border-0 shadow-sm rounded-4 h-100 p-3 bg-white border-top border-danger border-4">
            <div class="d-flex align-items-center mb-2">
                <div class="bg-danger-subtle text-danger p-2 rounded-3 me-2">
                    <i class="fas fa-exclamation-triangle fa-lg"></i>
                </div>
                <h6 class="fw-bold text-dark mb-0">Unpaid Arrears</h6>
            </div>
            <p class="text-muted small mb-3">Track outstanding fee balances across all classes: <strong><?= formatCurrency($arrears['total_arrears']) ?></strong></p>
            <a href="<?= BASE_PATH ?>/fees/arrears.php" class="btn btn-outline-danger btn-sm rounded-pill mt-auto w-100 fw-bold">
                <i class="fas fa-eye me-1"></i> View Arrears
            </a>
        </div>
    </div>
</div>

<!-- Recent Payments Table with Immediate Half A4 & POS Print Buttons -->
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-header bg-transparent py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h5 class="fw-bold text-dark mb-0"><i class="fas fa-history text-primary me-2"></i> Recent Payments & Print Slips</h5>
            <span class="text-muted small">Select any payment record to print instantly in your preferred format</span>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= BASE_PATH ?>/fees/payments.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                View Full Archive
            </a>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Receipt #</th>
                        <th>Student</th>
                        <th>Class & Sec</th>
                        <th>Date</th>
                        <th>Amount Paid (PKR)</th>
                        <th>Method</th>
                        <th class="text-end" style="min-width: 280px;">Print Slip (Choose Format)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentPayments)): ?>
                        <tr><td colspan="7" class="text-center py-5 text-muted">No fee payments found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($recentPayments as $p): ?>
                            <tr>
                                <td class="fw-bold text-primary">
                                    <a href="<?= BASE_PATH ?>/fees/receipt.php?id=<?= $p['id'] ?>" class="text-decoration-none">
                                        <?= e($p['receipt_no']) ?>
                                    </a>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= e($p['first_name'] . ' ' . $p['last_name']) ?></div>
                                    <small class="text-muted"><?= e($p['admission_no']) ?></small>
                                </td>
                                <td><?= e($p['class_name'] . ' - ' . $p['section_name']) ?></td>
                                <td><?= formatDate($p['payment_date']) ?></td>
                                <td class="fw-bold text-success fs-6"><?= formatCurrency($p['paid_amount']) ?></td>
                                <td><span class="badge bg-light text-dark border"><?= e($p['payment_method']) ?></span></td>
                                <td class="text-end text-nowrap">
                                    <a href="<?= BASE_PATH ?>/fees/receipt-half-a4.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-success rounded-pill px-3 shadow-sm me-1 fw-bold" title="Print Half A4 Dual Slip (School & Parent Copies)">
                                        <i class="fas fa-copy me-1"></i> Half A4 Slip
                                    </a>
                                    <a href="<?= BASE_PATH ?>/fees/receipt-pos.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-dark rounded-pill px-3 shadow-sm me-1 fw-bold" title="Print 80mm POS Thermal Slip">
                                        <i class="fas fa-receipt me-1"></i> POS 80mm
                                    </a>
                                    <a href="<?= BASE_PATH ?>/fees/receipt.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary rounded-pill px-2" title="Full A4 Official Receipt">
                                        <i class="fas fa-file-invoice me-1"></i> A4
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
