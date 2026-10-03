<?php
/**
 * View Staff Member
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

requirePermission('hr_manage');

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("
    SELECT s.*, d.name as dept_name, des.title as desig_title
    FROM staff s
    LEFT JOIN departments d ON s.department_id = d.id
    LEFT JOIN designations des ON s.designation_id = des.id
    WHERE s.id = ?
");
$stmt->execute([$id]);
$staff = $stmt->fetch();

if (!$staff) {
    setFlashMessage('error', 'Staff member not found.');
    header("Location: " . BASE_PATH . "/staff/index.php");
    exit;
}

$pageTitle = "Staff - " . $staff['name'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/staff/index.php">Staff</a></li>
                <li class="breadcrumb-item active"><?= e($staff['name']) ?></li>
            </ol>
        </nav>
        <h3 class="fw-bold text-dark mb-0">Staff Profile</h3>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_PATH ?>/staff/edit.php?id=<?= $staff['id'] ?>" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm">
            <i class="fas fa-edit me-1"></i> Edit Staff
        </a>
        <a href="<?= BASE_PATH ?>/staff/index.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
            Back
        </a>
    </div>
</div>

<div class="row g-4">
    <div class="col-12 col-lg-5">
        <div class="card border-0 shadow-sm rounded-4 p-4 text-center">
            <div class="avatar-initials mx-auto mb-3" style="width: 80px; height: 80px; font-size: 2rem;">
                <?= strtoupper(substr($staff['name'], 0, 1)) ?>
            </div>
            <h4 class="fw-bold text-dark mb-1"><?= e($staff['name']) ?></h4>
            <div class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1 rounded-pill mb-3">
                <?= e($staff['staff_code']) ?>
            </div>

            <div class="border-top pt-3 text-start small">
                <div class="d-flex justify-content-between py-1">
                    <span class="text-muted">Department:</span>
                    <strong><?= e($staff['dept_name'] ?? 'Operations') ?></strong>
                </div>
                <div class="d-flex justify-content-between py-1">
                    <span class="text-muted">Designation:</span>
                    <strong><?= e($staff['desig_title'] ?? 'Staff') ?></strong>
                </div>
                <div class="d-flex justify-content-between py-1">
                    <span class="text-muted">Monthly Salary:</span>
                    <strong class="text-success"><?= formatCurrency($staff['salary']) ?></strong>
                </div>
                <div class="d-flex justify-content-between py-1">
                    <span class="text-muted">Joining Date:</span>
                    <span><?= formatDate($staff['joining_date']) ?></span>
                </div>
                <div class="d-flex justify-content-between py-1">
                    <span class="text-muted">Phone:</span>
                    <span><?= e($staff['phone']) ?></span>
                </div>
                <div class="d-flex justify-content-between py-1">
                    <span class="text-muted">Email:</span>
                    <span><?= e($staff['email'] ?: 'N/A') ?></span>
                </div>
                <div class="py-2 mt-1">
                    <span class="text-muted d-block">Address:</span>
                    <span><?= e($staff['address'] ?: 'Not on record') ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
