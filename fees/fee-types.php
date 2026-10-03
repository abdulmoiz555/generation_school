<?php
/**
 * Fee Types Configuration (Section 17)
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

requirePermission('fees_manage');

// Add Fee Type
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_fee_type') {
    $name = trim($_POST['name'] ?? '');
    $code = trim($_POST['code'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $isRecurring = isset($_POST['is_recurring']) ? 1 : 0;

    if (!empty($name) && !empty($code)) {
        $stmt = $pdo->prepare("INSERT INTO fee_types (name, code, description, is_recurring) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $code, $desc, $isRecurring]);
        logAudit('ADD_FEE_TYPE', 'Fees', (int)$pdo->lastInsertId(), "Created fee type {$name}");
        setFlashMessage('success', "Fee type '{$name}' created successfully!");
    }
    header("Location: " . BASE_PATH . "/fees/fee-types.php");
    exit;
}

$types = $pdo->query("SELECT * FROM fee_types ORDER BY id ASC")->fetchAll();

$pageTitle = "Fee Types Configuration";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item">Fees</li>
                <li class="breadcrumb-item active">Fee Types</li>
            </ol>
        </nav>
        <h3 class="fw-bold text-dark mb-0">Fee Types & Categories</h3>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_PATH ?>/fees/fee-structure.php" class="btn btn-outline-primary btn-sm rounded-pill px-3 shadow-sm">
            <i class="fas fa-tags me-1"></i> Fee Structures
        </a>
        <button type="button" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#addFeeTypeModal">
            <i class="fas fa-plus me-1"></i> + New Fee Type
        </button>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Fee Type Name</th>
                        <th>Code</th>
                        <th>Billing Nature</th>
                        <th>Description</th>
                        <th>Created Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($types as $t): ?>
                        <tr>
                            <td class="fw-bold text-dark"><?= e($t['name']) ?></td>
                            <td><code><?= e($t['code']) ?></code></td>
                            <td>
                                <?php if ($t['is_recurring']): ?>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle">Recurring (Monthly)</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">One-Time / Annual</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted small"><?= e($t['description'] ?: 'Standard fee category') ?></td>
                            <td><?= formatDate($t['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addFeeTypeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold">Add Fee Type</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= BASE_PATH ?>/fees/fee-types.php" method="POST">
                <input type="hidden" name="action" value="add_fee_type">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Fee Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Monthly Tuition Fee" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Fee Code <span class="text-danger">*</span></label>
                        <input type="text" name="code" class="form-control" placeholder="e.g. TUI-MON" required>
                    </div>
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_recurring" id="recSwitch" checked>
                            <label class="form-check-label small fw-semibold" for="recSwitch">Recurring Monthly Charge</label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Description</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Fee category details..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Fee Type</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
