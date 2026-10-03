<?php
/**
 * Exam Types Management (Section 23)
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

requirePermission('exam_manage');

// Add Type
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_type') {
    $name = trim($_POST['name'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    if (!empty($name)) {
        $stmt = $pdo->prepare("INSERT INTO exam_types (name, description) VALUES (?, ?)");
        $stmt->execute([$name, $desc]);
        setFlashMessage('success', "Exam type '{$name}' created!");
    }
    header("Location: " . BASE_PATH . "/examinations/exam-types.php");
    exit;
}

$types = $pdo->query("SELECT * FROM exam_types ORDER BY id ASC")->fetchAll();

$pageTitle = "Exam Types";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item">Examinations</li>
                <li class="breadcrumb-item active">Exam Types</li>
            </ol>
        </nav>
        <h3 class="fw-bold text-dark mb-0">Examination Categories</h3>
    </div>
    <button type="button" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#addTypeModal">
        <i class="fas fa-plus me-1"></i> + New Exam Type
    </button>
</div>

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Type Name</th>
                        <th>Description</th>
                        <th>Associated Exams</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($types as $t): 
                        $exCount = (int)$pdo->query("SELECT COUNT(*) FROM exams WHERE exam_type_id = {$t['id']}")->fetchColumn();
                    ?>
                        <tr>
                            <td class="fw-bold text-dark"><?= e($t['name']) ?></td>
                            <td><?= e($t['description'] ?: 'Standard examination type') ?></td>
                            <td><span class="badge bg-primary-subtle text-primary border border-primary-subtle"><?= $exCount ?> Assessments</span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="addTypeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold">Add Exam Type</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= BASE_PATH ?>/examinations/exam-types.php" method="POST">
                <input type="hidden" name="action" value="add_type">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Type Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Monthly Progress Assessment" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Description</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Description of exam type..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Type</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
