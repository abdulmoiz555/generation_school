<?php
/**
 * Grading System Configuration (Section 25)
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

requirePermission('exam_manage');

// Update Grading Table
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_grades') {
    $gradeIds = $_POST['grade_id'] ?? [];
    $minPcts = $_POST['min_percentage'] ?? [];
    $maxPcts = $_POST['max_percentage'] ?? [];
    $remarks = $_POST['remarks'] ?? [];

    $stmt = $pdo->prepare("UPDATE grades SET min_percentage = ?, max_percentage = ?, remarks = ? WHERE id = ?");
    foreach ($gradeIds as $idx => $gId) {
        $stmt->execute([$minPcts[$idx], $maxPcts[$idx], $remarks[$idx], $gId]);
    }
    logAudit('UPDATE_GRADES', 'Examinations', null, 'Updated grading system score brackets');
    setFlashMessage('success', 'Grading criteria updated successfully!');
    header("Location: " . BASE_PATH . "/examinations/grades.php");
    exit;
}

$grades = $pdo->query("SELECT * FROM grades ORDER BY min_percentage DESC")->fetchAll();

$pageTitle = "Grading System Configuration";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item">Examinations</li>
                <li class="breadcrumb-item active">Grading Scale</li>
            </ol>
        </nav>
        <h3 class="fw-bold text-dark mb-0">Grading System Rules (Section 25)</h3>
    </div>
    <a href="<?= BASE_PATH ?>/examinations/results.php" class="btn btn-outline-info btn-sm rounded-pill px-3">
        <i class="fas fa-award me-1"></i> Result Cards
    </a>
</div>

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-header bg-transparent py-3">
        <h6 class="fw-bold text-primary mb-0"><i class="fas fa-sliders-h me-2"></i> Configure Standard Grade Percentage Thresholds</h6>
    </div>
    <div class="card-body p-4">
        <form action="<?= BASE_PATH ?>/examinations/grades.php" method="POST">
            <input type="hidden" name="action" value="save_grades">

            <div class="table-responsive mb-4">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th width="120">Grade Letter</th>
                            <th width="200">Min Percentage (%)</th>
                            <th width="200">Max Percentage (%)</th>
                            <th>Qualitative Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($grades as $idx => $g): ?>
                            <tr>
                                <td>
                                    <input type="hidden" name="grade_id[<?= $idx ?>]" value="<?= $g['id'] ?>">
                                    <span class="badge bg-primary fs-6 px-3 py-2"><?= e($g['grade_name']) ?></span>
                                </td>
                                <td>
                                    <input type="number" step="0.01" name="min_percentage[<?= $idx ?>]" class="form-control" value="<?= $g['min_percentage'] ?>" required>
                                </td>
                                <td>
                                    <input type="number" step="0.01" name="max_percentage[<?= $idx ?>]" class="form-control" value="<?= $g['max_percentage'] ?>" required>
                                </td>
                                <td>
                                    <input type="text" name="remarks[<?= $idx ?>]" class="form-control" value="<?= e($g['remarks']) ?>">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="text-end">
                <button type="submit" class="btn btn-primary px-5 py-2 rounded-pill shadow-sm">
                    <i class="fas fa-save me-2"></i> Save Grading Brackets
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
