<?php
/**
 * Subjects Management (Section 14)
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

requirePermission('academics_manage');

$allClasses = getAllClasses();
$allTeachers = $pdo->query("SELECT id, name FROM teachers WHERE status = 'active' ORDER BY name ASC")->fetchAll();

// Add Subject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_subject') {
    $name = trim($_POST['subject_name'] ?? '');
    $code = trim($_POST['subject_code'] ?? '');
    $classId = (int)($_POST['class_id'] ?? 0);
    $maxMarks = (int)($_POST['max_marks'] ?? 100);
    $passMarks = (int)($_POST['pass_marks'] ?? 40);
    $teacherId = !empty($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : null;
    $type = $_POST['subject_type'] ?? 'Theory';

    if (!empty($name) && !empty($code) && $classId > 0) {
        $stmt = $pdo->prepare("
            INSERT INTO subjects (subject_name, subject_code, class_id, max_marks, pass_marks, teacher_id, subject_type)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$name, $code, $classId, $maxMarks, $passMarks, $teacherId, $type]);
        logAudit('ADD_SUBJECT', 'Academics', (int)$pdo->lastInsertId(), "Added subject {$name} ({$code})");
        setFlashMessage('success', "Subject '{$name}' created successfully!");
    }
    header("Location: " . BASE_PATH . "/academics/subjects.php");
    exit;
}

// Fetch subjects
$classFilter = (int)($_GET['class_id'] ?? 0);
$where = $classFilter > 0 ? "WHERE s.class_id = {$classFilter}" : "";

$stmt = $pdo->query("
    SELECT s.*, c.class_name, t.name as teacher_name
    FROM subjects s
    JOIN classes c ON s.class_id = c.id
    LEFT JOIN teachers t ON s.teacher_id = t.id
    {$where}
    ORDER BY c.numeric_level ASC, s.subject_name ASC
");
$subjects = $stmt->fetchAll();

$pageTitle = "Subjects Management";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item">Academics</li>
                <li class="breadcrumb-item active">Subjects</li>
            </ol>
        </nav>
        <h3 class="fw-bold text-dark mb-0">Course Subjects Catalog</h3>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#addSubjectModal">
            <i class="fas fa-plus me-1"></i> + New Subject
        </button>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3">
        <form action="<?= BASE_PATH ?>/academics/subjects.php" method="GET" class="row g-2 align-items-center">
            <div class="col-md-4">
                <select name="class_id" class="form-select" onchange="this.form.submit()">
                    <option value="">All Classes</option>
                    <?php foreach ($allClasses as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $classFilter == $c['id'] ? 'selected' : '' ?>>
                            <?= e($c['class_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <a href="<?= BASE_PATH ?>/academics/subjects.php" class="btn btn-light border w-100">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Subject Name</th>
                        <th>Code</th>
                        <th>Class</th>
                        <th>Type</th>
                        <th>Assigned Teacher</th>
                        <th>Max Marks</th>
                        <th>Pass Marks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($subjects)): ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted">No subjects configured.</td></tr>
                    <?php else: ?>
                        <?php foreach ($subjects as $sub): ?>
                            <tr>
                                <td class="fw-bold text-dark"><?= e($sub['subject_name']) ?></td>
                                <td><code><?= e($sub['subject_code']) ?></code></td>
                                <td><span class="badge bg-light text-dark border"><?= e($sub['class_name']) ?></span></td>
                                <td><span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle"><?= e($sub['subject_type']) ?></span></td>
                                <td><?= e($sub['teacher_name'] ?: 'Unassigned') ?></td>
                                <td><span class="fw-semibold text-primary"><?= $sub['max_marks'] ?></span></td>
                                <td><span class="text-danger"><?= $sub['pass_marks'] ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Subject Modal -->
<div class="modal fade" id="addSubjectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold">Add Subject</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= BASE_PATH ?>/academics/subjects.php" method="POST">
                <input type="hidden" name="action" value="add_subject">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Subject Name <span class="text-danger">*</span></label>
                        <input type="text" name="subject_name" class="form-control" placeholder="e.g. Physics or Mathematics" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Subject Code <span class="text-danger">*</span></label>
                        <input type="text" name="subject_code" class="form-control" placeholder="e.g. PHY-101" required>
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
                        <label class="form-label small fw-semibold">Subject Type</label>
                        <select name="subject_type" class="form-select">
                            <option value="Theory">Theory</option>
                            <option value="Practical">Practical</option>
                            <option value="Both">Both (Theory + Lab)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Assigned Teacher</label>
                        <select name="teacher_id" class="form-select">
                            <option value="">-- Assign Teacher --</option>
                            <?php foreach ($allTeachers as $t): ?>
                                <option value="<?= $t['id'] ?>"><?= e($t['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Maximum Marks</label>
                            <input type="number" name="max_marks" class="form-control" value="100">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Passing Marks</label>
                            <input type="number" name="pass_marks" class="form-control" value="40">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Subject</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
