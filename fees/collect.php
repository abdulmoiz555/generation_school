<?php
/**
 * Fee Collection Screen (Section 18)
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

requirePermission('fees_collect');

$activeSession = getActiveSession();
$studentId = (int)($_GET['student_id'] ?? 0);
$selectedStudent = null;
$unpaidFees = [];
$previousBalance = 0.00;
$studentPayments = [];

if ($studentId > 0) {
    $selectedStudent = getStudent($studentId);
    if ($selectedStudent) {
        $previousBalance = getFeeBalance($studentId);
        $stmt = $pdo->prepare("
            SELECT sf.*, ft.name as fee_name 
            FROM student_fees sf 
            JOIN fee_types ft ON sf.fee_type_id = ft.id 
            WHERE sf.student_id = ? AND sf.status IN ('Pending', 'Partial', 'Overdue')
            ORDER BY sf.due_date ASC
        ");
        $stmt->execute([$studentId]);
        $unpaidFees = $stmt->fetchAll();

        // Fetch recent payments for instant slip printing
        $spStmt = $pdo->prepare("SELECT * FROM fee_payments WHERE student_id = ? ORDER BY id DESC LIMIT 5");
        $spStmt->execute([$studentId]);
        $studentPayments = $spStmt->fetchAll();
    }
}

// Available discounts & scholarships
$discounts = $pdo->query("SELECT * FROM fee_discounts WHERE status = 'active'")->fetchAll();
$scholarships = $pdo->query("SELECT * FROM scholarships WHERE status = 'active' ORDER BY category ASC")->fetchAll();

// Handle Collection Submit
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'collect_fee') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($csrfToken)) {
        $error = "Session validation failed. Please try again.";
    } else {
        $stId = (int)$_POST['student_id'];
        $paymentDate = $_POST['payment_date'] ?? date('Y-m-d');
        $baseAmount = (float)$_POST['fee_amount'];
        $discountAmount = (float)$_POST['fee_discount'];
        $fineAmount = (float)$_POST['fee_fine'];
        $totalPayable = (float)$_POST['fee_total'];
        $paidAmount = (float)$_POST['fee_paid'];
        $balanceRemaining = (float)$_POST['fee_balance'];
        $paymentMethod = $_POST['payment_method'] ?? 'Cash';
        $transactionRef = trim($_POST['transaction_ref'] ?? '');
        $remarks = trim($_POST['remarks'] ?? '');
        $selectedFeeInvoiceId = !empty($_POST['student_fee_id']) ? (int)$_POST['student_fee_id'] : null;
        $scholarshipId = !empty($_POST['scholarship_id']) ? (int)$_POST['scholarship_id'] : null;
        $concessionType = trim($_POST['concession_type'] ?? '');
        if ($scholarshipId && empty($concessionType)) {
            $scInfo = $pdo->query("SELECT title FROM scholarships WHERE id = {$scholarshipId}")->fetch();
            if ($scInfo) $concessionType = $scInfo['title'];
        }

        if ($paidAmount < 0) {
            $error = "Please enter a valid payment amount.";
        } else {
            try {
                $pdo->beginTransaction();
                $receiptNo = generateReceiptNumber();

                $pStmt = $pdo->prepare("
                    INSERT INTO fee_payments (
                        receipt_no, session_id, student_id, payment_date, 
                        total_amount, discount_amount, scholarship_id, concession_type, fine_amount, paid_amount, 
                        balance_remaining, payment_method, transaction_ref, remarks, received_by
                    ) VALUES (
                        ?, ?, ?, ?, 
                        ?, ?, ?, ?, ?, ?, 
                        ?, ?, ?, ?, ?
                    )
                ");
                $pStmt->execute([
                    $receiptNo, $activeSession['id'], $stId, $paymentDate,
                    $totalPayable, $discountAmount, $scholarshipId, $concessionType, $fineAmount, $paidAmount,
                    $balanceRemaining, $paymentMethod, $transactionRef, $remarks, getCurrentUserId()
                ]);
                $paymentId = (int)$pdo->lastInsertId();

                // If associated with a specific invoice or apply to oldest invoice
                if ($selectedFeeInvoiceId) {
                    $itemStmt = $pdo->prepare("INSERT INTO fee_payment_items (payment_id, student_fee_id, amount_applied) VALUES (?, ?, ?)");
                    $itemStmt->execute([$paymentId, $selectedFeeInvoiceId, $paidAmount]);

                    // Update invoice status
                    $newBalance = max(0, $totalPayable - $paidAmount);
                    $newStatus = ($newBalance <= 0) ? 'Paid' : 'Partial';
                    $updStmt = $pdo->prepare("UPDATE student_fees SET paid_amount = paid_amount + ?, balance = ?, status = ? WHERE id = ?");
                    $updStmt->execute([$paidAmount, $newBalance, $newStatus, $selectedFeeInvoiceId]);
                }

                $pdo->commit();
                logAudit('COLLECT_FEE', 'Fees', $paymentId, "Collected {$paidAmount} with receipt {$receiptNo} for student {$stId}");
                setFlashMessage('success', "Payment successfully recorded! Receipt #{$receiptNo} generated.");
                
                $printFormat = $_POST['print_format'] ?? 'half_a4';
                if ($printFormat === 'half_a4') {
                    header("Location: " . BASE_PATH . "/fees/receipt-half-a4.php?id=" . $paymentId . "&autoprint=1");
                } elseif ($printFormat === 'pos') {
                    header("Location: " . BASE_PATH . "/fees/receipt-pos.php?id=" . $paymentId . "&autoprint=1");
                } else {
                    header("Location: " . BASE_PATH . "/fees/receipt.php?id=" . $paymentId . "&autoprint=1");
                }
                exit;
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log("Fee collection error: " . $e->getMessage());
                $error = "Failed to record payment transaction.";
            }
        }
    }
}

$pageTitle = "Collect Student Fee";
$extraScripts = ['fees.js'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3 no-print">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item">Fees</li>
                <li class="breadcrumb-item active">Collect Fee</li>
            </ol>
        </nav>
        <h3 class="fw-bold text-dark mb-0">Fee Collection Counter</h3>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="<?= BASE_PATH ?>/fees/payments.php" class="btn btn-warning btn-sm rounded-pill px-3 fw-bold shadow-sm">
            <i class="fas fa-print me-1"></i> Print Fee Slips (Half A4 & POS)
        </a>
        <a href="<?= BASE_PATH ?>/fees/payments.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
            <i class="fas fa-history me-1"></i> Payment Records
        </a>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show rounded-3 mb-4" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i> <?= e($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Step 1: Quick Search Student -->
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-header bg-transparent py-3">
        <h6 class="fw-bold text-primary mb-0"><i class="fas fa-search me-2"></i> Step 1: Search & Select Student</h6>
    </div>
    <div class="card-body p-4">
        <div class="row g-3">
            <div class="col-md-9">
                <div class="position-relative">
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="fas fa-user-graduate text-muted"></i></span>
                        <input type="text" id="studentQuickSearch" class="form-control" placeholder="Search by Student Name, Admission Number (e.g. ADM-2025-001) or Roll No..." autocomplete="off">
                    </div>
                    <div id="searchResultsDropdown" class="list-group position-absolute w-100 shadow-lg rounded-3 mt-1" style="display: none; z-index: 1050; max-height: 250px; overflow-y: auto;"></div>
                </div>
            </div>
            <div class="col-md-3">
                <a href="<?= BASE_PATH ?>/fees/collect.php" class="btn btn-outline-secondary w-100">Clear Selection</a>
            </div>
        </div>
    </div>
</div>

<?php if ($selectedStudent): ?>
<!-- Step 2: Collection Form -->
<form id="feeCollectionForm" action="<?= BASE_PATH ?>/fees/collect.php" method="POST">
    <input type="hidden" name="action" value="collect_fee">
    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
    <input type="hidden" name="student_id" value="<?= $selectedStudent['id'] ?>">

    <div class="row g-4 mb-5">
        <!-- Student Identity Summary Card -->
        <div class="col-12 col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 p-4 text-center">
                <div class="avatar-initials mx-auto mb-3" style="width: 72px; height: 72px; font-size: 1.8rem;">
                    <?= strtoupper(substr($selectedStudent['first_name'], 0, 1)) ?>
                </div>
                <h5 class="fw-bold text-dark mb-1"><?= e($selectedStudent['first_name'] . ' ' . $selectedStudent['last_name']) ?></h5>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1 rounded-pill mb-3">
                    <?= e($selectedStudent['admission_no']) ?>
                </span>

                <div class="text-start bg-light p-3 rounded-3 small border mb-3">
                    <div class="d-flex justify-content-between py-1">
                        <span class="text-muted">Student Type:</span>
                        <span class="badge bg-secondary-subtle text-dark border"><?= e($selectedStudent['student_type'] ?? 'Coeducation') ?></span>
                    </div>
                    <?php if (!empty($selectedStudent['scholarship_title'])): ?>
                    <div class="d-flex justify-content-between py-1">
                        <span class="text-muted">Scholarship:</span>
                        <span class="badge bg-success-subtle text-success border border-success-subtle">
                            <?= e($selectedStudent['scholarship_category']) ?> (<?= number_format($selectedStudent['scholarship_pct'], 0) ?>%)
                        </span>
                    </div>
                    <?php endif; ?>
                    <div class="d-flex justify-content-between py-1">
                        <span class="text-muted">Class & Section:</span>
                        <strong><?= e($selectedStudent['class_name'] . ' - ' . $selectedStudent['section_name']) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between py-1">
                        <span class="text-muted">Roll Number:</span>
                        <strong><?= e($selectedStudent['roll_no'] ?: '-') ?></strong>
                    </div>
                    <div class="d-flex justify-content-between py-1">
                        <span class="text-muted">Father / Guardian:</span>
                        <strong><?= e($selectedStudent['father_name'] ?: 'N/A') ?></strong>
                    </div>
                    <div class="d-flex justify-content-between py-1">
                        <span class="text-muted">Contact Phone:</span>
                        <span><?= e($selectedStudent['parent_phone'] ?: $selectedStudent['phone']) ?></span>
                    </div>
                </div>

                <div class="alert <?= $previousBalance > 0 ? 'alert-danger' : 'alert-success' ?> mb-0 py-2">
                    <div class="small fw-semibold"><?= $previousBalance > 0 ? 'Total Unpaid Arrears' : 'No Overdue Dues' ?></div>
                    <h4 class="fw-bold mb-0"><?= formatCurrency($previousBalance) ?></h4>
                </div>

                <?php if (!empty($studentPayments)): ?>
                <div class="mt-3 pt-3 border-top text-start">
                    <div class="fw-bold small text-dark mb-2"><i class="fas fa-print me-1 text-primary"></i> Previous Slips for this Student:</div>
                    <div class="list-group list-group-flush small">
                        <?php foreach ($studentPayments as $sp): ?>
                            <div class="list-group-item px-0 py-2 border-bottom">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="fw-bold text-primary"><?= e($sp['receipt_no']) ?></span>
                                    <span class="fw-bold text-success"><?= formatCurrency($sp['paid_amount']) ?></span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-muted" style="font-size: 0.72rem;"><?= formatDate($sp['payment_date']) ?></span>
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= BASE_PATH ?>/fees/receipt-half-a4.php?id=<?= $sp['id'] ?>" class="btn btn-xs btn-success px-2 py-0" title="Print Half A4 Dual Slip">
                                            <i class="fas fa-copy me-1"></i> Half A4
                                        </a>
                                        <a href="<?= BASE_PATH ?>/fees/receipt-pos.php?id=<?= $sp['id'] ?>" class="btn btn-xs btn-dark px-2 py-0" title="Print POS 80mm Slip">
                                            <i class="fas fa-receipt me-1"></i> POS
                                        </a>
                                        <a href="<?= BASE_PATH ?>/fees/receipt.php?id=<?= $sp['id'] ?>" class="btn btn-xs btn-outline-primary px-2 py-0" title="Full A4">
                                            <i class="fas fa-print"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Payment Calculations and Form -->
        <div class="col-12 col-lg-8">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-transparent py-3">
                    <h6 class="fw-bold text-primary mb-0"><i class="fas fa-calculator me-2"></i> Payment Details & Receipt Generation</h6>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Select Pending Invoice (Optional)</label>
                            <select name="student_fee_id" id="invoiceSelect" class="form-select">
                                <option value="">-- Manual Payment Entry --</option>
                                <?php foreach ($unpaidFees as $uf): ?>
                                    <option value="<?= $uf['id'] ?>" data-amount="<?= $uf['balance'] ?>">
                                        <?= e($uf['fee_name']) ?> (<?= e($uf['month']) ?>) - Balance: <?= formatCurrency($uf['balance']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Payment Date <span class="text-danger">*</span></label>
                            <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>

                        <!-- Scholarship & Concession Selector -->
                        <div class="col-md-7">
                            <label class="form-label small fw-semibold text-success">
                                <i class="fas fa-award me-1"></i> Apply Scholarship / Concession (Need-Based, Orphan, Siblings, Merit)
                            </label>
                            <select name="scholarship_id" id="scholarshipSelect" class="form-select border-success">
                                <option value="" data-pct="0" data-title="Standard">-- None / Standard Full Fee (0% Concession) --</option>
                                <?php foreach ($scholarships as $sc): ?>
                                    <option value="<?= $sc['id'] ?>" data-pct="<?= $sc['discount_percentage'] ?>" data-title="<?= e($sc['title']) ?>" <?= (isset($selectedStudent['scholarship_id']) && $selectedStudent['scholarship_id'] == $sc['id']) ? 'selected' : '' ?>>
                                        <?= e($sc['category']) ?>: <?= e($sc['title']) ?> (<?= number_format($sc['discount_percentage'], 0) ?>% Waiver)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div id="concessionBadge" class="badge bg-success-subtle text-success border border-success-subtle mt-1 px-2 py-1" style="display: none;"></div>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small fw-semibold">Concession / Discount Reason</label>
                            <input type="text" name="concession_type" id="concessionType" class="form-control" placeholder="e.g. Orphan Waiver / Siblings Concession" value="<?= !empty($selectedStudent['scholarship_title']) ? e($selectedStudent['scholarship_title']) : '' ?>">
                        </div>

                        <!-- Amounts Grid in PKR -->
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Base Fee Amount (PKR) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" name="fee_amount" id="feeAmount" class="form-control fw-bold" value="<?= !empty($unpaidFees) ? $unpaidFees[0]['balance'] : 2500.00 ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Discount Concession (PKR)</label>
                            <input type="number" step="0.01" name="fee_discount" id="feeDiscount" class="form-control" value="0.00">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Late Fine (PKR)</label>
                            <input type="number" step="0.01" name="fee_fine" id="feeFine" class="form-control" value="0.00">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-primary">Net Total Payable (PKR)</label>
                            <input type="number" step="0.01" name="fee_total" id="feeTotal" class="form-control bg-light fw-bold text-primary fs-5" value="<?= !empty($unpaidFees) ? $unpaidFees[0]['balance'] : 2500.00 ?>" readonly>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label small fw-semibold text-success mb-0">Amount Paying (PKR) <span class="text-danger">*</span></label>
                                <a href="#" id="payFullBtn" class="small text-decoration-none">Pay Full</a>
                            </div>
                            <input type="number" step="0.01" name="fee_paid" id="feePaid" class="form-control border-success fw-bold text-success fs-5" value="<?= !empty($unpaidFees) ? $unpaidFees[0]['balance'] : 2500.00 ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-danger">Remaining Balance (PKR)</label>
                            <input type="number" step="0.01" name="fee_balance" id="feeBalance" class="form-control bg-light fw-bold text-danger fs-5" value="0.00" readonly>
                        </div>

                        <!-- Payment Method and Ref -->
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Payment Method <span class="text-danger">*</span></label>
                            <select name="payment_method" class="form-select" required>
                                <option value="Cash">Cash (Counter Collection)</option>
                                <option value="Bank">Bank Deposit / Transfer</option>
                                <option value="Online">Online Gateway / Card</option>
                                <option value="Cheque">Cheque</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Transaction / Cheque Reference #</label>
                            <input type="text" name="transaction_ref" class="form-control" placeholder="e.g. TXN-89218 or Check #4401">
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-semibold">Official Remarks / Note</label>
                            <textarea name="remarks" class="form-control" rows="2" placeholder="Fee collection details or concession reason..."></textarea>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap justify-content-end align-items-center gap-2 mt-4 pt-3 border-top">
                        <span class="text-muted small me-2"><i class="fas fa-print me-1"></i> Choose Print Format upon Collection:</span>
                        <button type="submit" name="print_format" value="half_a4" class="btn btn-success px-4 py-2 rounded-pill fw-bold shadow-sm">
                            <i class="fas fa-copy me-2"></i> Receive & Print Half A4 Dual Slip
                        </button>
                        <button type="submit" name="print_format" value="pos" class="btn btn-dark px-4 py-2 rounded-pill fw-bold shadow-sm">
                            <i class="fas fa-receipt me-2"></i> Receive & Print POS Thermal Slip
                        </button>
                        <button type="submit" name="print_format" value="a4" class="btn btn-primary px-4 py-2 rounded-pill fw-bold shadow-sm">
                            <i class="fas fa-file-invoice me-2"></i> Receive & Print Full A4
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>
<?php endif; ?>

<script>
// Search API integration
const searchInput = document.getElementById('studentQuickSearch');
const searchDropdown = document.getElementById('searchResultsDropdown');

if (searchInput) {
    let timer;
    searchInput.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(() => {
            const q = searchInput.value.trim();
            if (q.length < 1) {
                searchDropdown.style.display = 'none';
                return;
            }

            fetch(`<?= BASE_PATH ?>/api/student-search.php?q=${encodeURIComponent(q)}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.students.length > 0) {
                        let html = '';
                        data.students.forEach(st => {
                            html += `
                                <a href="<?= BASE_PATH ?>/fees/collect.php?student_id=${st.id}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2">
                                    <div>
                                        <strong>${st.first_name} ${st.last_name}</strong>
                                        <div class="text-muted small">${st.class_name || ''} - ${st.section_name || ''} &bull; Adm: <code>${st.admission_no}</code></div>
                                    </div>
                                    <span class="badge ${st.fee_balance > 0 ? 'bg-danger' : 'bg-success'} rounded-pill">
                                        PKR ${parseFloat(st.fee_balance).toFixed(2)} Due
                                    </span>
                                </a>
                            `;
                        });
                        searchDropdown.innerHTML = html;
                        searchDropdown.style.display = 'block';
                    } else {
                        searchDropdown.innerHTML = '<div class="list-group-item text-muted text-center py-3">No matching students found</div>';
                        searchDropdown.style.display = 'block';
                    }
                });
        }, 250);
    });

    document.addEventListener('click', (e) => {
        if (!searchInput.contains(e.target) && !searchDropdown.contains(e.target)) {
            searchDropdown.style.display = 'none';
        }
    });
}

// Auto populate invoice amount
const invoiceSelect = document.getElementById('invoiceSelect');
if (invoiceSelect) {
    invoiceSelect.addEventListener('change', () => {
        const selectedOpt = invoiceSelect.options[invoiceSelect.selectedIndex];
        const amt = selectedOpt.getAttribute('data-amount');
        if (amt) {
            document.getElementById('feeAmount').value = amt;
            document.getElementById('feePaid').value = amt;
            // trigger calculation
            document.getElementById('feeAmount').dispatchEvent(new Event('input'));
        }
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
