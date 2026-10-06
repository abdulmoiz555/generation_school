<?php
/**
 * Timetable Management (Section 15)
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$allClasses = getAllClasses();
$allTeachers = $pdo->query("SELECT id, name FROM teachers WHERE status = 'active' ORDER BY name ASC")->fetchAll();
$activeSession = getActiveSession();

$classId = (int)($_GET['class_id'] ?? ($allClasses[0]['id'] ?? 1));
$sectionId = (int)($_GET['section_id'] ?? 1);
$sections = getSections($classId);

// Add Timetable Slot
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_slot' && hasPermission('academics_manage')) {
    $cId = (int)$_POST['class_id'];
    $sId = (int)$_POST['section_id'];
    $day = $_POST['day'];
    $period = (int)$_POST['period_number'];
    $startTime = $_POST['start_time'];
    $endTime = $_POST['end_time'];
    $subjectId = (int)$_POST['subject_id'];
    $teacherId = !empty($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : null;
    $roomNo = trim($_POST['room_no'] ?? '');

    $stmt = $pdo->prepare("
        INSERT INTO timetables (session_id, class_id, section_id, day, period_number, start_time, end_time, subject_id, teacher_id, room_no)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$activeSession['id'], $cId, $sId, $day, $period, $startTime, $endTime, $subjectId, $teacherId, $roomNo]);
    logAudit('ADD_TIMETABLE_SLOT', 'Academics', (int)$pdo->lastInsertId(), "Added timetable slot for class {$cId}");
    setFlashMessage('success', "Timetable period added!");
    header("Location: " . BASE_PATH . "/academics/timetable.php?class_id={$cId}&section_id={$sId}");
    exit;
}

// Fetch Timetable entries for selected class & section
$stmt = $pdo->prepare("
    SELECT tt.*, s.subject_name, s.subject_code, t.name as teacher_name
    FROM timetables tt
    JOIN subjects s ON tt.subject_id = s.id
    LEFT JOIN teachers t ON tt.teacher_id = t.id
    WHERE tt.class_id = ? AND tt.section_id = ? AND tt.session_id = ?
    ORDER BY tt.period_number ASC
");
$stmt->execute([$classId, $sectionId, $activeSession['id']]);
$slots = $stmt->fetchAll();

// Group by day
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
$scheduleByDay = [];
foreach ($days as $d) {
    $scheduleByDay[$d] = [];
}
foreach ($slots as $slot) {
    $scheduleByDay[$slot['day']][] = $slot;
}

// Available subjects for the class
$subjectsForClass = $pdo->query("SELECT id, subject_name FROM subjects WHERE class_id = {$classId} ORDER BY subject_name ASC")->fetchAll();

$currentClass = getClass($classId);
$currentSection = getSection($sectionId);

$pageTitle = "Timetable - " . ($currentClass['class_name'] ?? 'Class') . ' ' . ($currentSection['section_name'] ?? '');
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3 no-print">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item">Academics</li>
                <li class="breadcrumb-item active">Timetable</li>
            </ol>
        </nav>
        <h3 class="fw-bold text-dark mb-0">Weekly Class Timetable</h3>
    </div>
    <div class="d-flex gap-2">
        <button onclick="window.print()" class="btn btn-outline-secondary btn-sm rounded-pill px-3 shadow-sm">
            <i class="fas fa-print me-1"></i> Print Timetable
        </button>
        <?php if (hasPermission('academics_manage')): ?>
            <button type="button" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#addSlotModal">
                <i class="fas fa-plus me-1"></i> + Add Period Slot
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- Class / Section Selector (No Print) -->
<div class="card border-0 shadow-sm mb-4 no-print">
    <div class="card-body p-3">
        <form action="<?= BASE_PATH ?>/academics/timetable.php" method="GET" class="row g-2 align-items-center">
            <div class="col-md-5">
                <select name="class_id" class="form-select class-select" data-target-section="ttSecSelect" onchange="this.form.submit()">
                    <?php foreach ($allClasses as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $classId == $c['id'] ? 'selected' : '' ?>>
                            <?= e($c['class_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-5">
                <select name="section_id" id="ttSecSelect" class="form-select" onchange="this.form.submit()">
                    <?php foreach ($sections as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= $sectionId == $s['id'] ? 'selected' : '' ?>>
                            <?= e($s['section_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Load Schedule</button>
            </div>
        </form>
    </div>
</div>

<!-- Printable Timetable Board -->
<div class="printable-area">
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-transparent py-3 text-center border-bottom">
            <h4 class="fw-bold text-dark mb-0"><?= e(getSetting('school_name', 'EduManage')) ?></h4>
            <div class="text-primary fw-semibold fs-5"><?= e($currentClass['class_name'] ?? '') ?> - <?= e($currentSection['section_name'] ?? '') ?> Weekly Timetable</div>
            <div class="text-muted small">Academic Session: <?= e($activeSession['session_name'] ?? '2025-2026') ?></div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered align-middle mb-0 text-center" style="font-size: 0.85rem;">
                    <thead class="table-light">
                        <tr>
                            <th width="100">Day</th>
                            <th>Period 1<br><span class="text-muted fw-normal" style="font-size: 0.7rem;">08:30-09:15</span></th>
                            <th>Period 2<br><span class="text-muted fw-normal" style="font-size: 0.7rem;">09:15-10:00</span></th>
                            <th>Period 3<br><span class="text-muted fw-normal" style="font-size: 0.7rem;">10:00-10:45</span></th>
                            <th>Period 4<br><span class="text-muted fw-normal" style="font-size: 0.7rem;">10:45-11:30</span></th>
                            <th class="bg-warning-subtle text-warning-emphasis fw-bold" style="min-width: 90px;">
                                <i class="fas fa-coffee me-1"></i> BREAK<br><span class="fw-normal" style="font-size: 0.7rem;">11:30-12:00</span>
                            </th>
                            <th>Period 5<br><span class="text-muted fw-normal" style="font-size: 0.7rem;">12:00-12:45</span></th>
                            <th>Period 6<br><span class="text-muted fw-normal" style="font-size: 0.7rem;">12:45-01:30</span></th>
                            <th>Period 7<br><span class="text-muted fw-normal" style="font-size: 0.7rem;">01:30-02:15</span></th>
                            <th>Period 8<br><span class="text-muted fw-normal" style="font-size: 0.7rem;">02:15-02:55</span></th>
                            <th>Period 9<br><span class="text-muted fw-normal" style="font-size: 0.7rem;">02:55-03:35</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($days as $d): ?>
                            <tr>
                                <th class="table-light fw-bold text-start ps-3"><?= $d ?></th>
                                <?php for ($p = 1; $p <= 9; $p++): 
                                    // Insert Break Column after Period 4
                                    if ($p === 5): ?>
                                        <td class="bg-warning-subtle text-center align-middle p-1" style="width: 90px; background-color: #fef3c7 !important;">
                                            <div class="fw-bold text-warning-emphasis small text-uppercase" style="letter-spacing: 0.5px; font-size: 0.72rem;">
                                                <i class="fas fa-utensils d-block mb-1"></i> Recess / Break
                                            </div>
                                        </td>
                                    <?php endif; 

                                    // Find slot for this period
                                    $matchedSlot = null;
                                    foreach ($scheduleByDay[$d] as $s) {
                                        if ($s['period_number'] == $p) {
                                            $matchedSlot = $s;
                                            break;
                                        }
                                    }
                                ?>
                                    <td class="p-1" style="height: 75px; min-width: 110px;">
                                        <?php if ($matchedSlot): ?>
                                            <div class="p-2 rounded bg-light border h-100 d-flex flex-column justify-content-center">
                                                <div class="fw-bold text-primary small text-truncate" title="<?= e($matchedSlot['subject_name']) ?>"><?= e($matchedSlot['subject_name']) ?></div>
                                                <div class="text-muted text-truncate" style="font-size: 0.7rem;"><?= e($matchedSlot['teacher_name'] ?: 'Faculty') ?></div>
                                                <div class="badge bg-secondary-subtle text-secondary border mt-1" style="font-size: 0.62rem;">
                                                    <?= e($matchedSlot['room_no'] ?: 'Room TBD') ?>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted small">-</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endfor; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Timetable Slot Modal -->
<div class="modal fade" id="addSlotModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold">Add Timetable Slot</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= BASE_PATH ?>/academics/timetable.php" method="POST">
                <input type="hidden" name="action" value="add_slot">
                <input type="hidden" name="class_id" value="<?= $classId ?>">
                <input type="hidden" name="section_id" value="<?= $sectionId ?>">

                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Day <span class="text-danger">*</span></label>
                            <select name="day" class="form-select" required>
                                <?php foreach ($days as $d): ?>
                                    <option value="<?= $d ?>"><?= $d ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Period Number <span class="text-danger">*</span></label>
                            <select name="period_number" class="form-select" required>
                                <?php for ($i = 1; $i <= 9; $i++): ?>
                                    <option value="<?= $i ?>">Period <?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Start Time <span class="text-danger">*</span></label>
                            <input type="time" name="start_time" class="form-control" value="08:30" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">End Time <span class="text-danger">*</span></label>
                            <input type="time" name="end_time" class="form-control" value="09:15" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Subject <span class="text-danger">*</span></label>
                            <select name="subject_id" class="form-select" required>
                                <option value="">-- Choose Subject --</option>
                                <?php foreach ($subjectsForClass as $sub): ?>
                                    <option value="<?= $sub['id'] ?>"><?= e($sub['subject_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Teacher</label>
                            <select name="teacher_id" class="form-select">
                                <option value="">-- Optional --</option>
                                <?php foreach ($allTeachers as $t): ?>
                                    <option value="<?= $t['id'] ?>"><?= e($t['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Room / Lab</label>
                            <input type="text" name="room_no" class="form-control" placeholder="Room 101">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Period Slot</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
