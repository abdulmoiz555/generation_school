<?php
/**
 * Student ID Card Generator & Printable Card (Section 48)
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

requirePermission('student_view');

$studentId = (int)($_GET['id'] ?? 0);
$student = getStudent($studentId);

if (!$student) {
    setFlashMessage('error', 'Student not found.');
    header("Location: " . BASE_PATH . "/students/index.php");
    exit;
}

$pageTitle = "Student ID Card - " . $student['first_name'] . ' ' . $student['last_name'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 no-print">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/students/index.php">Students</a></li>
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/students/view.php?id=<?= $student['id'] ?>"><?= e($student['first_name']) ?></a></li>
                <li class="breadcrumb-item active">ID Card</li>
            </ol>
        </nav>
        <h3 class="fw-bold text-dark mb-0">Official Student Identity Card</h3>
    </div>
    <div>
        <button onclick="window.print()" class="btn btn-primary rounded-pill px-4 shadow-sm">
            <i class="fas fa-print me-2"></i> Print ID Card
        </button>
        <a href="<?= BASE_PATH ?>/students/view.php?id=<?= $student['id'] ?>" class="btn btn-outline-secondary rounded-pill px-3 ms-2">
            Back
        </a>
    </div>
</div>

<div class="printable-area py-4 d-flex flex-column flex-md-row gap-4 justify-content-center align-items-center">
    
    <!-- FRONT OF ID CARD -->
    <div class="id-card shadow-sm border rounded-4 position-relative overflow-hidden bg-white" style="width: 320px; height: 480px; border: 2px solid #0f172a !important;">
        <!-- Top Header Strip -->
        <div class="bg-primary text-white text-center py-3 px-2">
            <div class="d-flex align-items-center justify-content-center gap-2 mb-1">
                <i class="fas fa-graduation-cap fs-4"></i>
                <h6 class="fw-bold mb-0 text-uppercase tracking-wider" style="font-size: 0.85rem;"><?= e(getSetting('school_name', 'EduManage Academy')) ?></h6>
            </div>
            <div class="text-white-50" style="font-size: 0.65rem;"><?= e(getSetting('school_motto', 'Excellence in Education')) ?></div>
        </div>

        <!-- Student Photo & Identification -->
        <div class="p-3 text-center">
            <div class="avatar-initials mx-auto my-2 border border-3 border-primary shadow-sm" style="width: 100px; height: 100px; font-size: 2.5rem;">
                <?= strtoupper(substr($student['first_name'], 0, 1)) ?>
            </div>

            <h5 class="fw-bold text-dark mb-1 mt-2"><?= e($student['first_name'] . ' ' . $student['last_name']) ?></h5>
            <div class="badge bg-primary px-3 py-1 rounded-pill mb-3" style="font-size: 0.75rem;">
                STUDENT
            </div>

            <div class="text-start bg-light p-2 rounded-3 small border mb-3" style="font-size: 0.78rem;">
                <div class="row g-1">
                    <div class="col-5 text-muted">Admission No:</div>
                    <div class="col-7 fw-bold text-dark"><?= e($student['admission_no']) ?></div>

                    <div class="col-5 text-muted">Class & Sec:</div>
                    <div class="col-7 fw-bold text-primary"><?= e($student['class_name'] . ' - ' . $student['section_name']) ?></div>

                    <div class="col-5 text-muted">Roll Number:</div>
                    <div class="col-7 fw-bold text-dark"><?= e($student['roll_no'] ?: 'N/A') ?></div>

                    <div class="col-5 text-muted">Date of Birth:</div>
                    <div class="col-7 fw-bold text-dark"><?= formatDate($student['dob']) ?></div>

                    <div class="col-5 text-muted">Blood Group:</div>
                    <div class="col-7 fw-bold text-danger"><?= e($student['blood_group'] ?: 'Unknown') ?></div>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-end mt-4 pt-2 border-top px-2">
                <div class="text-center">
                    <div class="text-muted" style="font-size: 0.65rem;">Session: <?= e($student['session_name'] ?? '2025-2026') ?></div>
                </div>
                <div class="text-center">
                    <div class="text-muted" style="font-size: 0.65rem; border-top: 1px solid #333; width: 80px; padding-top: 2px;">Principal Signature</div>
                </div>
            </div>
        </div>
    </div>

    <!-- BACK OF ID CARD -->
    <div class="id-card shadow-sm border rounded-4 position-relative overflow-hidden bg-white" style="width: 320px; height: 480px; border: 2px solid #0f172a !important;">
        <div class="bg-dark text-white text-center py-2 px-2">
            <h6 class="fw-bold mb-0 text-uppercase" style="font-size: 0.8rem;">Emergency & School Details</h6>
        </div>

        <div class="p-3 d-flex flex-column justify-content-between h-100" style="padding-bottom: 2.5rem !important;">
            <div>
                <h6 class="fw-bold text-primary mb-2 small text-uppercase"><i class="fas fa-user-shield me-1"></i> Guardian Contact</h6>
                <div class="small bg-light p-2 rounded border mb-3" style="font-size: 0.78rem;">
                    <div><strong>Father:</strong> <?= e($student['father_name'] ?: 'N/A') ?></div>
                    <div><strong>Emergency Phone:</strong> <?= e($student['parent_phone'] ?: ($student['phone'] ?: 'N/A')) ?></div>
                    <div><strong>Address:</strong> <?= e($student['address'] ?: 'Springfield') ?></div>
                </div>

                <h6 class="fw-bold text-primary mb-2 small text-uppercase"><i class="fas fa-university me-1"></i> Campus Information</h6>
                <div class="small bg-light p-2 rounded border mb-3" style="font-size: 0.78rem;">
                    <div><strong>Institution:</strong> <?= e(getSetting('school_name', 'EduManage')) ?></div>
                    <div><strong>Campus Phone:</strong> <?= e(getSetting('phone', '+1 555-0199')) ?></div>
                    <div><strong>Email:</strong> <?= e(getSetting('email', 'info@edumanage.edu')) ?></div>
                    <div><strong>Address:</strong> <?= e(getSetting('address', 'Springfield Education Zone')) ?></div>
                </div>

                <div class="alert alert-warning py-1 px-2 small mb-0" style="font-size: 0.68rem;">
                    <i class="fas fa-exclamation-triangle me-1"></i> This card is non-transferable. If found, please return to school administration office.
                </div>
            </div>

            <!-- Barcode Mock Representation -->
            <div class="text-center pt-2 border-top">
                <div class="fw-bold font-monospace letter-spacing-1" style="font-size: 1.1rem; letter-spacing: 4px;">|||||| |||| |||||||| |||</div>
                <div class="text-muted" style="font-size: 0.65rem;"><?= e($student['admission_no']) ?></div>
            </div>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
