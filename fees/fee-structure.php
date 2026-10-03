<?php
/**
 * Fee Structures (Section 17)
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

requirePermission('fees_manage');

$allClasses = getAllClasses();
$allSessions = getAllSessions();
$activeSession = getActiveSession();
$allFeeTypes = $pdo->query("SELECT * FROM fee_types ORDER BY name ASC")->fetchAll();

// Add Structure
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_structure') {
    $sessId = (int)$_POST['session_id'];
    $clsId = (int)$_POST['class_id'];
    $typeId = (int)$_POST['fee_type_id'];
    $amt = (float)$_POST['amount'];
    $dueDate = !empty($_POST['due_date']) ? $_POST['due_date'] : null;

    $stmt = $pdo->prepare("INSERT INTO fee_structures (session_id, class_id, fee_type_id, amount, due_date) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$sessId, $clsId, $typeId, $amt, $dueDate]);
    logAudit('ADD_FEE_STRUCTURE', 'Fees', (int)$pdo->lastInsertId(), "Created fee structure for class {$clsId}");
    setFlashMessage('success', "Fee structure configured successfully!");
    header("Location: " . BASE_PATH . "/fees/fee-structure.php");
    exit;
}

$stmt = $pdo->prepare("
    SELECT fs.*, c.class_name, ft.name as fee_name, ft.code as fee_code, sess.session_name
    FROM fee_structures fs
    JOIN classes c ON fs.class_id = c.id
    JOIN fee_types ft ON fs.fee_type_id = ft.id
    JOIN academic_sessions sess ON fs.session_id = sess.id
    WHERE fs.session_id = ?
    ORDER BY c.numeric_level ASC, ft.name ASC
");
$stmt->execute([$activeSession['id']]);
$structures = $stmt->fetchAll();

$pageTitle = "Fee Structures";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item">Fees</li>
                <li class="breadcrumb-item active">Fee Structures</li>
            </ol>
        </nav>
        <h3 class="fw-bold text-dark mb-0">Class Fee Structures</h3>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_PATH ?>/fees/fee-types.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
            <i class="fas fa-list me-1"></i> Fee Types
        </a>
        <button type="button" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#addStructureModal">
            <i class="fas fa-plus me-1"></i> + New Fee Structure
        </button>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-header bg-transparent py-3 d-flex justify-content-between align-items-center">
        <h6 class="fw-bold text-dark mb-0">Fee Schedules for Current Session: <?= e($activeSession['session_name'] ?? '2025-2026') ?></h6>
        <span class="badge bg-primary rounded-pill"><?= count($structures) ?> Structures Active</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Class</th>
                        <th>Fee Type</th>
                        <th>Standard Amount</th>
                        <th>Default Due Date</th>
                        <th>Session</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($structures)): ?>
                        <tr><td colspan="5" class="text-center py-4 text-muted">No fee structures configured for this session.</td></tr>
                    <?php else: ?>
                        <?php foreach ($structures as $st): ?>
                            <tr>
                                <td class="fw-bold text-dark"><?= e($st['class_name']) ?></td>
                                <td>
                                    <?= e($st['fee_name']) ?>
                                    <small class="text-muted">(<code><?= e($st['fee_code']) ?></code>)</small>
                                </td>
                                <td class="fw-bold text-success fs-6"><?= formatCurrency($st['amount']) ?></td>
                                <td><?= formatDate($st['due_date']) ?></td>
                                <td><span class="badge bg-light text-dark border"><?= e($st['session_name']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="addStructureModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold">Configure Fee Structure</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= BASE_PATH ?>/fees/fee-structure.php" method="POST">
                <input type="hidden" name="action" value="add_structure">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Academic Session <span class="text-danger">*</span></label>
                        <select name="session_id" class="form-select" required>
                            <?php foreach ($allSessions as $s): ?>
                                <option value="<?= $s['id'] ?>" <?= $s['id'] == $activeSession['id'] ? 'selected' : '' ?>>
                                    <?= e($s['session_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Class <span class="text-danger">*</span></label>
                        <select name="class_id" class="form-select" required>
                            <?php foreach ($allClasses as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= e($c['class_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Fee Type <span class="text-danger">*</span></label>
                        <select name="fee_type_id" class="form-select" required>
                            <?php foreach ($allFeeTypes as $ft): ?>
                                <option value="<?= $ft['id'] ?>"><?= e($ft['name']) ?> (<?= e($ft['code']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Amount <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" name="amount" class="form-control" placeholder="250.00" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Default Due Date</label>
                            <input type="date" name="due_date" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Fee Structure</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
