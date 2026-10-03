<?php
/**
 * Exam Datesheets & Subject Schedule (Section 23)
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

requirePermission('exam_manage');

$allExams = $pdo->query("SELECT * FROM exams ORDER BY start_date DESC")->fetchAll();
$allClasses = getAllClasses();

$examId = (int)($_GET['exam_id'] ?? ($allExams[0]['id'] ?? 1));
$exam = $pdo->query("SELECT * FROM exams WHERE id = {$examId}")->fetch();

$stmt = $pdo->prepare("
    SELECT es.*, c.class_name, s.subject_name, s.subject_code
    FROM exam_subjects es
    JOIN classes c ON es.class_id = c.id
    JOIN subjects s ON es.subject_id = s.id
    WHERE es.exam_id = ?
    ORDER BY es.exam_date ASC, es.start_time ASC
");
$stmt->execute([$examId]);
$datesheet = $stmt->fetchAll();

$pageTitle = "Exam Schedule & Datesheet";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3 no-print">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/examinations/exams.php">Examinations</a></li>
                <li class="breadcrumb-item active">Datesheet</li>
            </ol>
        </nav>
        <h3 class="fw-bold text-dark mb-0">Examination Datesheet Schedule</h3>
    </div>
    <div class="d-flex gap-2">
        <button onclick="window.print()" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
            <i class="fas fa-print me-1"></i> Print Datesheet
        </button>
        <a href="<?= BASE_PATH ?>/examinations/marks.php?exam_id=<?= $examId ?>" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm">
            <i class="fas fa-pen-nib me-1"></i> Marks Entry
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4 no-print">
    <div class="card-body p-3">
        <form action="<?= BASE_PATH ?>/examinations/exam-subjects.php" method="GET" class="row g-2 align-items-center">
            <div class="col-md-5">
                <select name="exam_id" class="form-select" onchange="this.form.submit()">
                    <?php foreach ($allExams as $ex): ?>
                        <option value="<?= $ex['id'] ?>" <?= $examId == $ex['id'] ? 'selected' : '' ?>>
                            <?= e($ex['title']) ?> (<?= formatDate($ex['start_date']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
</div>

<div class="printable-area">
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-header bg-transparent py-3 text-center border-bottom">
            <h4 class="fw-bold text-dark mb-1"><?= e(getSetting('school_name', 'Springfield International Academy')) ?></h4>
            <h5 class="fw-semibold text-primary mb-0"><?= e($exam['title'] ?? 'Examination') ?> Datesheet</h5>
            <small class="text-muted">Official Examination Schedule</small>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle mb-0 text-center">
                    <thead class="table-light">
                        <tr>
                            <th>Exam Date</th>
                            <th>Time Window</th>
                            <th>Class</th>
                            <th class="text-start">Course Subject</th>
                            <th>Code</th>
                            <th>Max Marks</th>
                            <th>Room / Hall</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($datesheet)): ?>
                            <tr><td colspan="7" class="text-center py-4 text-muted">No scheduled subjects found for this examination.</td></tr>
                        <?php else: ?>
                            <?php foreach ($datesheet as $ds): ?>
                                <tr>
                                    <td><strong class="text-dark"><?= formatDate($ds['exam_date']) ?></strong></td>
                                    <td><span class="badge bg-light text-dark border"><?= date('H:i', strtotime($ds['start_time'])) ?> - <?= date('H:i', strtotime($ds['end_time'])) ?></span></td>
                                    <td><span class="badge bg-primary-subtle text-primary border border-primary-subtle"><?= e($ds['class_name']) ?></span></td>
                                    <td class="text-start fw-bold text-dark"><?= e($ds['subject_name']) ?></td>
                                    <td><code><?= e($ds['subject_code']) ?></code></td>
                                    <td><span class="fw-bold"><?= $ds['max_marks'] ?></span></td>
                                    <td><?= e($ds['room_no'] ?: 'Examination Hall') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
