<?php
/**
 * Official Printable Fee Receipt (Section 19)
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$paymentId = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("
    SELECT p.*, s.first_name, s.last_name, s.admission_no, s.roll_no,
           c.class_name, sec.section_name, pr.father_name,
           u.full_name as receiver_name, sess.session_name
    FROM fee_payments p
    JOIN students s ON p.student_id = s.id
    LEFT JOIN classes c ON s.class_id = c.id
    LEFT JOIN sections sec ON s.section_id = sec.id
    LEFT JOIN parents pr ON s.parent_id = pr.id
    LEFT JOIN users u ON p.received_by = u.id
    LEFT JOIN academic_sessions sess ON p.session_id = sess.id
    WHERE p.id = ?
");
$stmt->execute([$paymentId]);
$payment = $stmt->fetch();

if (!$payment) {
    setFlashMessage('error', 'Payment receipt record not found.');
    header("Location: " . BASE_PATH . "/fees/payments.php");
    exit;
}

// Payment item breakdown
$itemStmt = $pdo->prepare("
    SELECT fpi.*, sf.month, ft.name as fee_name
    FROM fee_payment_items fpi
    JOIN student_fees sf ON fpi.student_fee_id = sf.id
    JOIN fee_types ft ON sf.fee_type_id = ft.id
    WHERE fpi.payment_id = ?
");
$itemStmt->execute([$paymentId]);
$items = $itemStmt->fetchAll();

$pageTitle = "Receipt #" . $payment['receipt_no'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3 no-print">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/fees/payments.php">Fee Payments</a></li>
                <li class="breadcrumb-item active"><?= e($payment['receipt_no']) ?></li>
            </ol>
        </nav>
        <h3 class="fw-bold text-dark mb-0">Official Fee Receipt</h3>
    </div>

    <!-- Actions -->
    <div class="d-flex flex-wrap gap-2 align-items-center">
        <a href="<?= BASE_PATH ?>/fees/receipt-half-a4.php?id=<?= $payment['id'] ?>&autoprint=1" class="btn btn-success rounded-pill px-4 shadow-sm fw-bold">
            <i class="fas fa-print me-1"></i> Print Fee Slip (Half A4)
        </a>

        <a href="<?= BASE_PATH ?>/fees/collect.php?student_id=<?= $payment['student_id'] ?>" class="btn btn-outline-dark rounded-pill px-3">
            + New Collection
        </a>
    </div>
</div>

<!-- Standout Print Format Selector Banner -->
<div class="card border-0 bg-white shadow-sm rounded-4 p-3 mb-4 no-print border-start border-success border-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div>
            <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1 mb-1">
                <i class="fas fa-check-circle me-1"></i> Receipt #<?= e($payment['receipt_no']) ?> Ready
            </span>
            <h5 class="fw-bold text-dark mb-0">Official Fee Slip Printing:</h5>
            <div class="text-muted small">Default slip is formatted for <strong>Half A4 Paper</strong> with School Copy & Parent Copy side-by-side.</div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?= BASE_PATH ?>/fees/receipt-half-a4.php?id=<?= $payment['id'] ?>&autoprint=1" class="btn btn-success fw-bold rounded-pill px-4 shadow-sm">
                <i class="fas fa-print me-1"></i> Print Fee Slip (Half A4)
            </a>
            <button type="button" onclick="triggerPrint()" class="btn btn-outline-primary fw-bold rounded-pill px-3 shadow-sm">
                <i class="fas fa-file-invoice me-1"></i> Full A4 View
            </button>
        </div>
    </div>
</div>

<div class="printable-area py-3">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-9">
            
            <div class="receipt-box bg-white rounded-4 shadow-sm border p-4 p-md-5 position-relative overflow-hidden">
                <!-- Centered School Logo Watermark -->
                <div class="receipt-watermark" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 280px; height: 280px; opacity: 0.14; pointer-events: none; z-index: 0; display: flex; align-items: center; justify-content: center; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important;">
                    <img src="<?= BASE_PATH ?>/assets/img/generation_school_logo.jpg" alt="Watermark" style="width: 100%; height: 100%; object-fit: contain; border-radius: 50%; filter: grayscale(15%); -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important;">
                </div>
                <div style="position: relative; z-index: 1;">
                
                <!-- School Letterhead Header -->
                <div class="receipt-header pb-4 mb-4 border-bottom text-center">
                    <div class="d-flex align-items-center justify-content-center gap-3 mb-2">
                        <img src="<?= BASE_PATH ?>/assets/img/generation_school_logo.jpg" alt="Logo" class="rounded-circle shadow-sm" style="width: 56px; height: 56px; object-fit: cover; border: 1px solid #cbd5e1;" onerror="this.outerHTML='<i class=\'fas fa-graduation-cap text-primary fs-1\'></i>'">
                        <h2 class="fw-bold text-dark mb-0 text-uppercase letter-spacing-1">
                            <?= e(getSetting('school_name', 'Generation Model School')) ?>
                        </h2>
                    </div>
                    <p class="text-muted small mb-1"><?= e(getSetting('school_motto', 'Excellence in Education, Character in Leadership')) ?></p>
                    <div class="small text-muted">
                        <span><i class="fas fa-map-marker-alt me-1"></i> <?= e(getSetting('address', '742 Evergreen Terrace')) ?>, <?= e(getSetting('city', 'Springfield')) ?></span> &bull; 
                        <span><i class="fas fa-phone me-1"></i> <?= e(getSetting('phone', '+1 555-0199')) ?></span> &bull; 
                        <span><i class="fas fa-envelope me-1"></i> <?= e(getSetting('email', 'info@springfield.edu')) ?></span>
                    </div>
                    <div class="mt-2">
                        <span class="badge bg-dark px-4 py-2 fs-6 text-uppercase">Official Payment Receipt</span>
                    </div>
                </div>

                <!-- Receipt Metadata Grid -->
                <div class="row g-3 mb-4 small">
                    <div class="col-6 col-md-3">
                        <span class="text-muted d-block">Receipt Number:</span>
                        <strong class="fs-6 text-primary"><?= e($payment['receipt_no']) ?></strong>
                    </div>
                    <div class="col-6 col-md-3">
                        <span class="text-muted d-block">Payment Date:</span>
                        <strong class="fs-6 text-dark"><?= formatDate($payment['payment_date']) ?></strong>
                    </div>
                    <div class="col-6 col-md-3">
                        <span class="text-muted d-block">Academic Session:</span>
                        <strong><?= e($payment['session_name'] ?? '2025-2026') ?></strong>
                    </div>
                    <div class="col-6 col-md-3">
                        <span class="text-muted d-block">Payment Method:</span>
                        <strong class="badge bg-light text-dark border"><?= e($payment['payment_method']) ?></strong>
                    </div>
                </div>

                <!-- Student Information Card -->
                <div class="bg-light p-3 rounded-3 border mb-4">
                    <div class="row g-2 small">
                        <div class="col-sm-6">
                            <span class="text-muted">Student Name:</span>
                            <strong class="text-dark fs-6 ms-1"><?= e($payment['first_name'] . ' ' . $payment['last_name']) ?></strong>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted">Admission No:</span>
                            <strong class="text-dark ms-1"><?= e($payment['admission_no']) ?></strong>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted">Father / Guardian:</span>
                            <strong class="text-dark ms-1"><?= e($payment['father_name'] ?: 'Not on record') ?></strong>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted">Class & Section:</span>
                            <strong class="text-dark ms-1"><?= e($payment['class_name'] . ' - ' . $payment['section_name']) ?> (Roll #<?= e($payment['roll_no'] ?: '-') ?>)</strong>
                        </div>
                    </div>
                </div>

                <!-- Fee Line Items Table -->
                <div class="table-responsive mb-4">
                    <table class="table table-bordered table-print mb-0">
                        <thead class="table-light">
                            <tr>
                                <th width="60">Sr #</th>
                                <th>Fee Particulars / Description</th>
                                <th>Billing Period</th>
                                <th class="text-end" width="160">Amount Paid</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($items)): ?>
                                <tr>
                                    <td>1</td>
                                    <td>Academic Tuition & Campus Fees</td>
                                    <td>Current Term</td>
                                    <td class="text-end fw-bold"><?= formatCurrency($payment['paid_amount']) ?></td>
                                </tr>
                            <?php else: ?>
                                <?php $sr = 1; foreach ($items as $it): ?>
                                    <tr>
                                        <td><?= $sr++ ?></td>
                                        <td class="fw-semibold"><?= e($it['fee_name']) ?></td>
                                        <td><?= e($it['month']) ?></td>
                                        <td class="text-end fw-bold"><?= formatCurrency($it['amount_applied']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="3" class="text-end">Base Subtotal:</th>
                                <td class="text-end fw-bold"><?= formatCurrency($payment['total_amount'] + $payment['discount_amount'] - $payment['fine_amount']) ?></td>
                            </tr>
                            <?php if ($payment['discount_amount'] > 0): ?>
                                <tr>
                                    <th colspan="3" class="text-end text-success">Discount / Scholarship Concession:</th>
                                    <td class="text-end text-success fw-bold">- <?= formatCurrency($payment['discount_amount']) ?></td>
                                </tr>
                            <?php endif; ?>
                            <?php if ($payment['fine_amount'] > 0): ?>
                                <tr>
                                    <th colspan="3" class="text-end text-danger">Late Payment Fine:</th>
                                    <td class="text-end text-danger fw-bold">+ <?= formatCurrency($payment['fine_amount']) ?></td>
                                </tr>
                            <?php endif; ?>
                            <tr class="table-active">
                                <th colspan="3" class="text-end fs-6">Net Total Paid (In Words):</th>
                                <td class="text-end fs-5 fw-bold text-success"><?= formatCurrency($payment['paid_amount']) ?></td>
                            </tr>
                            <?php if ($payment['balance_remaining'] > 0): ?>
                                <tr>
                                    <th colspan="3" class="text-end text-danger">Remaining Arrears / Balance:</th>
                                    <td class="text-end text-danger fw-bold"><?= formatCurrency($payment['balance_remaining']) ?></td>
                                </tr>
                            <?php endif; ?>
                        </tfoot>
                    </table>
                </div>

                <?php if (!empty($payment['transaction_ref']) || !empty($payment['remarks'])): ?>
                    <div class="small text-muted mb-4 border p-2 rounded">
                        <?php if (!empty($payment['transaction_ref'])): ?>
                            <div><strong>Reference / Cheque:</strong> <?= e($payment['transaction_ref']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($payment['remarks'])): ?>
                            <div><strong>Note:</strong> <?= e($payment['remarks']) ?></div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Signatures & Computer generated disclaimer -->
                <div class="receipt-signatures pt-4">
                    <div class="text-center">
                        <div class="sign-line mx-auto mb-1">Received By: <?= e($payment['receiver_name'] ?: 'Cashier') ?></div>
                        <div class="text-muted" style="font-size: 0.7rem;">Authorized Accounts Officer</div>
                    </div>
                    <div class="text-center">
                        <div class="sign-line mx-auto mb-1">Parent / Student Signature</div>
                        <div class="text-muted" style="font-size: 0.7rem;">Payer Acknowledgment</div>
                    </div>
                </div>

                <div class="text-center text-muted small mt-5 pt-3 border-top" style="font-size: 0.7rem;">
                    <?= e(getSetting('receipt_footer_note', 'This is a computer generated official fee receipt and does not require an embossed stamp.')) ?>
                </div>

                </div><!-- end inner content z-index wrapper -->
            </div>

        </div>
    </div>
</div>

<script>
function triggerPrint() {
    window.focus();
    try {
        window.print();
    } catch (e) {
        console.warn('Print error:', e);
        try {
            if (window.parent && window.parent !== window && window.parent.print) {
                window.parent.focus();
                window.parent.print();
            }
        } catch(ex) {}
    }
}

window.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
        e.preventDefault();
        triggerPrint();
    }
});

window.addEventListener('load', () => {
    const params = new URLSearchParams(window.location.search);
    if (params.get('autoprint') === '1' || params.get('print') === '1') {
        setTimeout(triggerPrint, 400);
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
