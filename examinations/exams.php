<?php
/**
 * Examinations Management (Section 23)
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

requirePermission('exam_manage');

$allSessions = getAllSessions();
$activeSession = getActiveSession();
$allExamTypes = $pdo->query("SELECT * FROM exam_types ORDER BY id ASC")->fetchAll();

// Add Exam
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_exam') {
    $sessId = (int)$_POST['session_id'];
    $typeId = (int)$_POST['exam_type_id'];
    $title = trim($_POST['title'] ?? '');
    $startDate = $_POST['start_date'] ?? '';
    $endDate = $_POST['end_date'] ?? '';
    $status = $_POST['status'] ?? 'Upcoming';

    if (!empty($title) && !empty($startDate) && !empty($endDate)) {
        $stmt = $pdo->prepare("INSERT INTO exams (session_id, exam_type_id, title, start_date, end_date, status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$sessId, $typeId, $title, $startDate, $endDate, $status]);
        logAudit('ADD_EXAM', 'Examinations', (int)$pdo->lastInsertId(), "Created exam {$title}");
        setFlashMessage('success', "Examination '{$title}' created!");
    }
    header("Location: " . BASE_PATH . "/examinations/exams.php");
    exit;
}

$stmt = $pdo->prepare("
    SELECT e.*, et.name as type_name, s.session_name,
           (SELECT COUNT(*) FROM exam_subjects es WHERE es.exam_id = e.id) as subjects_count
    FROM exams e
    JOIN exam_types et ON e.exam_type_id = et.id
    JOIN academic_sessions s ON e.session_id = s.id
    ORDER BY e.start_date DESC
");
$stmt->execute();
$exams = $stmt->fetchAll();

$pageTitle = "Examinations Schedule";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item">Examinations</li>
                <li class="breadcrumb-item active">Exams</li>
            </ol>
        </nav>
        <h3 class="fw-bold text-dark mb-0">Examinations & Schedules</h3>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_PATH ?>/examinations/marks.php" class="btn btn-outline-primary btn-sm rounded-pill px-3 shadow-sm">
            <i class="fas fa-pen-nib me-1"></i> Marks Entry
        </a>
        <button type="button" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#addExamModal">
            <i class="fas fa-plus me-1"></i> + New Exam
        </button>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Exam Title</th>
                        <th>Type</th>
                        <th>Academic Session</th>
                        <th>Schedule Dates</th>
                        <th>Subjects</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($exams as $ex): ?>
                        <tr>
                            <td>
                                <div class="fw-bold text-dark"><?= e($ex['title']) ?></div>
                            </td>
                            <td><span class="badge bg-light text-dark border"><?= e($ex['type_name']) ?></span></td>
                            <td><?= e($ex['session_name']) ?></td>
                            <td><?= formatDate($ex['start_date']) ?> to <?= formatDate($ex['end_date']) ?></td>
                            <td><span class="badge bg-primary-subtle text-primary border border-primary-subtle"><?= $ex['subjects_count'] ?> Subjects</span></td>
                            <td>
                                <span class="badge <?= $ex['status'] === 'Active' ? 'bg-success' : ($ex['status'] === 'Completed' ? 'bg-secondary' : 'bg-info') ?>">
                                    <?= e($ex['status']) ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <a href="<?= BASE_PATH ?>/examinations/marks.php?exam_id=<?= $ex['id'] ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                    <i class="fas fa-pen-nib me-1"></i> Enter Marks
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="addExamModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold">Create Examination Assessment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= BASE_PATH ?>/examinations/exams.php" method="POST">
                <input type="hidden" name="action" value="add_exam">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Exam Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" placeholder="e.g. Fall Semester Mid-Terms 2026" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Session <span class="text-danger">*</span></label>
                            <select name="session_id" class="form-select" required>
                                <?php foreach ($allSessions as $s): ?>
                                    <option value="<?= $s['id'] ?>" <?= $s['id'] == $activeSession['id'] ? 'selected' : '' ?>>
                                        <?= e($s['session_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Exam Type <span class="text-danger">*</span></label>
                            <select name="exam_type_id" class="form-select" required>
                                <?php foreach ($allExamTypes as $et): ?>
                                    <option value="<?= $et['id'] ?>"><?= e($et['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Start Date <span class="text-danger">*</span></label>
                            <input type="date" name="start_date" class="form-control" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">End Date <span class="text-danger">*</span></label>
                            <input type="date" name="end_date" class="form-control" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Status</label>
                        <select name="status" class="form-select">
                            <option value="Upcoming">Upcoming</option>
                            <option value="Active">Active</option>
                            <option value="Completed">Completed</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Schedule Exam</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
