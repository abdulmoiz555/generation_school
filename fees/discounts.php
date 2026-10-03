<?php
/**
 * Fee Discounts & Concessions (Section 20)
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

requirePermission('fees_manage');

// Add Discount
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_discount') {
    $name = trim($_POST['name'] ?? '');
    $type = $_POST['discount_type'] ?? 'Fixed';
    $amount = (float)$_POST['amount'];
    $desc = trim($_POST['description'] ?? '');

    if (!empty($name) && $amount > 0) {
        $stmt = $pdo->prepare("INSERT INTO fee_discounts (name, discount_type, amount, description, status) VALUES (?, ?, ?, ?, 'active')");
        $stmt->execute([$name, $type, $amount, $desc]);
        logAudit('ADD_DISCOUNT', 'Fees', (int)$pdo->lastInsertId(), "Created discount {$name}");
        setFlashMessage('success', "Fee concession '{$name}' created!");
    }
    header("Location: " . BASE_PATH . "/fees/discounts.php");
    exit;
}

$discounts = $pdo->query("SELECT * FROM fee_discounts ORDER BY id ASC")->fetchAll();

$pageTitle = "Fee Discounts & Scholarships";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item">Fees</li>
                <li class="breadcrumb-item active">Discounts</li>
            </ol>
        </nav>
        <h3 class="fw-bold text-dark mb-0">Fee Discounts & Scholarships (Section 20)</h3>
    </div>
    <button type="button" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#addDiscountModal">
        <i class="fas fa-plus me-1"></i> + New Concession
    </button>
</div>

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Concession Title</th>
                        <th>Type</th>
                        <th>Value</th>
                        <th>Description</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($discounts as $d): ?>
                        <tr>
                            <td class="fw-bold text-dark"><?= e($d['name']) ?></td>
                            <td><span class="badge bg-light text-dark border"><?= e($d['discount_type']) ?></span></td>
                            <td class="fw-bold text-success">
                                <?= $d['discount_type'] === 'Percentage' ? $d['amount'] . '%' : formatCurrency($d['amount']) ?>
                            </td>
                            <td class="text-muted small"><?= e($d['description'] ?: '-') ?></td>
                            <td><span class="badge badge-soft-success">Active</span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="addDiscountModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold">Add Discount Scheme</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= BASE_PATH ?>/fees/discounts.php" method="POST">
                <input type="hidden" name="action" value="add_discount">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Discount Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Sibling Discount or Staff Child" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Type <span class="text-danger">*</span></label>
                            <select name="discount_type" class="form-select" required>
                                <option value="Percentage">Percentage (%)</option>
                                <option value="Fixed">Fixed Amount ($)</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Amount / Rate <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" name="amount" class="form-control" placeholder="15.00" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Description</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Eligibility guidelines..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Concession</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
