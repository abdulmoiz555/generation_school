<?php
/**
 * Class Subjects Mapping (Section 14)
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

requirePermission('academics_manage');

$allClasses = getAllClasses();
$classId = (int)($_GET['class_id'] ?? ($allClasses[0]['id'] ?? 1));

$stmt = $pdo->prepare("
    SELECT s.*, c.class_name, t.name as teacher_name
    FROM subjects s
    JOIN classes c ON s.class_id = c.id
    LEFT JOIN teachers t ON s.teacher_id = t.id
    WHERE s.class_id = ?
    ORDER BY s.subject_name ASC
");
$stmt->execute([$classId]);
$subjects = $stmt->fetchAll();

$pageTitle = "Class Subjects Matrix";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/academics/subjects.php">Academics</a></li>
                <li class="breadcrumb-item active">Class Subjects</li>
            </ol>
        </nav>
        <h3 class="fw-bold text-dark mb-0">Class Subjects Curriculum</h3>
    </div>
    <a href="<?= BASE_PATH ?>/academics/subjects.php" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm">
        + Add Subject
    </a>
</div>

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-header bg-transparent py-3">
        <form action="<?= BASE_PATH ?>/academics/class-subjects.php" method="GET" class="row g-2">
            <div class="col-md-4">
                <select name="class_id" class="form-select" onchange="this.form.submit()">
                    <?php foreach ($allClasses as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $classId == $c['id'] ? 'selected' : '' ?>>
                            <?= e($c['class_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Subject Name</th>
                        <th>Code</th>
                        <th>Subject Type</th>
                        <th>Assigned Faculty</th>
                        <th>Max Marks</th>
                        <th>Pass Marks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($subjects)): ?>
                        <tr><td colspan="6" class="text-center py-4 text-muted">No subjects registered for this class.</td></tr>
                    <?php else: ?>
                        <?php foreach ($subjects as $s): ?>
                            <tr>
                                <td class="fw-bold text-dark"><?= e($s['subject_name']) ?></td>
                                <td><code><?= e($s['subject_code']) ?></code></td>
                                <td><span class="badge bg-light text-dark border"><?= e($s['subject_type']) ?></span></td>
                                <td><?= e($s['teacher_name'] ?: 'Not assigned') ?></td>
                                <td><?= $s['max_marks'] ?></td>
                                <td><?= $s['pass_marks'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
