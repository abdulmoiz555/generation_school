<?php
/**
 * View Parent Details & Children Roster (Section 11)
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

requirePermission('student_view');

$parentId = (int)($_GET['id'] ?? 0);
$pStmt = $pdo->prepare("SELECT * FROM parents WHERE id = ?");
$pStmt->execute([$parentId]);
$parent = $pStmt->fetch();

if (!$parent) {
    setFlashMessage('error', 'Parent profile not found.');
    header("Location: " . BASE_PATH . "/parents/index.php");
    exit;
}

// Fetch all linked children
$chStmt = $pdo->prepare("
    SELECT s.*, c.class_name, sec.section_name 
    FROM students s
    LEFT JOIN classes c ON s.class_id = c.id
    LEFT JOIN sections sec ON s.section_id = sec.id
    WHERE s.parent_id = ?
    ORDER BY s.id ASC
");
$chStmt->execute([$parentId]);
$children = $chStmt->fetchAll();

$pageTitle = "Parent - " . $parent['father_name'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/parents/index.php">Parents</a></li>
                <li class="breadcrumb-item active"><?= e($parent['father_name']) ?></li>
            </ol>
        </nav>
        <h3 class="fw-bold text-dark mb-0">Parent Profile</h3>
    </div>
    <div>
        <a href="<?= BASE_PATH ?>/parents/edit.php?id=<?= $parent['id'] ?>" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm">
            <i class="fas fa-edit me-1"></i> Edit Profile
        </a>
        <a href="<?= BASE_PATH ?>/parents/index.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3 ms-2">
            Back
        </a>
    </div>
</div>

<div class="row g-4">
    <!-- Parent Details Card -->
    <div class="col-12 col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 p-4 text-center">
            <div class="avatar-initials mx-auto mb-3" style="width: 80px; height: 80px; font-size: 2rem; background: linear-gradient(135deg, #10b981, #06b6d4);">
                <?= strtoupper(substr($parent['father_name'], 0, 1)) ?>
            </div>
            <h5 class="fw-bold text-dark mb-1"><?= e($parent['father_name']) ?></h5>
            <div class="text-muted small mb-3"><?= e($parent['occupation'] ?: 'Parent / Guardian') ?></div>

            <div class="border-top pt-3 text-start small">
                <div class="d-flex justify-content-between py-1">
                    <span class="text-muted">Mother's Name:</span>
                    <strong><?= e($parent['mother_name'] ?: '-') ?></strong>
                </div>
                <div class="d-flex justify-content-between py-1">
                    <span class="text-muted">CNIC / ID:</span>
                    <code><?= e($parent['cnic'] ?: '-') ?></code>
                </div>
                <div class="d-flex justify-content-between py-1">
                    <span class="text-muted">Primary Phone:</span>
                    <strong><?= e($parent['phone']) ?></strong>
                </div>
                <div class="d-flex justify-content-between py-1">
                    <span class="text-muted">Alternate Phone:</span>
                    <span><?= e($parent['alternate_phone'] ?: '-') ?></span>
                </div>
                <div class="d-flex justify-content-between py-1">
                    <span class="text-muted">Email:</span>
                    <span><?= e($parent['email'] ?: '-') ?></span>
                </div>
                <div class="d-flex justify-content-between py-1">
                    <span class="text-muted">City:</span>
                    <span><?= e($parent['city'] ?: '-') ?></span>
                </div>
                <div class="py-2 mt-1">
                    <span class="text-muted d-block">Residential Address:</span>
                    <span class="text-dark"><?= e($parent['address'] ?: 'Not provided') ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Children Enrolled Card -->
    <div class="col-12 col-lg-8">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-transparent py-3 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold text-primary mb-0"><i class="fas fa-child me-2"></i> Associated Enrolled Children (<?= count($children) ?>)</h6>
                <a href="<?= BASE_PATH ?>/students/add.php" class="btn btn-xs btn-outline-primary">+ Enroll Sibling</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Student</th>
                                <th>Admission #</th>
                                <th>Class & Sec</th>
                                <th>Roll #</th>
                                <th>Outstanding Fees</th>
                                <th class="text-end">Profile</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($children)): ?>
                                <tr><td colspan="6" class="text-center py-4 text-muted">No students currently linked to this parent profile.</td></tr>
                            <?php else: ?>
                                <?php foreach ($children as $ch): 
                                    $balance = getFeeBalance($ch['id']);
                                ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-initials me-2" style="width: 34px; height: 34px; font-size: 0.8rem;">
                                                    <?= strtoupper(substr($ch['first_name'], 0, 1)) ?>
                                                </div>
                                                <div>
                                                    <a href="<?= BASE_PATH ?>/students/view.php?id=<?= $ch['id'] ?>" class="fw-bold text-dark text-decoration-none">
                                                        <?= e($ch['first_name'] . ' ' . $ch['last_name']) ?>
                                                    </a>
                                                    <div class="text-muted small"><?= e($ch['gender']) ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td><code><?= e($ch['admission_no']) ?></code></td>
                                        <td><?= e($ch['class_name'] . ' - ' . $ch['section_name']) ?></td>
                                        <td><?= e($ch['roll_no'] ?: '-') ?></td>
                                        <td>
                                            <?php if ($balance > 0): ?>
                                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                                                    <?= formatCurrency($balance) ?> Due
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle">
                                                    All Paid
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <a href="<?= BASE_PATH ?>/students/view.php?id=<?= $ch['id'] ?>" class="btn btn-sm btn-outline-info">
                                                <i class="fas fa-eye me-1"></i> View
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
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
