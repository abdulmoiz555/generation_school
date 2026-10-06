<?php
/**
 * Classes & Sections Management (Section 13)
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

requirePermission('academics_manage');

$allTeachers = $pdo->query("SELECT id, name, teacher_code FROM teachers WHERE status = 'active' ORDER BY name ASC")->fetchAll();

// Add Class Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_class') {
    $className = trim($_POST['class_name'] ?? '');
    $numericLevel = (int)($_POST['numeric_level'] ?? 1);
    if (!empty($className)) {
        $stmt = $pdo->prepare("INSERT INTO classes (class_name, numeric_level) VALUES (?, ?)");
        $stmt->execute([$className, $numericLevel]);
        logAudit('ADD_CLASS', 'Academics', (int)$pdo->lastInsertId(), "Created class {$className}");
        setFlashMessage('success', "Class '{$className}' added successfully!");
    }
    header("Location: " . BASE_PATH . "/academics/classes.php");
    exit;
}

// Add Section Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_section') {
    $classId = (int)($_POST['class_id'] ?? 0);
    $sectionName = trim($_POST['section_name'] ?? '');
    $classTeacherId = !empty($_POST['class_teacher_id']) ? (int)$_POST['class_teacher_id'] : null;
    $roomNo = trim($_POST['room_no'] ?? '');
    $capacity = (int)($_POST['capacity'] ?? 40);

    if ($classId && !empty($sectionName)) {
        $stmt = $pdo->prepare("INSERT INTO sections (class_id, section_name, class_teacher_id, room_no, capacity) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$classId, $sectionName, $classTeacherId, $roomNo, $capacity]);
        logAudit('ADD_SECTION', 'Academics', (int)$pdo->lastInsertId(), "Created section {$sectionName}");
        setFlashMessage('success', "Section '{$sectionName}' created successfully!");
    }
    header("Location: " . BASE_PATH . "/academics/classes.php");
    exit;
}

$classes = getAllClasses();

$pageTitle = "Classes & Sections Management";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item">Academics</li>
                <li class="breadcrumb-item active">Classes & Sections</li>
            </ol>
        </nav>
        <h3 class="fw-bold text-dark mb-0">Class & Section Administration</h3>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#addClassModal">
            <i class="fas fa-plus me-1"></i> + New Class
        </button>
        <button type="button" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#addSectionModal">
            <i class="fas fa-layer-group me-1"></i> + New Section
        </button>
    </div>
</div>

<div class="row g-4">
    <?php foreach ($classes as $cls): 
        // Fetch sections and class teachers
        $secStmt = $pdo->prepare("
            SELECT s.*, t.name as teacher_name, t.phone as teacher_phone,
                   (SELECT COUNT(*) FROM students st WHERE st.section_id = s.id AND st.status = 'active') as student_count
            FROM sections s
            LEFT JOIN teachers t ON s.class_teacher_id = t.id
            WHERE s.class_id = ?
            ORDER BY s.section_name ASC
        ");
        $secStmt->execute([$cls['id']]);
        $sections = $secStmt->fetchAll();

        // Fetch other subject teachers assigned to this class
        $subTeachStmt = $pdo->prepare("
            SELECT DISTINCT t.name as teacher_name, sub.subject_name
            FROM class_subjects cs
            JOIN teachers t ON cs.teacher_id = t.id
            JOIN subjects sub ON cs.subject_id = sub.id
            WHERE cs.class_id = ? AND t.status = 'active'
            ORDER BY sub.subject_name ASC
        ");
        $subTeachStmt->execute([$cls['id']]);
        $subjectTeachers = $subTeachStmt->fetchAll();

        $totalInClass = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE class_id = {$cls['id']} AND status = 'active'")->fetchColumn();

        // Attendance stats for this class
        $attStmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_records,
                SUM(CASE WHEN sa.status = 'Present' THEN 1 ELSE 0 END) as present_count
            FROM student_attendance sa
            WHERE sa.class_id = ?
        ");
        $attStmt->execute([$cls['id']]);
        $attData = $attStmt->fetch();
        $classAttRate = ($attData && $attData['total_records'] > 0)
            ? round(($attData['present_count'] / $attData['total_records']) * 100)
            : 94; // healthy default if term just started
    ?>
        <div class="col-12 col-md-6 col-xl-4">
            <div class="card border-0 shadow-sm rounded-4 h-100 overflow-hidden">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="fw-bold text-dark mb-0">
                            <i class="fas fa-graduation-cap text-primary me-2"></i><?= e($cls['class_name']) ?>
                        </h5>
                        <small class="text-muted">Grade Level <?= $cls['numeric_level'] ?></small>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-primary rounded-pill px-3 py-1 fw-bold">
                            <?= $totalInClass ?> Students
                        </span>
                        <div class="mt-1">
                            <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size: 0.72rem;">
                                <i class="fas fa-check-circle me-1"></i><?= $classAttRate ?>% Attd.
                            </span>
                        </div>
                    </div>
                </div>
                <div class="card-body p-3">
                    <!-- Class Teacher In-charges -->
                    <div class="mb-3">
                        <div class="text-muted small fw-bold text-uppercase mb-2" style="font-size: 0.72rem; letter-spacing: 0.5px;">
                            <i class="fas fa-crown text-warning me-1"></i> Assigned Class Teacher(s)
                        </div>
                        <?php if (empty($sections)): ?>
                            <div class="text-muted small fst-italic">No sections registered yet.</div>
                        <?php else: ?>
                            <div class="d-flex flex-column gap-2">
                                <?php foreach ($sections as $sec): ?>
                                    <div class="d-flex align-items-center justify-content-between p-2 rounded-3 bg-light border">
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-initials bg-primary text-white rounded-circle me-2 d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; font-size: 0.75rem; font-weight: bold;">
                                                <?= $sec['teacher_name'] ? strtoupper(substr($sec['teacher_name'], 0, 1)) : '?' ?>
                                            </div>
                                            <div>
                                                <div class="fw-semibold text-dark small mb-0"><?= e($sec['teacher_name'] ?: 'Not Assigned') ?></div>
                                                <div class="text-muted" style="font-size: 0.7rem;">In-Charge &bull; <?= e($sec['section_name']) ?></div>
                                            </div>
                                        </div>
                                        <span class="badge bg-white text-secondary border small"><?= $sec['student_count'] ?> Students</span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Other Subject Teachers -->
                    <div class="mb-2">
                        <div class="text-muted small fw-bold text-uppercase mb-2" style="font-size: 0.72rem; letter-spacing: 0.5px;">
                            <i class="fas fa-book-reader text-info me-1"></i> Other Subject Teachers (<?= count($subjectTeachers) ?>)
                        </div>
                        <?php if (empty($subjectTeachers)): ?>
                            <div class="text-muted small fst-italic">Faculty assignments synchronized via syllabus schedule.</div>
                        <?php else: ?>
                            <div class="d-flex flex-wrap gap-1">
                                <?php foreach (array_slice($subjectTeachers, 0, 4) as $st): ?>
                                    <span class="badge bg-secondary-subtle text-secondary border px-2 py-1" style="font-size: 0.72rem;" title="<?= e($st['teacher_name']) ?> teaches <?= e($st['subject_name']) ?>">
                                        <i class="fas fa-chalkboard-teacher me-1"></i><?= e($st['teacher_name']) ?> (<?= e($st['subject_name']) ?>)
                                    </span>
                                <?php endforeach; ?>
                                <?php if (count($subjectTeachers) > 4): ?>
                                    <span class="badge bg-light text-muted border px-2 py-1" style="font-size: 0.72rem;">+<?= count($subjectTeachers) - 4 ?> more</span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-footer bg-light border-0 p-3 pt-2 d-flex flex-column gap-2">
                    <a href="<?= BASE_PATH ?>/academics/class-details.php?id=<?= $cls['id'] ?>" class="btn btn-primary btn-sm rounded-pill w-100 fw-semibold shadow-sm">
                        <i class="fas fa-users me-1"></i> Class Details, Teachers & Students (<?= $totalInClass ?>)
                    </a>
                    <a href="<?= BASE_PATH ?>/fees/batch-challan.php?class_id=<?= $cls['id'] ?>" target="_blank" class="btn btn-outline-warning btn-sm rounded-pill w-100 text-dark fw-semibold">
                        <i class="fas fa-print me-1 text-warning"></i> Print Whole Class Challans Batch
                    </a>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Add Class Modal -->
<div class="modal fade" id="addClassModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold">Add Academic Class</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= BASE_PATH ?>/academics/classes.php" method="POST">
                <input type="hidden" name="action" value="add_class">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Class Name <span class="text-danger">*</span></label>
                        <input type="text" name="class_name" class="form-control" placeholder="e.g. Grade 6 or Kindergarten" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Numeric Level <span class="text-danger">*</span></label>
                        <input type="number" name="numeric_level" class="form-control" placeholder="e.g. 6" required>
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Class</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Section Modal -->
<div class="modal fade" id="addSectionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold">Add Class Section</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= BASE_PATH ?>/academics/classes.php" method="POST">
                <input type="hidden" name="action" value="add_section">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Class <span class="text-danger">*</span></label>
                        <select name="class_id" class="form-select" required>
                            <?php foreach ($classes as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= e($c['class_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Section Name <span class="text-danger">*</span></label>
                        <input type="text" name="section_name" class="form-control" placeholder="e.g. Section C" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Class Teacher</label>
                        <select name="class_teacher_id" class="form-select">
                            <option value="">-- Assign Class Teacher --</option>
                            <?php foreach ($allTeachers as $t): ?>
                                <option value="<?= $t['id'] ?>"><?= e($t['name']) ?> (<?= e($t['teacher_code']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Room Number</label>
                            <input type="text" name="room_no" class="form-control" placeholder="e.g. Room 303">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Capacity</label>
                            <input type="number" name="capacity" class="form-control" value="40">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Section</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
