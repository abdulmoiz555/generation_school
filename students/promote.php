<?php
/**
 * Student Promotion & Progression Module (Section 51)
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

requirePermission('student_promote');

$allClasses = getAllClasses();
$allSessions = getAllSessions();
$activeSession = getActiveSession();

$sourceClassId = (int)($_GET['source_class_id'] ?? 0);
$sourceSectionId = (int)($_GET['source_section_id'] ?? 0);

// Load students eligible for promotion
$eligibleStudents = [];
if ($sourceClassId > 0) {
    $sql = "SELECT s.*, c.class_name, sec.section_name FROM students s 
            JOIN classes c ON s.class_id = c.id 
            LEFT JOIN sections sec ON s.section_id = sec.id 
            WHERE s.class_id = ? AND s.status = 'active'";
    $params = [$sourceClassId];
    if ($sourceSectionId > 0) {
        $sql .= " AND s.section_id = ?";
        $params[] = $sourceSectionId;
    }
    $sql .= " ORDER BY s.roll_no ASC, s.first_name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $eligibleStudents = $stmt->fetchAll();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($csrfToken)) {
        $error = "Security token mismatch. Please try again.";
    } else {
        $targetSessionId = (int)($_POST['target_session_id'] ?? 0);
        $targetClassId = (int)($_POST['target_class_id'] ?? 0);
        $targetSectionId = (int)($_POST['target_section_id'] ?? 0);
        $selectedStudents = $_POST['student_ids'] ?? [];
        $promotionAction = $_POST['promotion_action'] ?? 'promote'; // promote, repeat, graduate

        if (empty($selectedStudents)) {
            $error = "Please select at least one student to process.";
        } elseif ($promotionAction === 'promote' && (!$targetClassId || !$targetSectionId)) {
            $error = "Please choose target class and section for promotion.";
        } else {
            try {
                $pdo->beginTransaction();
                $updateCount = 0;

                if ($promotionAction === 'promote') {
                    $stmt = $pdo->prepare("UPDATE students SET session_id = ?, class_id = ?, section_id = ?, status = 'active' WHERE id = ?");
                    foreach ($selectedStudents as $sId) {
                        $stmt->execute([$targetSessionId, $targetClassId, $targetSectionId, (int)$sId]);
                        $updateCount++;
                    }
                } elseif ($promotionAction === 'graduate') {
                    $stmt = $pdo->prepare("UPDATE students SET status = 'graduated' WHERE id = ?");
                    foreach ($selectedStudents as $sId) {
                        $stmt->execute([(int)$sId]);
                        $updateCount++;
                    }
                } elseif ($promotionAction === 'repeat') {
                    $stmt = $pdo->prepare("UPDATE students SET session_id = ? WHERE id = ?");
                    foreach ($selectedStudents as $sId) {
                        $stmt->execute([$targetSessionId, (int)$sId]);
                        $updateCount++;
                    }
                }

                $pdo->commit();
                logAudit('PROMOTE_STUDENTS', 'Students', null, "Processed {$promotionAction} for {$updateCount} students");
                setFlashMessage('success', "Successfully processed {$updateCount} students!");
                header("Location: " . BASE_PATH . "/students/index.php");
                exit;
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log("Promotion error: " . $e->getMessage());
                $error = "Failed to process promotion. Check database constraints.";
            }
        }
    }
}

$pageTitle = "Student Promotion & Progression";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/students/index.php">Students</a></li>
                <li class="breadcrumb-item active">Promotion</li>
            </ol>
        </nav>
        <h3 class="fw-bold text-dark mb-0">Student Promotion & Progression</h3>
    </div>
    <a href="<?= BASE_PATH ?>/students/index.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
        <i class="fas fa-arrow-left me-1"></i> Back to Directory
    </a>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show rounded-3 mb-4" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i> <?= e($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Step 1: Select Current Class -->
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-header bg-transparent py-3">
        <h6 class="fw-bold text-primary mb-0"><i class="fas fa-filter me-2"></i> Step 1: Choose Current Class & Section</h6>
    </div>
    <div class="card-body p-4">
        <form action="<?= BASE_PATH ?>/students/promote.php" method="GET" class="row g-3 align-items-end">
            <div class="col-md-5">
                <label class="form-label fw-semibold small">Current Class</label>
                <select name="source_class_id" class="form-select class-select" data-target-section="sourceSecSelect" required>
                    <option value="">-- Choose Class --</option>
                    <?php foreach ($allClasses as $cls): ?>
                        <option value="<?= $cls['id'] ?>" <?= $sourceClassId == $cls['id'] ? 'selected' : '' ?>>
                            <?= e($cls['class_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label fw-semibold small">Section (Optional)</label>
                <select name="source_section_id" id="sourceSecSelect" class="form-select">
                    <option value="">All Sections</option>
                    <?php if ($sourceClassId > 0): ?>
                        <?php foreach (getSections($sourceClassId) as $sec): ?>
                            <option value="<?= $sec['id'] ?>" <?= $sourceSectionId == $sec['id'] ? 'selected' : '' ?>>
                                <?= e($sec['section_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100 py-2"><i class="fas fa-users me-1"></i> Fetch</button>
            </div>
        </form>
    </div>
</div>

<?php if ($sourceClassId > 0): ?>
<form action="<?= BASE_PATH ?>/students/promote.php" method="POST">
    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">

    <!-- Step 2: Target Promotion Settings -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-transparent py-3">
            <h6 class="fw-bold text-primary mb-0"><i class="fas fa-level-up-alt me-2"></i> Step 2: Target Session, Class & Action</h6>
        </div>
        <div class="card-body p-4">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label fw-semibold small">Action Type</label>
                    <select name="promotion_action" class="form-select" id="promoActionSelect">
                        <option value="promote">Promote to Next Class</option>
                        <option value="repeat">Repeat in Current Class</option>
                        <option value="graduate">Graduate Student</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold small">Target Academic Session</label>
                    <select name="target_session_id" class="form-select" required>
                        <?php foreach ($allSessions as $sess): ?>
                            <option value="<?= $sess['id'] ?>" <?= ($sess['id'] != $activeSession['id']) ? 'selected' : '' ?>>
                                <?= e($sess['session_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 target-class-group">
                    <label class="form-label fw-semibold small">Target Class</label>
                    <select name="target_class_id" class="form-select class-select" data-target-section="targetSecSelect">
                        <option value="">-- Choose Next Class --</option>
                        <?php foreach ($allClasses as $cls): ?>
                            <option value="<?= $cls['id'] ?>" <?= ($cls['id'] == $sourceClassId + 1) ? 'selected' : '' ?>>
                                <?= e($cls['class_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 target-class-group">
                    <label class="form-label fw-semibold small">Target Section</label>
                    <select name="target_section_id" id="targetSecSelect" class="form-select">
                        <option value="">-- Choose Section --</option>
                        <?php 
                        $nextClassId = min(count($allClasses), $sourceClassId + 1);
                        foreach (getSections($nextClassId) as $sec): ?>
                            <option value="<?= $sec['id'] ?>"><?= e($sec['section_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Step 3: Student Selection Table -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-transparent py-3 d-flex justify-content-between align-items-center">
            <h6 class="fw-bold mb-0 text-dark">Step 3: Select Students (<?= count($eligibleStudents) ?> found)</h6>
            <div>
                <button type="button" class="btn btn-xs btn-outline-primary me-2" id="selectAllBtn">Select All</button>
                <button type="button" class="btn btn-xs btn-outline-secondary" id="deselectAllBtn">Deselect All</button>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th width="40"><input type="checkbox" id="masterCheckbox" checked></th>
                            <th>Roll #</th>
                            <th>Student Name</th>
                            <th>Father Name</th>
                            <th>Admission #</th>
                            <th>Current Class</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($eligibleStudents)): ?>
                            <tr><td colspan="6" class="text-center py-4 text-muted">No active students found in this class.</td></tr>
                        <?php else: ?>
                            <?php foreach ($eligibleStudents as $st): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="student_ids[]" value="<?= $st['id'] ?>" class="student-checkbox" checked>
                                    </td>
                                    <td><?= e($st['roll_no'] ?: '-') ?></td>
                                    <td>
                                        <div class="fw-bold text-dark"><?= e($st['first_name'] . ' ' . $st['last_name']) ?></div>
                                    </td>
                                    <td><?= e($st['father_name'] ?? 'N/A') ?></td>
                                    <td><code><?= e($st['admission_no']) ?></code></td>
                                    <td><span class="badge bg-light text-dark border"><?= e($st['class_name'] . ' - ' . $st['section_name']) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-transparent p-3 text-end">
            <button type="submit" class="btn btn-primary px-5 py-2 rounded-pill shadow-sm" onclick="return confirm('Confirm processing promotion for selected students?')">
                <i class="fas fa-check-circle me-2"></i> Execute Promotion
            </button>
        </div>
    </div>
</form>

<script>
document.getElementById('selectAllBtn')?.addEventListener('click', () => {
    document.querySelectorAll('.student-checkbox').forEach(cb => cb.checked = true);
    document.getElementById('masterCheckbox').checked = true;
});
document.getElementById('deselectAllBtn')?.addEventListener('click', () => {
    document.querySelectorAll('.student-checkbox').forEach(cb => cb.checked = false);
    document.getElementById('masterCheckbox').checked = false;
});
document.getElementById('masterCheckbox')?.addEventListener('change', function() {
    document.querySelectorAll('.student-checkbox').forEach(cb => cb.checked = this.checked);
});
document.getElementById('promoActionSelect')?.addEventListener('change', function() {
    const isGraduate = this.value === 'graduate';
    document.querySelectorAll('.target-class-group').forEach(el => {
        el.style.display = isGraduate ? 'none' : 'block';
    });
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
