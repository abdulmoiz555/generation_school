<?php
/**
 * Detailed Student Profile
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

requirePermission('student_view');

$studentId = (int)($_GET['id'] ?? 0);
$student = getStudent($studentId);

if (!$student) {
    setFlashMessage('error', 'Student record not found.');
    header("Location: " . BASE_PATH . "/students/index.php");
    exit;
}

// Attendance stats
$attStmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_days,
        SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_days,
        SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) as late_days,
        SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent_days,
        SUM(CASE WHEN status = 'Leave' THEN 1 ELSE 0 END) as leave_days
    FROM student_attendance 
    WHERE student_id = ?
");
$attStmt->execute([$studentId]);
$attStats = $attStmt->fetch();

$totalDays = (int)($attStats['total_days'] ?? 0);
$presentDays = (int)($attStats['present_days'] ?? 0);
$attPercent = ($totalDays > 0) ? round(($presentDays / $totalDays) * 100, 1) : 100;

// Fee Invoices
$feeStmt = $pdo->prepare("
    SELECT sf.*, ft.name as fee_name 
    FROM student_fees sf 
    JOIN fee_types ft ON sf.fee_type_id = ft.id 
    WHERE sf.student_id = ? 
    ORDER BY sf.due_date DESC
");
$feeStmt->execute([$studentId]);
$fees = $feeStmt->fetchAll();

// Payment receipts
$payStmt = $pdo->prepare("SELECT * FROM fee_payments WHERE student_id = ? ORDER BY payment_date DESC");
$payStmt->execute([$studentId]);
$payments = $payStmt->fetchAll();

// Exam results
$marksStmt = $pdo->prepare("
    SELECT m.*, sub.subject_name, sub.subject_code, ex.title as exam_title
    FROM marks m
    JOIN exam_subjects es ON m.exam_subject_id = es.id
    JOIN subjects sub ON es.subject_id = sub.id
    JOIN exams ex ON m.exam_id = ex.id
    WHERE m.student_id = ?
    ORDER BY ex.start_date DESC, sub.subject_name ASC
");
$marksStmt->execute([$studentId]);
$marks = $marksStmt->fetchAll();

// Student Documents
$docStmt = $pdo->prepare("SELECT * FROM student_documents WHERE student_id = ? ORDER BY created_at DESC");
$docStmt->execute([$studentId]);
$documents = $docStmt->fetchAll();

$pageTitle = $student['first_name'] . ' ' . $student['last_name'] . " - Profile";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/students/index.php">Students</a></li>
                <li class="breadcrumb-item active"><?= e($student['first_name'] . ' ' . $student['last_name']) ?></li>
            </ol>
        </nav>
        <h3 class="fw-bold text-dark mb-0">Student Profile</h3>
    </div>
    
    <div class="d-flex flex-wrap gap-2">
        <button onclick="window.print()" class="btn btn-outline-secondary btn-sm rounded-pill px-3 shadow-sm">
            <i class="fas fa-print me-1"></i> Print Profile
        </button>
        <a href="<?= BASE_PATH ?>/students/profile.php?id=<?= $student['id'] ?>&print=idcard" class="btn btn-outline-info btn-sm rounded-pill px-3 shadow-sm">
            <i class="fas fa-id-card me-1"></i> Student ID Card
        </a>
        <?php if (hasPermission('fees_collect')): ?>
            <a href="<?= BASE_PATH ?>/fees/collect.php?student_id=<?= $student['id'] ?>" class="btn btn-success btn-sm rounded-pill px-3 shadow-sm">
                <i class="fas fa-cash-register me-1"></i> Collect Fee
            </a>
        <?php endif; ?>
        <?php if (hasPermission('student_edit')): ?>
            <a href="<?= BASE_PATH ?>/students/edit.php?id=<?= $student['id'] ?>" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm">
                <i class="fas fa-edit me-1"></i> Edit Student
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Printable Area / Profile Banner -->
<div class="printable-area">
    <div class="row g-4 mb-4">
        <!-- Left Profile Identity Card -->
        <div class="col-12 col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 text-center p-4">
                <div class="position-relative d-inline-block mx-auto mb-3">
                    <div class="avatar-initials" style="width: 100px; height: 100px; font-size: 2.5rem;">
                        <?= strtoupper(substr($student['first_name'], 0, 1)) ?>
                    </div>
                </div>

                <h4 class="fw-bold text-dark mb-1"><?= e($student['first_name'] . ' ' . $student['last_name']) ?></h4>
                <div class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1 rounded-pill mb-3">
                    Admission #: <strong><?= e($student['admission_no']) ?></strong>
                </div>

                <div class="d-flex justify-content-center gap-2 mb-4">
                    <span class="badge bg-light text-dark border">Roll: <?= e($student['roll_no'] ?: 'N/A') ?></span>
                    <span class="badge bg-light text-dark border"><?= e($student['gender']) ?></span>
                    <span class="badge bg-light text-dark border">Blood: <?= e($student['blood_group'] ?: 'Unknown') ?></span>
                </div>

                <div class="border-top pt-3 text-start small">
                    <div class="d-flex justify-content-between py-1">
                        <span class="text-muted">Class & Section:</span>
                        <strong class="text-dark"><?= e($student['class_name'] . ' - ' . $student['section_name']) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between py-1">
                        <span class="text-muted">Academic Session:</span>
                        <strong class="text-dark"><?= e($student['session_name'] ?? '2025-2026') ?></strong>
                    </div>
                    <div class="d-flex justify-content-between py-1">
                        <span class="text-muted">Admission Date:</span>
                        <strong class="text-dark"><?= formatDate($student['admission_date']) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between py-1">
                        <span class="text-muted">Status:</span>
                        <span class="badge badge-soft-success"><?= ucfirst($student['status']) ?></span>
                    </div>
                </div>
            </div>

            <!-- Attendance Mini Card -->
            <div class="card border-0 shadow-sm rounded-4 mt-3 p-3">
                <h6 class="fw-bold text-dark mb-3"><i class="fas fa-calendar-check text-success me-2"></i> Attendance Overview</h6>
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="small text-muted">Attendance Rate</span>
                    <span class="fw-bold text-success fs-5"><?= $attPercent ?>%</span>
                </div>
                <div class="progress" style="height: 8px;">
                    <div class="progress-bar bg-success" role="progressbar" style="width: <?= $attPercent ?>%;"></div>
                </div>
                <div class="row text-center mt-3 pt-2 border-top g-1">
                    <div class="col-3">
                        <div class="fw-bold text-dark"><?= $totalDays ?></div>
                        <div class="text-muted" style="font-size: 0.7rem;">Total</div>
                    </div>
                    <div class="col-3">
                        <div class="fw-bold text-success"><?= $presentDays ?></div>
                        <div class="text-muted" style="font-size: 0.7rem;">Present</div>
                    </div>
                    <div class="col-3">
                        <div class="fw-bold text-warning"><?= (int)($attStats['late_days'] ?? 0) ?></div>
                        <div class="text-muted" style="font-size: 0.7rem;">Late</div>
                    </div>
                    <div class="col-3">
                        <div class="fw-bold text-danger"><?= (int)($attStats['absent_days'] ?? 0) ?></div>
                        <div class="text-muted" style="font-size: 0.7rem;">Absent</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Tabs Content Area -->
        <div class="col-12 col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-transparent p-0 border-bottom">
                    <ul class="nav nav-tabs border-0 px-3 pt-2" id="studentTab" role="tablist">
                        <li class="nav-item">
                            <button class="nav-link active fw-semibold" id="personal-tab" data-bs-toggle="tab" data-bs-target="#personal" type="button"><i class="fas fa-id-badge me-1"></i> Personal</button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link fw-semibold" id="parent-tab" data-bs-toggle="tab" data-bs-target="#parent" type="button"><i class="fas fa-user-friends me-1"></i> Family</button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link fw-semibold" id="fees-tab" data-bs-toggle="tab" data-bs-target="#fees" type="button"><i class="fas fa-receipt me-1"></i> Fees (<?= count($fees) ?>)</button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link fw-semibold" id="results-tab" data-bs-toggle="tab" data-bs-target="#results" type="button"><i class="fas fa-award me-1"></i> Results (<?= count($marks) ?>)</button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link fw-semibold" id="docs-tab" data-bs-toggle="tab" data-bs-target="#docs" type="button"><i class="fas fa-file-alt me-1"></i> Documents (<?= count($documents) ?>)</button>
                        </li>
                    </ul>
                </div>

                <div class="card-body p-4">
                    <div class="tab-content" id="studentTabContent">
                        
                        <!-- 1. Personal Info Tab -->
                        <div class="tab-pane fade show active" id="personal" role="tabpanel">
                            <h6 class="fw-bold text-primary mb-3">Bio-data & Identification</h6>
                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <span class="text-muted small d-block">Full Legal Name:</span>
                                    <strong class="text-dark"><?= e($student['first_name'] . ' ' . $student['last_name']) ?></strong>
                                </div>
                                <div class="col-sm-6">
                                    <span class="text-muted small d-block">Date of Birth:</span>
                                    <strong class="text-dark"><?= formatDate($student['dob']) ?> (Age: <?= date_diff(date_create($student['dob']), date_create('today'))->y ?> yrs)</strong>
                                </div>
                                <div class="col-sm-6">
                                    <span class="text-muted small d-block">B-Form / CNIC:</span>
                                    <strong class="text-dark"><?= e($student['cnic_bform'] ?: 'Not Provided') ?></strong>
                                </div>
                                <div class="col-sm-6">
                                    <span class="text-muted small d-block">Gender:</span>
                                    <strong class="text-dark"><?= e($student['gender']) ?></strong>
                                </div>
                                <div class="col-sm-6">
                                    <span class="text-muted small d-block">Primary Contact Phone:</span>
                                    <strong class="text-dark"><?= e($student['phone'] ?: 'N/A') ?></strong>
                                </div>
                                <div class="col-sm-6">
                                    <span class="text-muted small d-block">Email Address:</span>
                                    <strong class="text-dark"><?= e($student['email'] ?: 'N/A') ?></strong>
                                </div>
                                <div class="col-sm-12">
                                    <span class="text-muted small d-block">Residential Address:</span>
                                    <strong class="text-dark"><?= e($student['address'] ?: 'Not Provided') ?>, <?= e($student['city'] ?: '') ?> <?= e($student['province'] ?: '') ?></strong>
                                </div>
                                <div class="col-sm-12">
                                    <span class="text-muted small d-block">Previous School Record:</span>
                                    <strong class="text-dark"><?= e($student['previous_school'] ?: 'None (Enrolled as fresh applicant)') ?></strong>
                                </div>
                            </div>
                        </div>

                        <!-- 2. Family Info Tab -->
                        <div class="tab-pane fade" id="parent" role="tabpanel">
                            <h6 class="fw-bold text-primary mb-3">Parent & Guardian Information</h6>
                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <span class="text-muted small d-block">Father's Name:</span>
                                    <strong class="text-dark"><?= e($student['father_name'] ?: 'Not Provided') ?></strong>
                                </div>
                                <div class="col-sm-6">
                                    <span class="text-muted small d-block">Mother's Name:</span>
                                    <strong class="text-dark"><?= e($student['mother_name'] ?: 'Not Provided') ?></strong>
                                </div>
                                <div class="col-sm-6">
                                    <span class="text-muted small d-block">Guardian Name:</span>
                                    <strong class="text-dark"><?= e($student['guardian_name'] ?: ($student['father_name'] ?: 'N/A')) ?></strong>
                                </div>
                                <div class="col-sm-6">
                                    <span class="text-muted small d-block">Emergency Contact:</span>
                                    <strong class="text-dark"><?= e($student['parent_phone'] ?: 'N/A') ?></strong>
                                </div>
                                <div class="col-sm-6">
                                    <span class="text-muted small d-block">Parent Email:</span>
                                    <strong class="text-dark"><?= e($student['parent_email'] ?: 'N/A') ?></strong>
                                </div>
                            </div>
                        </div>

                        <!-- 3. Fee Invoices & Payments Tab -->
                        <div class="tab-pane fade" id="fees" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="fw-bold text-primary mb-0">Fee Invoices</h6>
                                <span class="badge bg-warning-subtle text-warning border border-warning-subtle">
                                    Outstanding Balance: <?= formatCurrency(getFeeBalance($student['id'])) ?>
                                </span>
                            </div>
                            <div class="table-responsive mb-4">
                                <table class="table table-sm table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Fee Description</th>
                                            <th>Month</th>
                                            <th>Amount</th>
                                            <th>Paid</th>
                                            <th>Balance</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($fees)): ?>
                                            <tr><td colspan="6" class="text-center text-muted py-3">No fee records found.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($fees as $f): ?>
                                                <tr>
                                                    <td class="fw-semibold"><?= e($f['fee_name']) ?></td>
                                                    <td><?= e($f['month']) ?></td>
                                                    <td><?= formatCurrency($f['amount']) ?></td>
                                                    <td class="text-success"><?= formatCurrency($f['paid_amount']) ?></td>
                                                    <td class="text-danger fw-bold"><?= formatCurrency($f['balance']) ?></td>
                                                    <td>
                                                        <span class="badge <?= $f['status'] === 'Paid' ? 'badge-soft-success' : 'badge-soft-danger' ?>">
                                                            <?= e($f['status']) ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <h6 class="fw-bold text-primary mb-2">Payment Receipts</h6>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Receipt #</th>
                                            <th>Date</th>
                                            <th>Amount Paid</th>
                                            <th>Method</th>
                                            <th class="text-end">Print Receipt</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($payments as $p): ?>
                                            <tr>
                                                <td class="fw-bold text-primary"><?= e($p['receipt_no']) ?></td>
                                                <td><?= formatDate($p['payment_date']) ?></td>
                                                <td class="fw-bold text-success"><?= formatCurrency($p['paid_amount']) ?></td>
                                                <td><span class="badge bg-light text-dark border"><?= e($p['payment_method']) ?></span></td>
                                                <td class="text-end text-nowrap">
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="<?= BASE_PATH ?>/fees/receipt.php?id=<?= $p['id'] ?>" class="btn btn-xs btn-outline-primary" title="Full A4">
                                                            <i class="fas fa-print me-1"></i> A4
                                                        </a>
                                                        <a href="<?= BASE_PATH ?>/fees/receipt-half-a4.php?id=<?= $p['id'] ?>" class="btn btn-xs btn-outline-primary" title="Half A4 Dual">
                                                            <i class="fas fa-copy me-1"></i> Half
                                                        </a>
                                                        <a href="<?= BASE_PATH ?>/fees/receipt-pos.php?id=<?= $p['id'] ?>" class="btn btn-xs btn-outline-dark" title="POS Thermal">
                                                            <i class="fas fa-receipt me-1"></i> POS
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- 4. Examination Results Tab -->
                        <div class="tab-pane fade" id="results" role="tabpanel">
                            <h6 class="fw-bold text-primary mb-3">Academic Performance & Grades</h6>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Exam Title</th>
                                            <th>Subject</th>
                                            <th>Obtained</th>
                                            <th>Max</th>
                                            <th>Percentage</th>
                                            <th>Grade</th>
                                            <th>Remarks</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($marks)): ?>
                                            <tr><td colspan="7" class="text-center text-muted py-4">No examination results available yet.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($marks as $m): ?>
                                                <tr>
                                                    <td class="fw-semibold"><?= e($m['exam_title']) ?></td>
                                                    <td><?= e($m['subject_name']) ?> <small class="text-muted">(<?= e($m['subject_code']) ?>)</small></td>
                                                    <td class="fw-bold text-primary"><?= $m['marks_obtained'] ?></td>
                                                    <td><?= $m['max_marks'] ?></td>
                                                    <td><?= $m['percentage'] ?>%</td>
                                                    <td>
                                                        <span class="badge <?= in_array($m['grade'], ['A+', 'A']) ? 'bg-success' : 'bg-primary' ?>">
                                                            <?= e($m['grade']) ?>
                                                        </span>
                                                    </td>
                                                    <td class="small text-muted"><?= e($m['remarks'] ?: 'Satisfactory') ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- 5. Documents Tab -->
                        <div class="tab-pane fade" id="docs" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="fw-bold text-primary mb-0">Student Verified Documents</h6>
                                <a href="<?= BASE_PATH ?>/students/documents.php?student_id=<?= $student['id'] ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-upload me-1"></i> Upload Document
                                </a>
                            </div>
                            <div class="list-group">
                                <?php if (empty($documents)): ?>
                                    <div class="text-center py-4 text-muted">No documents uploaded for this student yet.</div>
                                <?php else: ?>
                                    <?php foreach ($documents as $d): ?>
                                        <div class="list-group-item d-flex justify-content-between align-items-center">
                                            <div>
                                                <i class="fas fa-file-pdf text-danger me-2 fs-5"></i>
                                                <strong><?= e($d['title']) ?></strong>
                                                <span class="text-muted small ms-2">(Uploaded: <?= formatDate($d['created_at']) ?>)</span>
                                            </div>
                                            <a href="<?= BASE_PATH ?>/<?= e($d['file_path']) ?>" class="btn btn-sm btn-light border" download>
                                                <i class="fas fa-download"></i>
                                            </a>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
